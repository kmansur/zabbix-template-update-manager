#!/usr/bin/env python3
"""Fail-closed offline verification gate for SIGNED DIAGNOSTIC reports.

A successful result is only an integrity check. It cannot designate a report
authoritative and is not used by the ZTUM frontend.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path

from tools.sign_historical_diagnostic import verify as verify_signature
from tools.verify_historical_candidate_diagnostic import verify as verify_objects


def verify_signed_diagnostic(report: dict, envelope: dict, trusted_public_key: bytes,
                             key_id: str, upstream_repo: Path) -> list[str]:
    errors = verify_objects(report, upstream_repo)
    if not verify_signature(report, envelope, trusted_public_key, key_id):
        errors.append("Detached signature is not valid under the supplied trusted key")
    return errors


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--signature", type=Path, required=True)
    parser.add_argument("--trusted-public-key", type=Path, required=True)
    parser.add_argument("--key-id", required=True)
    parser.add_argument("--source-dir", type=Path, required=True)
    args = parser.parse_args()
    report = json.loads(args.report.read_text(encoding="utf-8"))
    envelope = json.loads(args.signature.read_text(encoding="utf-8"))
    errors = verify_signed_diagnostic(
        report, envelope, args.trusted_public_key.read_bytes(), args.key_id, args.source_dir
    )
    if errors:
        for error in errors:
            print("FAIL:", error)
        raise SystemExit(1)
    print("PASS: signature and immutable Git objects verified (DIAGNOSTIC ONLY)")


if __name__ == "__main__":
    main()
