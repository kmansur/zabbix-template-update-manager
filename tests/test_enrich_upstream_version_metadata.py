#!/usr/bin/env python3

from __future__ import annotations

import importlib.util
import json
import os
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def load_module(name: str, path: Path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


builder = load_module("build_upstream_index", ROOT / "tools" / "build_upstream_index.py")
enricher = load_module("enrich_upstream_version_metadata", ROOT / "tools" / "enrich_upstream_version_metadata.py")


def run(repo: Path, *args: str, env: dict[str, str] | None = None) -> str:
    proc = subprocess.run(
        ["git", "-C", str(repo), *args],
        check=True,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        env=env,
    )
    return proc.stdout.strip()


def commit(repo: Path, message: str, when: str) -> str:
    env = os.environ.copy()
    env["GIT_AUTHOR_DATE"] = when
    env["GIT_COMMITTER_DATE"] = when
    run(repo, "add", ".")
    run(repo, "commit", "-m", message, env=env)
    return run(repo, "rev-parse", "HEAD")


TEMPLATE_V1 = """zabbix_export:
  version: '7.0'
  templates:
    - uuid: 11111111111111111111111111111111
      template: 'Template One'
      name: 'Template One'
      vendor:
        name: Zabbix
        version: 7.0-1
    - uuid: 22222222222222222222222222222222
      template: 'Template Two'
      name: 'Template Two'
      vendor:
        name: Zabbix
        version: 7.0-1
"""

TEMPLATE_V2 = TEMPLATE_V1.replace(
    "template: 'Template One'\n      name: 'Template One'\n      vendor:\n        name: Zabbix\n        version: 7.0-1",
    "template: 'Template One'\n      name: 'Template One'\n      vendor:\n        name: Zabbix\n        version: 7.0-2",
)

with tempfile.TemporaryDirectory() as tmp:
    repo = Path(tmp)
    run(repo, "init")
    run(repo, "config", "user.name", "ZTUM Test")
    run(repo, "config", "user.email", "ztum@example.invalid")

    template_dir = repo / "templates" / "test"
    template_dir.mkdir(parents=True)
    template_path = template_dir / "template_test.yaml"
    template_path.write_text(TEMPLATE_V1, encoding="utf-8")
    first_commit = commit(repo, "initial versions", "2026-01-10T12:00:00+00:00")

    template_path.write_text(TEMPLATE_V2, encoding="utf-8")
    second_commit = commit(repo, "bump template one", "2026-02-03T15:30:00+00:00")

    index = builder.build_index(
        source_dir=repo,
        line="7.0",
        source_ref="release/7.0",
        source_commit=second_commit,
        source_commit_date="2026-02-03T15:30:00+00:00",
    )
    index_path = repo / "index.json"
    index_path.write_text(json.dumps(index), encoding="utf-8")

    enriched = enricher.enrich(index_path=index_path, source_dir=repo)

    one = enriched["templates"]["11111111111111111111111111111111"]
    two = enriched["templates"]["22222222222222222222222222222222"]

    assert one["version_commit"] == second_commit
    assert one["version_date"].startswith("2026-02-03T15:30:00")
    assert two["version_commit"] == first_commit
    assert two["version_date"].startswith("2026-01-10T12:00:00")

    previous = json.loads(index_path.read_text(encoding="utf-8"))
    previous_path = repo / "previous.json"
    previous_path.write_text(json.dumps(previous), encoding="utf-8")

    # Unchanged vendor versions must reuse previous metadata instead of requiring
    # another historical lookup.
    enriched_again = enricher.enrich(
        index_path=index_path,
        source_dir=repo,
        previous_path=previous_path,
    )
    assert enriched_again["templates"]["11111111111111111111111111111111"]["version_commit"] == second_commit
    assert enriched_again["templates"]["22222222222222222222222222222222"]["version_commit"] == first_commit

print("upstream version metadata tests passed.")
