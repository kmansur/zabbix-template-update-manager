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
    ):
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
    for revision in parents:
        try:
            raw = git(repo, "show", f"{revision}:{path}")
        except subprocess.CalledProcessError:
            missing = True
            break
        raw_sha = hashlib.sha256(raw).hexdigest()
        if raw_sha in seen_blobs:
            continue
        seen_blobs.add(raw_sha)
        doc = yaml.safe_load(raw)
        templates = (doc or {}).get("zabbix_export", {}).get("templates", [])
        if not isinstance(templates, list):
            raise ValueError("Invalid templates list")
        matches = [t for t in templates if isinstance(t, dict) and
                   str(t.get("uuid", "")).lower().replace("-", "") == uuid]
        if len(matches) != 1:
            raise ValueError("Missing or ambiguous template UUID in historical source")
        vendor = matches[0].get("vendor") or {}
        if not isinstance(vendor, dict):
            raise ValueError("Invalid vendor structure")
        candidates.append({
            "commit": revision,
            "path": path,
            "raw_sha256": raw_sha,
            "vendor_name": str(vendor.get("name", "")),
            "vendor_version": str(vendor.get("version", "")),
        })
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
        "history_complete": False,
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
