#!/usr/bin/env python3
"""Build a NON-AUTHORITATIVE offline history candidate report.

This tool is deliberately diagnostic: traversing all reachable parents captures
merge-parent variants but does not prove path-rename completeness or version boundaries. The frontend MUST NOT consume this report for
automatic update authorization.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
from pathlib import Path

import yaml

COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")
UUID_RE = re.compile(r"^[0-9a-f]{32}$")


def git(repo: Path, *args: str) -> bytes:
    return subprocess.check_output(["git", "-C", str(repo), *args], stderr=subprocess.DEVNULL)


def build_report(repo: Path, commit: str, path: str, uuid: str, max_commits: int = 150) -> dict:
    if not COMMIT_RE.fullmatch(commit) or not UUID_RE.fullmatch(uuid):
        raise ValueError("Expected lowercase immutable commit and template UUID")
    if not path.startswith("templates/") or not path.endswith(".yaml") or any(
        part in ("", ".", "..") for part in path.split("/")
    ) or "\\\" in path or "\\x00" in path:
        raise ValueError("Invalid templates/*.yaml path")
    resolved = git(repo, "rev-parse", "--verify", commit + "^{commit}").decode().strip()
    if resolved != commit:
        raise ValueError("Commit does not resolve exactly")
    parents = git(repo, "rev-list", "--topo-order", "--max-count", str(max_commits + 1), commit).decode().splitlines()
    shallow_repository = git(repo, "rev-parse", "--is-shallow-repository").decode().strip() == "true"
    truncated = len(parents) > max_commits
    parents = parents[:max_commits]
    candidates = []
    seen_blobs = set()
    missing = False
    missing_revisions = []
    invalid_revisions = []
    # An ordinary rename can be followed for each reachable ancestor, but an
    # ambiguous rename or merge history must stay non-authoritative.
    tracked = path
    renames = []
    for revision in parents:
        try:
            raw = git(repo, "show", f"{revision}:{tracked}")
        except subprocess.CalledProcessError:
            missing = True
            missing_revisions.append(revision)
            continue
        raw_sha = hashlib.sha256(raw).hexdigest()
        if raw_sha in seen_blobs:
            continue
        seen_blobs.add(raw_sha)
        try:
            doc = yaml.safe_load(raw)
        except yaml.YAMLError:
            invalid_revisions.append(revision)
            continue
        if not isinstance(doc, dict) or not isinstance(doc.get("zabbix_export"), dict):
            invalid_revisions.append(revision)
            continue
        templates = doc["zabbix_export"].get("templates")
        if not isinstance(templates, list):
            invalid_revisions.append(revision)
            continue
        matches = [t for t in templates if isinstance(t, dict) and
                   str(t.get("uuid", "")).lower().replace("-", "") == uuid]
        if len(matches) != 1:
            invalid_revisions.append(revision)
            continue
        vendor = matches[0].get("vendor") or {}
        if not isinstance(vendor, dict):
            invalid_revisions.append(revision)
            continue
        candidates.append({
            "commit": revision,
            "path": tracked,
            "raw_sha256": raw_sha,
            "vendor_name": str(vendor.get("name", "")),
            "vendor_version": str(vendor.get("version", "")),
        })
    # Advisory only: --follow can reveal a linear rename chain, but does not
    # prove that every reachable merge parent used the same previous path.
    follow_lines = git(
        repo, "log", "--follow", "--find-renames", "--format=%H",
        "--name-status", commit, "--", path
    ).decode("utf-8", "replace").splitlines()
    for line in follow_lines:
        fields = line.split("\t")
        if len(fields) == 3 and re.fullmatch(r"R[0-9]{1,3}", fields[0]):
            old_path, new_path = fields[1:]
            if (old_path.startswith("templates/") and old_path.endswith(".yaml")
                    and new_path.startswith("templates/") and new_path.endswith(".yaml")
                    and ".." not in old_path.split("/")
                    and ".." not in new_path.split("/")):
                renames.append({"from": old_path, "to": new_path})
    return {
        "schema_version": 1,
        "purpose": "diagnostic-only",
        "authoritative": False,
        "traversal": "all-parents-topological-distinct-file-content",
        "source_commit": commit,
        "path": path,
        "uuid": uuid,
        "scanned_commits": len(parents),
        "truncated": truncated,
        "shallow_repository": shallow_repository,
        "missing_history_path": missing,
        "missing_revisions": missing_revisions,
        "invalid_revisions": invalid_revisions,
        "rename_transitions": renames,
        "rename_tracking_authoritative": False,
        "history_complete": False,
        "candidate_count": len(candidates),
        "distinct_vendor_versions": sorted({entry["vendor_version"] for entry in candidates}),
        "duplicate_vendor_versions": sorted({version for version in
            {entry["vendor_version"] for entry in candidates}
            if sum(entry["vendor_version"] == version for entry in candidates) > 1}),
        "limitation": "rename-history and version-boundary completeness not established",
        "candidates": candidates,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--commit", required=True)
    parser.add_argument("--path", required=True)
    parser.add_argument("--uuid", required=True)
    parser.add_argument("--max-commits", type=int, default=150)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    if not 1 <= args.max_commits <= 2000:
        parser.error("--max-commits must be between 1 and 2000")
    result = build_report(args.source_dir, args.commit, args.path, args.uuid, args.max_commits)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, sort_keys=True, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
