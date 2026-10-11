#!/usr/bin/env python3
"""Independent Git revision-coverage audit for a ZTUM diagnostic report.

Verifies that the report enumerated the pinned commit's reachable revision
window. This does NOT prove path renames, vendor semantics or baseline authority.
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
from pathlib import Path

import yaml

SHA = re.compile(r"^[a-f0-9]{40}$")


def git(repo: Path, *args: str) -> str:
    return subprocess.check_output(["git", "-C", str(repo), *args], stderr=subprocess.DEVNULL, text=True).strip()


def audit(report: dict, repo: Path, max_commits: int) -> list[str]:
    errors: list[str] = []
    commit = report.get("source_commit")
    if not isinstance(commit, str) or not SHA.fullmatch(commit):
        return ["Invalid source commit"]
    if not 1 <= max_commits <= 2000:
        return ["Invalid audit limit"]
    try:
        expected = git(repo, "rev-list", "--topo-order", "--max-count", str(max_commits + 1), commit).splitlines()
        shallow = git(repo, "rev-parse", "--is-shallow-repository") == "true"
    except subprocess.CalledProcessError:
        return ["Pinned Git ancestry unavailable"]
    window = expected[:max_commits]
    truncated = len(expected) > max_commits
    if report.get("scanned_commits") != len(window):
        errors.append("Scanned revision count differs from Git ancestry")
    if report.get("truncated") is not truncated:
        errors.append("Truncation flag differs from Git ancestry")
    if report.get("shallow_repository") is not shallow:
        errors.append("Shallow flag differs from Git")
    candidates = report.get("candidates", [])
    missing = report.get("missing_revisions", [])
    invalid = report.get("invalid_revisions", [])
    if not isinstance(candidates, list) or not isinstance(missing, list) or not isinstance(invalid, list):
        return errors + ["Invalid candidate or evidence lists"]
    identities = [c.get("commit") for c in candidates if isinstance(c, dict)] + missing + invalid
    if any(not isinstance(sha, str) or sha not in window for sha in identities):
        errors.append("Report includes revisions outside pinned ancestry window")
    if len(identities) != len(set(identities)):
        errors.append("A revision is reported more than once")
    # A report may omit a path-missing revision without altering candidate hashes.
    # Independently inspect every revision of the declared ancestry window.
    path = report.get("path")
    if not isinstance(path, str) or not path.startswith("templates/") or not path.endswith(".yaml") or any(
        component in ("", ".", "..") for component in path.split("/")
    ):
        errors.append("Invalid diagnostic path")
    else:
        actual_missing = []
        for revision in window:
            exists = subprocess.run(
                ["git", "-C", str(repo), "cat-file", "-e", f"{revision}:{path}"],
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL
            )
            if exists.returncode != 0:
                actual_missing.append(revision)
        # An attacker must not label a valid unique source as "invalid" to
        # suppress its candidate fingerprint while keeping a signed report.
        uuid = report.get("uuid")
        for revision in invalid:
            if not isinstance(revision, str) or revision not in window:
                continue
            try:
                raw = subprocess.check_output(
                    ["git", "-C", str(repo), "show", f"{revision}:{path}"],
                    stderr=subprocess.DEVNULL
                )
                document = yaml.safe_load(raw)
                templates = document["zabbix_export"]["templates"]
                matches = [t for t in templates if isinstance(t, dict) and
                           str(t.get("uuid", "")).lower().replace("-", "") == uuid]
                if len(matches) == 1 and isinstance(matches[0].get("vendor") or {}, dict):
                    errors.append("Revision marked invalid contains a valid template identity")
            except (subprocess.CalledProcessError, yaml.YAMLError, KeyError, TypeError, ValueError):
                pass
        if set(actual_missing) != set(missing):
            errors.append("Missing-path revisions differ from actual Git ancestry")
    # Every readable source revision must be accounted for by a candidate
    # content hash. This checks content coverage independently of the builder.
    if isinstance(path, str) and path.startswith("templates/") and path.endswith(".yaml"):
        import hashlib
        expected_hashes = set()
        for revision in window:
            if revision in invalid:
                continue
            try:
                raw = subprocess.check_output(
                    ["git", "-C", str(repo), "show", f"{revision}:{path}"],
                    stderr=subprocess.DEVNULL
                )
            except subprocess.CalledProcessError:
                continue
            expected_hashes.add(hashlib.sha256(raw).hexdigest())
        declared_hashes = {
            candidate.get("raw_sha256") for candidate in candidates
            if isinstance(candidate, dict)
        }
        if expected_hashes != declared_hashes:
            errors.append("Distinct historical YAML contents differ from the Git ancestry window")
    if bool(missing) != report.get("missing_history_path"):
        errors.append("Missing-path evidence inconsistent")
    if report.get("authoritative") is not False or report.get("history_complete") is not False:
        errors.append("Diagnostic authority flags must be false")
    return errors


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--max-commits", type=int, default=150)
    args = parser.parse_args()
    errors = audit(json.loads(args.report.read_text(encoding="utf-8")), args.source_dir, args.max_commits)
    if errors:
        for error in errors:
            print("FAIL:", error)
        raise SystemExit(1)
    print("PASS: ancestry window flags consistent (NOT authoritative)")


if __name__ == "__main__":
    main()
