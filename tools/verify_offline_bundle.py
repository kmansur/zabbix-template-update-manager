#!/usr/bin/env python3
"""Verify a signed ZTUM offline bundle before it is copied to a frontend host."""

from __future__ import annotations

import argparse
import base64
import hashlib
import json
import re
from pathlib import Path
from typing import NoReturn

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PublicKey


SIGNATURE_SCHEMA = "ztum-offline-manifest-signature-v1"
KEY_ID_RE = re.compile(r"^[A-Za-z0-9._-]{1,128}$")


def fail(message: str) -> NoReturn:
    raise SystemExit(f"FAIL: {message}")


def canonical_manifest(manifest: dict) -> bytes:
    unsigned = {key: value for key, value in manifest.items() if key != "signature"}
    return (json.dumps(unsigned, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode(
        "utf-8"
    )


def safe_relative(path: str) -> bool:
    if not path or path.startswith("/") or "\\" in path:
        return False
    parts = path.split("/")
    return all(part not in {"", ".", ".."} for part in parts)


def load_public_key(path: Path) -> Ed25519PublicKey:
    if path.is_symlink() or not path.is_file():
        fail("public key is missing or unsafe")
    try:
        key = serialization.load_pem_public_key(path.read_bytes())
    except Exception as exc:  # pragma: no cover - error text is operator-facing
        fail(f"unable to load public key: {exc}")
    if not isinstance(key, Ed25519PublicKey):
        fail("public key is not Ed25519")
    return key


def verify(bundle: Path, public_key_path: Path, expected_key_id: str) -> int:
    bundle = bundle.resolve()
    if not bundle.is_dir() or bundle.is_symlink():
        fail("bundle directory is missing or unsafe")
    for path in bundle.rglob("*"):
        if path.is_symlink():
            fail(f"bundle contains an unsafe symlink: {path.relative_to(bundle).as_posix()}")
    if not KEY_ID_RE.fullmatch(expected_key_id):
        fail("key ID has an invalid format")

    manifest_path = bundle / "manifest.json"
    if not manifest_path.is_file() or manifest_path.is_symlink():
        fail("manifest.json is missing or unsafe")
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        fail(f"manifest cannot be decoded: {exc}")
    if not isinstance(manifest, dict) or manifest.get("schema_version") != 1:
        fail("unsupported manifest schema")
    files = manifest.get("files")
    signature = manifest.get("signature")
    if not isinstance(files, dict) or not files or not isinstance(signature, dict):
        fail("manifest structure is invalid")
    if signature.get("schema") != SIGNATURE_SCHEMA or signature.get("key_id") != expected_key_id:
        fail("manifest signature identity is invalid")

    try:
        signature_bytes = base64.b64decode(signature["signature"], validate=True)
    except (KeyError, ValueError, TypeError):
        fail("manifest signature is not valid base64")
    try:
        load_public_key(public_key_path).verify(signature_bytes, canonical_manifest(manifest))
    except InvalidSignature:
        fail("manifest signature is invalid")
    except (ValueError, TypeError) as exc:
        fail(f"manifest signature cannot be verified: {exc}")

    expected_paths: set[str] = set()
    for relative, expected_sha in files.items():
        if not isinstance(relative, str) or not safe_relative(relative):
            fail("manifest contains an unsafe file path")
        if not isinstance(expected_sha, str) or re.fullmatch(r"[0-9a-f]{64}", expected_sha) is None:
            fail(f"manifest contains an invalid SHA-256 for {relative}")
        target = bundle / Path(relative)
        if target.is_symlink() or not target.is_file():
            fail(f"bundle file is missing or unsafe: {relative}")
        actual = hashlib.sha256(target.read_bytes()).hexdigest()
        if actual != expected_sha:
            fail(f"bundle file hash mismatch: {relative}")
        expected_paths.add(relative)

    actual_paths = {
        path.relative_to(bundle).as_posix()
        for path in bundle.rglob("*")
        if path.is_file() and path != manifest_path
    }
    extras = sorted(actual_paths - expected_paths)
    missing = sorted(expected_paths - actual_paths)
    if missing:
        fail(f"manifest references missing files: {', '.join(missing[:5])}")
    if extras:
        fail(f"bundle contains unlisted files: {', '.join(extras[:5])}")

    print(f"PASS: verified {len(expected_paths)} signed offline bundle files (key {expected_key_id})")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--bundle", required=True, type=Path)
    parser.add_argument("--public-key", required=True, type=Path)
    parser.add_argument("--key-id", required=True)
    args = parser.parse_args()
    return verify(args.bundle, args.public_key, args.key_id)


if __name__ == "__main__":
    raise SystemExit(main())
