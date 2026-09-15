#!/usr/bin/env python3

from __future__ import annotations

import importlib.util
import tempfile
from pathlib import Path

MODULE_PATH = Path(__file__).resolve().parents[1] / "tools" / "build_upstream_index.py"
spec = importlib.util.spec_from_file_location("build_upstream_index", MODULE_PATH)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(module)


def assert_equal(expected, actual, message: str) -> None:
    if expected != actual:
        raise AssertionError(f"{message}\nExpected: {expected!r}\nActual: {actual!r}")


BASE_UUID = "f8f7908280354f2abeed07dc788c3747"
BASE_TEMPLATE = """zabbix_export:
  version: '7.0'
  templates:
    - uuid: f8f7908280354f2abeed07dc788c3747
      template: 'Linux by Zabbix agent'
      name: 'Linux by Zabbix agent'
      description: Base definition
      vendor:
        name: Zabbix
        version: 7.0-4
"""

with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp)
    first_dir = root / "templates" / "os" / "linux"
    first_dir.mkdir(parents=True)
    (first_dir / "template_os_linux.yaml").write_text(BASE_TEMPLATE, encoding="utf-8")

    index = module.build_index(
        source_dir=root,
        line="7.0",
        source_ref="release/7.0",
        source_commit="a" * 40,
        source_commit_date="2026-09-14T13:01:54+00:00",
    )

    assert_equal(1, index["schema_version"], "Schema version mismatch.")
    assert_equal("7.0", index["source"]["line"], "Release line mismatch.")
    assert_equal("7.0-4", index["templates"][BASE_UUID]["vendor_version"], "Vendor version mismatch.")
    assert_equal(
        ["templates/os/linux/template_os_linux.yaml"],
        index["templates"][BASE_UUID]["paths"],
        "Template source path mismatch.",
    )
    assert_equal(1, len(index["templates"][BASE_UUID]["content_sha256s"]), "Expected one content hash.")

    duplicate_dir = root / "templates" / "bundle" / "linux"
    duplicate_dir.mkdir(parents=True)
    (duplicate_dir / "template_os_linux.yaml").write_text(BASE_TEMPLATE, encoding="utf-8")

    merged = module.build_index(
        source_dir=root,
        line="7.0",
        source_ref="release/7.0",
        source_commit="a" * 40,
        source_commit_date="2026-09-14T13:01:54+00:00",
    )
    assert_equal(2, len(merged["templates"][BASE_UUID]["paths"]), "Equivalent duplicate UUID paths must be retained.")
    assert_equal(1, len(merged["templates"][BASE_UUID]["content_sha256s"]), "Equivalent duplicate content must collapse to one hash.")
    assert_equal(1, merged["statistics"]["duplicate_uuid_definitions"], "Duplicate UUID statistic mismatch.")

    variant_dir = root / "templates" / "variant" / "linux"
    variant_dir.mkdir(parents=True)
    (variant_dir / "template_os_linux.yaml").write_text(
        BASE_TEMPLATE.replace("Base definition", "Alternative definition"),
        encoding="utf-8",
    )

    variants = module.build_index(
        source_dir=root,
        line="7.0",
        source_ref="release/7.0",
        source_commit="a" * 40,
        source_commit_date="2026-09-14T13:01:54+00:00",
    )
    assert_equal(3, len(variants["templates"][BASE_UUID]["paths"]), "All official UUID paths must be retained.")
    assert_equal(2, len(variants["templates"][BASE_UUID]["content_sha256s"]), "Distinct content variants must be retained as hashes.")
    assert_equal(1, variants["statistics"]["content_variant_uuids"], "Content variant statistic mismatch.")

    conflict_dir = root / "templates" / "conflict"
    conflict_dir.mkdir(parents=True)
    (conflict_dir / "template_conflict.yaml").write_text(
        BASE_TEMPLATE.replace("Linux by Zabbix agent", "Conflicting identity"),
        encoding="utf-8",
    )

    try:
        module.build_index(
            source_dir=root,
            line="7.0",
            source_ref="release/7.0",
            source_commit="a" * 40,
            source_commit_date="2026-09-14T13:01:54+00:00",
        )
    except RuntimeError as exc:
        if "conflicting template identity" not in str(exc):
            raise
    else:
        raise AssertionError("Conflicting identities for one UUID must fail index generation.")

print("build_upstream_index tests passed.")
