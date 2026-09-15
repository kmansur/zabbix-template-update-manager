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


with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp)
    template_dir = root / "templates" / "os" / "linux"
    template_dir.mkdir(parents=True)
    (template_dir / "template_os_linux.yaml").write_text(
        """zabbix_export:
  version: '7.0'
  templates:
    - uuid: f8f7908280354f2abeed07dc788c3747
      template: 'Linux by Zabbix agent'
      name: 'Linux by Zabbix agent'
      vendor:
        name: Zabbix
        version: 7.0-4
""",
        encoding="utf-8",
    )

    index = module.build_index(
        source_dir=root,
        line="7.0",
        source_ref="release/7.0",
        source_commit="a" * 40,
        source_commit_date="2026-09-14T13:01:54+00:00",
    )

    uuid = "f8f7908280354f2abeed07dc788c3747"
    assert_equal(1, index["schema_version"], "Schema version mismatch.")
    assert_equal("7.0", index["source"]["line"], "Release line mismatch.")
    assert_equal("7.0-4", index["templates"][uuid]["vendor_version"], "Vendor version mismatch.")
    assert_equal(
        "templates/os/linux/template_os_linux.yaml",
        index["templates"][uuid]["path"],
        "Template path mismatch.",
    )

    duplicate_dir = root / "templates" / "duplicate"
    duplicate_dir.mkdir(parents=True)
    (duplicate_dir / "template_duplicate.yaml").write_text(
        """zabbix_export:
  version: '7.0'
  templates:
    - uuid: f8f7908280354f2abeed07dc788c3747
      template: Duplicate
      name: Duplicate
""",
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
        if "duplicate template UUID" not in str(exc):
            raise
    else:
        raise AssertionError("Duplicate UUIDs must fail index generation.")

print("build_upstream_index tests passed.")
