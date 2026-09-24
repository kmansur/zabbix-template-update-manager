#!/usr/bin/env python3
from __future__ import annotations

import json
import subprocess
import tempfile
from pathlib import Path


def run(tool: Path, root: Path, coverage: Path, *extra: str):
    return subprocess.run(
        [
            "python3",
            str(tool),
            "--repo-root",
            str(root),
            "--coverage-dir",
            str(coverage),
            "--output",
            str(root / "summary.md"),
            *extra,
        ],
        text=True,
        capture_output=True,
        check=False,
    )


def main() -> int:
    project = Path(__file__).resolve().parents[1]
    tool = project / "tools" / "aggregate_coverage.py"

    with tempfile.TemporaryDirectory() as tmp:
        root = Path(tmp)
        coverage = root / "coverage"
        coverage.mkdir()
        (root / "src").mkdir()
        (root / "actions").mkdir()

        source = root / "src" / "Example.php"
        action = root / "actions" / "Action.php"
        source.write_text("<?php\n", encoding="utf-8")
        action.write_text("<?php\n", encoding="utf-8")

        record = {
            str(source.resolve()): {"10": 1, "11": -1, "12": 1},
        }
        (coverage / "one.json").write_text(json.dumps(record), encoding="utf-8")

        ok = run(
            tool,
            root,
            coverage,
            "--min-file-percent",
            "50",
            "--min-line-percent",
            "60",
        )
        assert ok.returncode == 0, ok.stdout + ok.stderr
        assert "50.0%" in ok.stdout
        assert "66.7%" in ok.stdout

        fail_file = run(
            tool,
            root,
            coverage,
            "--min-file-percent",
            "51",
        )
        assert fail_file.returncode == 1
        assert "Runtime file reachability" in fail_file.stdout

        fail_line = run(
            tool,
            root,
            coverage,
            "--min-line-percent",
            "67",
        )
        assert fail_line.returncode == 1
        assert "executable-line coverage" in fail_line.stdout

    print("Coverage aggregation gate tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
