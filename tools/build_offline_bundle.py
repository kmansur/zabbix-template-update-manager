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

COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")
PATH_RE = re.compile(r"^templates/(?:[A-Za-z0-9._+\-]+/)*[A-Za-z0-9._+\-]+\.yaml$")
MAX_HISTORY = 75


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


def build_history(repo: Path, root: Path, commit: str, path: str, limit: int, manifest: dict[str, str]) -> None:
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
    ids = ids[:limit]
    records = []

    for item in ids:
        try:
            source = run_git(repo, "show", f"{item}:{path}")
        except RuntimeError:
            # The remote runtime currently fetches historical content by current path.
            # Stop at the same safety boundary rather than inventing a renamed path.
            truncated = True
            break
        write_file(root, f"sources/{item}/{path}", source, manifest)
        records.append({"id": item, "message": ""})

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
                build_history(repo, output, commit, path, args.history_limit, manifest_files)
                processed_histories.add(key)

    manifest = {
        "schema_version": 1,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "generator": "tools/build_offline_bundle.py",
        "files": dict(sorted(manifest_files.items())),
    }
    (output / "manifest.json").write_text(
        json.dumps(manifest, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )

    print(f"Offline bundle created: {output}")
    print(f"Verified files: {len(manifest_files)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
