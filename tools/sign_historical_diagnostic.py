#!/usr/bin/env python3
"""Detached Ed25519 signing for diagnostic historical reports.

An intentionally offline developer/publisher tool. Never called by the Zabbix
frontend. Keys are supplied explicitly; no key generation or download here.
"""
from __future__ import annotations

import argparse
import base64
import json
from pathlib import Path

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey, Ed25519PublicKey

SCHEMA = "ztum-historical-diagnostic-signature-v1"


def canonical_bytes(report: dict) -> bytes:
    if not isinstance(report, dict):
        raise ValueError("Expected a JSON object")
    if report.get("authoritative") is not False or report.get("history_complete") is not False:
        raise ValueError("Only non-authoritative diagnostic reports may be signed")
    return (json.dumps(report, sort_keys=True, ensure_ascii=False, separators=(",", ":"), allow_nan=False) + "\n").encode("utf-8")


def sign(report: dict, private_pem: bytes, key_id: str) -> dict:
    if not key_id or len(key_id) > 128 or not all(c.isalnum() or c in "._-" for c in key_id):
        raise ValueError("Invalid key identifier")
    key = serialization.load_pem_private_key(private_pem, password=None)
    if not isinstance(key, Ed25519PrivateKey):
        raise ValueError("Ed25519 private key required")
    return {"schema": SCHEMA, "key_id": key_id,
            "signature": base64.b64encode(key.sign(canonical_bytes(report))).decode("ascii")}


def verify(report: dict, envelope: dict, trusted_public_pem: bytes, expected_key_id: str) -> bool:
    if not isinstance(envelope, dict) or set(envelope) != {"schema", "key_id", "signature"}:
        return False
    if envelope["schema"] != SCHEMA or envelope["key_id"] != expected_key_id:
        return False
    try:
        signature = base64.b64decode(envelope["signature"], validate=True)
        if len(signature) != 64:
            return False
        key = serialization.load_pem_public_key(trusted_public_pem)
        if not isinstance(key, Ed25519PublicKey):
            return False
        key.verify(signature, canonical_bytes(report))
        return True
    except (InvalidSignature, ValueError, TypeError):
        return False


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="mode", required=True)
    signer = sub.add_parser("sign")
    signer.add_argument("--report", required=True, type=Path)
    signer.add_argument("--private-key", required=True, type=Path)
    signer.add_argument("--key-id", required=True)
    signer.add_argument("--output", required=True, type=Path)
    validator = sub.add_parser("verify")
    validator.add_argument("--report", required=True, type=Path)
    validator.add_argument("--signature", required=True, type=Path)
    validator.add_argument("--trusted-public-key", required=True, type=Path)
    validator.add_argument("--key-id", required=True)
    args = parser.parse_args()
    report = json.loads(args.report.read_text(encoding="utf-8"))
    if args.mode == "sign":
        envelope = sign(report, args.private_key.read_bytes(), args.key_id)
        args.output.write_text(json.dumps(envelope, sort_keys=True, separators=(",", ":")) + "\n", encoding="utf-8")
    else:
        envelope = json.loads(args.signature.read_text(encoding="utf-8"))
        if not verify(report, envelope, args.trusted_public_key.read_bytes(), args.key_id):
            raise SystemExit("FAIL: invalid historical diagnostic signature")
        print("PASS: signed diagnostic verified (NOT authoritative)")


if __name__ == "__main__":
    main()
