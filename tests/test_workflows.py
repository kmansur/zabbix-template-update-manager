#!/usr/bin/env python3
from __future__ import annotations

import re
from pathlib import Path

import yaml


def walk(value):
    if isinstance(value, dict):
        for item in value.values():
            yield from walk(item)
    elif isinstance(value, list):
        for item in value:
            yield from walk(item)
    else:
        yield value


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    workflows = sorted((root / ".github" / "workflows").glob("*.yml"))
    assert workflows, "No GitHub Actions workflows found"

    for workflow in workflows:
        data = yaml.load(workflow.read_text(encoding="utf-8"), Loader=yaml.BaseLoader)
        assert isinstance(data, dict), f"{workflow}: invalid YAML mapping"
        assert "jobs" in data and isinstance(data["jobs"], dict) and data["jobs"], f"{workflow}: jobs missing"

        for value in walk(data):
            if not isinstance(value, str) or not value.startswith("actions/"):
                continue
            assert re.fullmatch(r"actions/[A-Za-z0-9_.-]+@v\d+", value), (
                f"{workflow}: first-party action must be pinned to an explicit major version: {value}"
            )

    print(f"Workflow validation passed for {len(workflows)} file(s).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
