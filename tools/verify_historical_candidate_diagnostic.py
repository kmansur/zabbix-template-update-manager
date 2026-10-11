#!/usr/bin/env python3
"""Strict verification of a non-authoritative ZTUM offline historical report.

The validator does not authenticate publishers or declare histories complete.
It detects tampering and stale candidate hashes against a pinned local Git tree.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
from pathlib import Path

SHA = re.compile(r"^[a-f0-9]{40}$")
SHA256 = re.compile(r"^[a-f0-9]{64}$")
UUID = re.compile(r"^[a-f0-9]{32}$")


def verify(report: dict, repo: Path) -> list[str]:
    errors = []
    if report.get("schema_version") != 1 or report.get("purpose") != "diagnostic-only":
        errors.append("Unexpected schema/purpose")
    if report.get("authoritative") is not False or report.get("history_complete") is not False:
        errors.append("Diagnostic must explicitly deny authority/completeness")
    if report.get("rename_tracking_authoritative") is not False:
        errors.append("Rename evidence must not claim authority")
    if not SHA.fullmatch(str(report.get("source_commit", ""))):
        errors.append("Invalid pinned source commit")
    source_commit = str(report.get("source_commit", ""))
    if SHA.fullmatch(source_commit):
        try:
            checked = subprocess.check_output(["git", "-C", str(repo), "rev-parse", "--verify", source_commit + "^{commit}"], stderr=subprocess.DEVNULL).decode().strip()
            if checked != source_commit:
                errors.append("Source commit identity mismatch")
        except subprocess.CalledProcessError:
            errors.append("Pinned source commit unavailable")
    if not UUID.fullmatch(str(report.get("uuid", ""))):
        errors.append("Invalid template UUID")
    path = report.get("path", "")
    if not isinstance(path, str) or not path.startswith("templates/") or not path.endswith(".yaml") or any(
        item in ("", ".", "..") for item in path.split("/")
    ):
        errors.append("Invalid source path")
    candidates = report.get("candidates")
    if not isinstance(candidates, list):
        return errors + ["Candidates must be a list"]
    if report.get("history_complete") is not False or report.get("authoritative") is not False:
        errors.append("Report must remain non-authoritative")
    for field in ("truncated", "shallow_repository", "missing_history_path"):
        if type(report.get(field)) is not bool:
            errors.append(f"Invalid diagnostic completeness field: {field}")
    for field in ("missing_revisions", "invalid_revisions", "rename_transitions"):
        if not isinstance(report.get(field), list):
            errors.append(f"Invalid diagnostic evidence list: {field}")
    if report.get("traversal") != "all-parents-topological-distinct-file-content":
        errors.append("Unexpected history traversal algorithm")
    if not isinstance(report.get("scanned_commits"), int) or report["scanned_commits"] < len(candidates):
        errors.append("Invalid scanned revision count")
    if report.get("candidate_count") != len(candidates):
        errors.append("Candidate count mismatch")
    seen = set()
    for index, candidate in enumerate(candidates):
        if not isinstance(candidate, dict):
            errors.append(f"Candidate {index}: not an object")
            continue
        sha = str(candidate.get("commit", ""))
        candidate_path = candidate.get("path", "")
        fingerprint = str(candidate.get("raw_sha256", ""))
        if not SHA.fullmatch(sha) or not SHA256.fullmatch(fingerprint):
            errors.append(f"Candidate {index}: invalid commit/hash")
            continue
        if not isinstance(candidate_path, str) or candidate_path != path:
            errors.append(f"Candidate {index}: unexpected path")
            continue
        if SHA.fullmatch(source_commit):
            ancestry = subprocess.run(["git", "-C", str(repo), "merge-base", "--is-ancestor", sha, source_commit], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            if ancestry.returncode != 0:
                errors.append(f"Candidate {index}: commit is not an ancestor of source")
        if sha in seen:
            errors.append(f"Candidate {index}: duplicated commit")
        seen.add(sha)
        try:
            raw = subprocess.check_output(
                ["git", "-C", str(repo), "show", f"{sha}:{candidate_path}"],
                stderr=subprocess.DEVNULL,
            )
        except subprocess.CalledProcessError:
            errors.append(f"Candidate {index}: immutable source unavailable")
            continue
        if hashlib.sha256(raw).hexdigest() != fingerprint:
            errors.append(f"Candidate {index}: source fingerprint mismatch")
    return errors


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--source-dir", type=Path, required=True)
    args = parser.parse_args()
    report = json.loads(args.report.read_text(encoding="utf-8"))
    errors = verify(report, args.source_dir)
    for error in errors:
        print("FAIL:", error)
    if errors:
        raise SystemExit(1)
    print("PASS: diagnostic report fingerprints validated (NOT authoritative)")


if __name__ == "__main__":
    main()
