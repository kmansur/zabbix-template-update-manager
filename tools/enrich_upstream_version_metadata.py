#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
import subprocess
from pathlib import Path
from typing import Any

import yaml

COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")
UUID_RE = re.compile(r"^[0-9a-f]{32}$")


class HistoryBoundaryError(RuntimeError):
    """Raised when a shallow Git checkout cannot identify the real blame commit."""


def normalize_uuid(value: Any) -> str:
    return str(value or "").strip().lower().replace("-", "")


def mapping_value(node: Any, key: str) -> Any | None:
    if not isinstance(node, yaml.nodes.MappingNode):
        return None

    for key_node, value_node in node.value:
        if getattr(key_node, "value", None) == key:
            return value_node

    return None


def version_lines(raw_source: bytes) -> dict[str, int]:
    try:
        root = yaml.compose(raw_source.decode("utf-8"))
    except Exception as exc:
        raise RuntimeError(f"unable to compose YAML for version metadata: {exc}") from exc

    export = mapping_value(root, "zabbix_export")
    templates = mapping_value(export, "templates")
    if not isinstance(templates, yaml.nodes.SequenceNode):
        return {}

    result: dict[str, int] = {}
    for template in templates.value:
        uuid_node = mapping_value(template, "uuid")
        vendor_node = mapping_value(template, "vendor")
        version_node = mapping_value(vendor_node, "version")

        uuid = normalize_uuid(getattr(uuid_node, "value", ""))
        if UUID_RE.fullmatch(uuid) and version_node is not None:
            result[uuid] = int(version_node.start_mark.line) + 1

    return result


def run_git(repo: Path, *args: str) -> str:
    proc = subprocess.run(
        ["git", "-C", str(repo), *args],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        check=False,
    )
    if proc.returncode != 0:
        detail = proc.stderr.strip() or proc.stdout.strip() or f"exit {proc.returncode}"
        raise RuntimeError(f"git {' '.join(args)} failed: {detail}")

    return proc.stdout


def blame_version_line(repo: Path, path: str, line: int) -> tuple[str, str]:
    output = run_git(repo, "blame", "--line-porcelain", "-L", f"{line},{line}", "HEAD", "--", path)
    lines = output.splitlines()
    if not lines:
        raise RuntimeError(f"git blame returned no data for {path}:{line}")

    commit = lines[0].split()[0].lstrip("^").lower()
    if not COMMIT_RE.fullmatch(commit):
        raise RuntimeError(f"git blame returned an invalid commit for {path}:{line}")

    if any(item == "boundary" for item in lines[1:]):
        raise HistoryBoundaryError(
            f"history boundary reached while resolving {path}:{line}; a deeper checkout is required"
        )

    commit_date = run_git(repo, "show", "-s", "--format=%cI", commit).strip()
    if not commit_date:
        raise RuntimeError(f"unable to determine commit date for {commit}")

    return commit, commit_date


def load_index(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        raise RuntimeError(f"{path}: unable to load index: {exc}") from exc

    if not isinstance(data, dict) or not isinstance(data.get("templates"), dict):
        raise RuntimeError(f"{path}: invalid upstream index structure")

    return data


def valid_previous_metadata(record: Any, version: str) -> tuple[str, str] | None:
    if not isinstance(record, dict) or str(record.get("vendor_version", "")).strip() != version:
        return None

    commit = str(record.get("version_commit", "")).strip().lower()
    date = str(record.get("version_date", "")).strip()
    if COMMIT_RE.fullmatch(commit) and date:
        return commit, date

    return None


def enrich(index_path: Path, source_dir: Path, previous_path: Path | None = None) -> dict[str, Any]:
    index = load_index(index_path)
    previous = load_index(previous_path) if previous_path is not None and previous_path.is_file() else {}
    previous_templates = previous.get("templates") if isinstance(previous, dict) else {}
    if not isinstance(previous_templates, dict):
        previous_templates = {}

    line_cache: dict[str, dict[str, int]] = {}
    blame_cache: dict[tuple[str, int], tuple[str, str]] = {}

    for uuid, record in index["templates"].items():
        if not isinstance(record, dict):
            continue

        version = str(record.get("vendor_version", "")).strip()
        if version == "":
            continue

        reused = valid_previous_metadata(previous_templates.get(uuid), version)
        if reused is not None:
            record["version_commit"], record["version_date"] = reused
            continue

        candidates: list[tuple[str, str]] = []
        paths = record.get("paths")
        if not isinstance(paths, list) or not paths:
            raise RuntimeError(f"template {uuid} has no source paths")

        for relative_path in paths:
            if not isinstance(relative_path, str) or relative_path == "":
                raise RuntimeError(f"template {uuid} has an invalid source path")

            if relative_path not in line_cache:
                raw = (source_dir / relative_path).read_bytes()
                line_cache[relative_path] = version_lines(raw)

            line = line_cache[relative_path].get(uuid)
            if line is None:
                raise RuntimeError(
                    f"unable to locate vendor.version line for template {uuid} in {relative_path}"
                )

            cache_key = (relative_path, line)
            if cache_key not in blame_cache:
                blame_cache[cache_key] = blame_version_line(source_dir, relative_path, line)

            candidates.append(blame_cache[cache_key])

        # Duplicate UUID definitions are legal when identity is identical. Use the
        # newest version-line change so the displayed date reflects when every
        # current definition had reached the advertised vendor version.
        commit, date = max(candidates, key=lambda item: item[1])
        record["version_commit"] = commit
        record["version_date"] = date

    index_path.write_text(
        json.dumps(index, indent=2, sort_keys=False, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    return index


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Attach vendor-version commit/date metadata to a generated Zabbix upstream index."
    )
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--index", type=Path, required=True)
    parser.add_argument("--previous-index", type=Path)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        index = enrich(
            index_path=args.index.resolve(),
            source_dir=args.source_dir.resolve(),
            previous_path=args.previous_index.resolve() if args.previous_index else None,
        )
    except HistoryBoundaryError as exc:
        print(f"Version metadata requires deeper Git history: {exc}", flush=True)
        return 3
    except Exception as exc:
        print(f"Version metadata enrichment failed: {exc}", flush=True)
        return 1

    print(f"Attached version metadata to {len(index['templates'])} templates in {args.index}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
