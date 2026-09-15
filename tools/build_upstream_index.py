#!/usr/bin/env python3

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path
from typing import Any

import yaml

UUID_RE = re.compile(r"^[a-f0-9]{32}$")


def normalize_uuid(value: Any) -> str:
    return str(value or "").strip().lower().replace("-", "")


def build_index(
    source_dir: Path,
    line: str,
    source_ref: str,
    source_commit: str,
    source_commit_date: str,
) -> dict[str, Any]:
    templates_root = source_dir / "templates"
    if not templates_root.is_dir():
        raise RuntimeError(f"templates directory not found: {templates_root}")

    records: dict[str, dict[str, str]] = {}

    for yaml_path in sorted(templates_root.rglob("*.yaml")):
        relative_path = yaml_path.relative_to(source_dir).as_posix()
        try:
            document = yaml.safe_load(yaml_path.read_text(encoding="utf-8"))
        except Exception as exc:
            raise RuntimeError(f"unable to parse {relative_path}: {exc}") from exc

        if not isinstance(document, dict):
            continue

        export = document.get("zabbix_export")
        if not isinstance(export, dict):
            continue

        templates = export.get("templates") or []
        if not isinstance(templates, list):
            raise RuntimeError(f"invalid templates list in {relative_path}")

        for template in templates:
            if not isinstance(template, dict):
                raise RuntimeError(f"invalid template record in {relative_path}")

            uuid = normalize_uuid(template.get("uuid"))
            if not UUID_RE.fullmatch(uuid):
                raise RuntimeError(f"template without valid UUID in {relative_path}")
            if uuid in records:
                raise RuntimeError(
                    f"duplicate template UUID {uuid}: {records[uuid]['path']} and {relative_path}"
                )

            vendor = template.get("vendor") or {}
            if not isinstance(vendor, dict):
                vendor = {}

            records[uuid] = {
                "uuid": uuid,
                "name": str(template.get("name") or template.get("template") or "").strip(),
                "technical_name": str(template.get("template") or "").strip(),
                "vendor_name": str(vendor.get("name") or "").strip(),
                "vendor_version": str(vendor.get("version") or "").strip(),
                "path": relative_path,
            }

    if not records:
        raise RuntimeError("no templates were found in the upstream source")

    return {
        "schema_version": 1,
        "source": {
            "line": line,
            "ref": source_ref,
            "commit": source_commit,
            "commit_date": source_commit_date,
            "canonical_repository": "https://git.zabbix.com/projects/ZBX/repos/zabbix/",
            "mirror_repository": "https://github.com/zabbix/zabbix",
        },
        "templates": dict(sorted(records.items())),
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build a compact official Zabbix template UUID index.")
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--line", required=True)
    parser.add_argument("--source-ref", required=True)
    parser.add_argument("--source-commit", required=True)
    parser.add_argument("--source-commit-date", required=True)
    parser.add_argument("--output", type=Path, required=True)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    index = build_index(
        source_dir=args.source_dir.resolve(),
        line=args.line,
        source_ref=args.source_ref,
        source_commit=args.source_commit,
        source_commit_date=args.source_commit_date,
    )

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(index, indent=2, sort_keys=False, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    print(
        f"Built {args.output} with {len(index['templates'])} templates "
        f"from {args.source_ref}@{args.source_commit[:12]}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
