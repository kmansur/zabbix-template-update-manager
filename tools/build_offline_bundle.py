#!/usr/bin/env python3
"""Build a verified local ZTUM upstream bundle from a local Zabbix Git checkout."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")
PATH_RE = re.compile(r"^templates/(?:[A-Za-z0-9._+\-]+/)*[A-Za-z0-9._+\-]+\.yaml$")
MAX_HISTORY = 75
SIGNATURE_SCHEMA = "ztum-offline-manifest-signature-v1"


def die(message: str) -> None:
    raise SystemExit(message)


def run_git(repo: Path, *args: str, text: bool = False):
    proc = subprocess.run(
        ["git", "-C", str(repo), *args],
        check=False,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=text,
    )
    if proc.returncode != 0:
        detail = proc.stderr.strip() if text else proc.stderr.decode("utf-8", "replace").strip()
        raise RuntimeError(f"git {' '.join(args)} failed: {detail}")
    return proc.stdout


def valid_path(path: str) -> bool:
    if not PATH_RE.fullmatch(path):
        return False
    return all(part not in {".", "..", ""} for part in path.split("/"))


def write_file(root: Path, relative: str, data: bytes, manifest: dict[str, str]) -> None:
    target = root / Path(relative)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(data)
    manifest[relative] = hashlib.sha256(data).hexdigest()


def canonical_manifest(manifest: dict) -> bytes:
    unsigned = {key: value for key, value in manifest.items() if key != "signature"}
    return (json.dumps(unsigned, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")


def sign_manifest(manifest: dict, key_path: Path, key_id: str) -> None:
    if not re.fullmatch(r"[A-Za-z0-9._-]{1,128}", key_id):
        raise RuntimeError("--key-id must contain only letters, numbers, dot, underscore or hyphen")
    try:
        key = serialization.load_pem_private_key(key_path.read_bytes(), password=None)
    except Exception as exc:
        raise RuntimeError(f"Unable to load Ed25519 signing key: {exc}") from exc
    if not isinstance(key, Ed25519PrivateKey):
        raise RuntimeError("--signing-private-key must contain an Ed25519 private PEM")
    manifest["signature"] = {
        "schema": SIGNATURE_SCHEMA,
        "key_id": key_id,
        "signature": __import__("base64").b64encode(key.sign(canonical_manifest(manifest))).decode("ascii"),
    }


def load_index(path: Path) -> tuple[bytes, dict]:
    raw = path.read_bytes()
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"{path}: invalid JSON: {exc}") from exc
    if data.get("schema_version") != 1:
        raise RuntimeError(f"{path}: unsupported index schema")
    source = data.get("source")
    templates = data.get("templates")
    if not isinstance(source, dict) or not isinstance(templates, dict):
        raise RuntimeError(f"{path}: invalid index structure")
    line = str(source.get("line", ""))
    commit = str(source.get("commit", "")).lower()
    if not re.fullmatch(r"\d+\.\d+", line) or not COMMIT_RE.fullmatch(commit):
        raise RuntimeError(f"{path}: invalid source line/commit")
    return raw, data


def current_sources(index: dict) -> dict[str, str]:
    result: dict[str, str] = {}
    for template in index["templates"].values():
        sources = template.get("sources")
        if not isinstance(sources, list) or not sources:
            raise RuntimeError("Offline bundle generation requires indexes with per-path raw source fingerprints")
        for record in sources:
            if not isinstance(record, dict):
                raise RuntimeError("Invalid source fingerprint record")
            path = str(record.get("path", ""))
            sha256 = str(record.get("sha256", "")).lower()
            if not valid_path(path) or not re.fullmatch(r"[0-9a-f]{64}", sha256):
                raise RuntimeError(f"Invalid source fingerprint for {path!r}")
            previous = result.get(path)
            if previous is not None and previous != sha256:
                raise RuntimeError(f"Conflicting source fingerprint for {path}")
            result[path] = sha256
    return result


def build_history(
    repo: Path,
    root: Path,
    commit: str,
    path: str,
    limit: int,
    manifest: dict[str, str],
    require_complete: bool = False,
) -> None:
    output = run_git(
        repo,
        "log",
        "--follow",
        "--format=%H",
        f"--max-count={limit + 1}",
        commit,
        "--",
        path,
        text=True,
    )
    ids = [line.strip().lower() for line in output.splitlines() if line.strip()]
    if any(not COMMIT_RE.fullmatch(item) for item in ids):
        raise RuntimeError(f"Invalid history commit returned for {path}")

    truncated = len(ids) > limit
    if truncated and require_complete:
        raise RuntimeError(
            f"History for {path} exceeds --history-limit={limit}; refusing an incomplete bundle"
        )
    ids = ids[:limit]
    records = []
    tracked_path = path

    for item in ids:
        try:
            source = run_git(repo, "show", f"{item}:{tracked_path}")
        except RuntimeError:
            if require_complete:
                raise RuntimeError(
                    f"History for {path} is incomplete at {item}; refusing an incomplete bundle"
                ) from None
            truncated = True
            break
        source_path = tracked_path
        write_file(root, f"sources/{item}/{source_path}", source, manifest)
        previous_paths = []
        changes = run_git(repo, "diff-tree", "--no-commit-id", "--name-status", "-r", "--find-renames=0%", "-m", item, text=True)
        for line in changes.splitlines():
            fields = line.split("\t")
            if len(fields) == 3 and re.fullmatch(r"R[0-9]{1,3}", fields[0]) and fields[2] == tracked_path:
                if valid_path(fields[1]):
                    previous_paths.append(fields[1])
        if len(set(previous_paths)) > 1:
            raise RuntimeError(f"Ambiguous rename provenance for {item}:{tracked_path}")
        previous_path = previous_paths[0] if previous_paths else None
        if previous_path is not None:
            rename = {
                "schema_version": 1,
                "commit": item,
                "path": tracked_path,
                "previous_path": previous_path,
            }
            rename_bytes = (json.dumps(rename, sort_keys=True, separators=(",", ":")) + "\n").encode()
            write_file(root, f"renames/{item}/{hashlib.sha256(tracked_path.encode()).hexdigest()}.json", rename_bytes, manifest)
            tracked_path = previous_path
        records.append({"id": item, "message": "", "path": source_path})

    history = {
        "schema_version": 1,
        "path": path,
        "until": commit,
        "truncated": truncated,
        "commits": records,
    }
    encoded = (json.dumps(history, sort_keys=True, separators=(",", ":")) + "\n").encode()
    path_hash = hashlib.sha256(path.encode()).hexdigest()
    write_file(root, f"history/{commit}/{path_hash}.json", encoded, manifest)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--zabbix-repo", required=True, type=Path)
    parser.add_argument("--index", required=True, action="append", type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--history-limit", type=int, default=MAX_HISTORY)
    parser.add_argument(
        "--require-complete-history",
        action="store_true",
        help="fail instead of packaging a truncated history record",
    )
    parser.add_argument("--signing-private-key", required=True, type=Path)
    parser.add_argument("--key-id", required=True)
    args = parser.parse_args()

    if args.history_limit < 1 or args.history_limit > MAX_HISTORY:
        die(f"--history-limit must be between 1 and {MAX_HISTORY}")

    repo = args.zabbix_repo.resolve()
    try:
        inside = run_git(repo, "rev-parse", "--is-inside-work-tree", text=True).strip()
    except Exception as exc:
        die(str(exc))
    if inside != "true":
        die("--zabbix-repo is not a Git work tree")

    output = args.output.resolve()
    if output.exists() and any(output.iterdir()):
        die("--output must not exist or must be an empty directory")
    output.mkdir(parents=True, exist_ok=True)

    manifest_files: dict[str, str] = {}
    processed_histories: set[tuple[str, str]] = set()

    for index_path in args.index:
        raw, index = load_index(index_path)
        line = str(index["source"]["line"])
        commit = str(index["source"]["commit"]).lower()
        write_file(output, f"indexes/{line}.json", raw, manifest_files)

        for path, expected_sha in current_sources(index).items():
            source = run_git(repo, "show", f"{commit}:{path}")
            actual = hashlib.sha256(source).hexdigest()
            if actual != expected_sha:
                raise RuntimeError(
                    f"Raw source fingerprint mismatch for {path}: expected {expected_sha}, got {actual}"
                )
            write_file(output, f"sources/{commit}/{path}", source, manifest_files)

            key = (commit, path)
            if key not in processed_histories:
                build_history(
                    repo,
                    output,
                    commit,
                    path,
                    args.history_limit,
                    manifest_files,
                    args.require_complete_history,
                )
                processed_histories.add(key)

    manifest = {
        "schema_version": 1,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "generator": "tools/build_offline_bundle.py",
        "files": dict(sorted(manifest_files.items())),
    }
    sign_manifest(manifest, args.signing_private_key, args.key_id)
    (output / "manifest.json").write_text(
        json.dumps(manifest, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )

    print(f"Offline bundle created: {output}")
    print(f"Verified files: {len(manifest_files)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
