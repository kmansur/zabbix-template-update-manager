#!/usr/bin/env python3

from __future__ import annotations

import argparse
import hashlib
import json
import re
from pathlib import Path
from typing import Any

import yaml

UUID_RE = re.compile(r"^[a-f0-9]{32}$")
IDENTITY_FIELDS = (
    "name",
    "technical_name",
    "vendor_name",
    "vendor_version",
)


def normalize_uuid(value: Any) -> str:
    return str(value or "").strip().lower().replace("-", "")


def template_sha256(template: dict[str, Any]) -> str:
    canonical = json.dumps(
        template,
        sort_keys=True,
        separators=(",", ":"),
        ensure_ascii=False,
    ).encode("utf-8")
    return hashlib.sha256(canonical).hexdigest()


def template_identity(template: dict[str, Any], uuid: str) -> dict[str, Any]:
    vendor = template.get("vendor") or {}
    if not isinstance(vendor, dict):
        vendor = {}

    return {
        "uuid": uuid,
        "name": str(template.get("name") or template.get("template") or "").strip(),
        "technical_name": str(template.get("template") or "").strip(),
        "vendor_name": str(vendor.get("name") or "").strip(),
        "vendor_version": str(vendor.get("version") or "").strip(),
    }


def merge_record(
    records: dict[str, dict[str, Any]],
    uuid: str,
    identity: dict[str, Any],
    relative_path: str,
    content_hash: str,
    source_hash: str,
) -> None:
    if uuid not in records:
        records[uuid] = {
            **identity,
            "paths": [relative_path],
            "content_sha256s": [content_hash],
            "sources": [
                {
                    "path": relative_path,
                    "sha256": source_hash,
                }
            ],
        }
        return

    existing = records[uuid]
    conflicts = [
        field
        for field in IDENTITY_FIELDS
        if existing.get(field, "") != identity.get(field, "")
    ]
    if conflicts:
        raise RuntimeError(
            "conflicting template identity for UUID "
            f"{uuid} ({', '.join(conflicts)}): "
            f"{existing['paths'][0]} and {relative_path}"
        )

    if relative_path not in existing["paths"]:
        existing["paths"].append(relative_path)
        existing["paths"].sort()

    source_by_path = {source["path"]: source["sha256"] for source in existing["sources"]}
    if relative_path in source_by_path and source_by_path[relative_path] != source_hash:
        raise RuntimeError(
            f"conflicting raw source fingerprint for {relative_path}: "
            f"{source_by_path[relative_path]} != {source_hash}"
        )
    if relative_path not in source_by_path:
        existing["sources"].append({"path": relative_path, "sha256": source_hash})
        existing["sources"].sort(key=lambda source: source["path"])

    if content_hash not in existing["content_sha256s"]:
        existing["content_sha256s"].append(content_hash)
        existing["content_sha256s"].sort()


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

    records: dict[str, dict[str, Any]] = {}

    for yaml_path in sorted(templates_root.rglob("*.yaml")):
        relative_path = yaml_path.relative_to(source_dir).as_posix()
        try:
            raw_source = yaml_path.read_bytes()
            document = yaml.safe_load(raw_source.decode("utf-8"))
        except Exception as exc:
            raise RuntimeError(f"unable to parse {relative_path}: {exc}") from exc

        source_hash = hashlib.sha256(raw_source).hexdigest()

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

            merge_record(
                records=records,
                uuid=uuid,
                identity=template_identity(template, uuid),
                relative_path=relative_path,
                content_hash=template_sha256(template),
                source_hash=source_hash,
            )

    if not records:
        raise RuntimeError("no templates were found in the upstream source")

    duplicate_uuids = sum(1 for record in records.values() if len(record["paths"]) > 1)
    content_variant_uuids = sum(
        1 for record in records.values() if len(record["content_sha256s"]) > 1
    )

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
        "statistics": {
            "templates": len(records),
            "duplicate_uuid_definitions": duplicate_uuids,
            "content_variant_uuids": content_variant_uuids,
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
        f"Built {args.output} with {len(index['templates'])} unique template UUIDs "
        f"from {args.source_ref}@{args.source_commit[:12]} "
        f"({index['statistics']['duplicate_uuid_definitions']} duplicated UUIDs, "
        f"{index['statistics']['content_variant_uuids']} with content variants)"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
