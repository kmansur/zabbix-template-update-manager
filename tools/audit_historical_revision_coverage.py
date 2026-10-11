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
        if set(actual_missing) != set(missing):
            errors.append("Missing-path revisions differ from actual Git ancestry")
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
