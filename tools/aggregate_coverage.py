#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--repo-root", type=Path, default=Path("."))
    parser.add_argument("--coverage-dir", type=Path, required=True)
    parser.add_argument("--output", type=Path, default=Path("coverage-summary.md"))
    args = parser.parse_args()

    root = args.repo_root.resolve()
    merged: dict[Path, dict[int, int]] = {}

    for record in sorted(args.coverage_dir.resolve().glob("*.json")):
        data = json.loads(record.read_text(encoding="utf-8"))
        for filename, lines in data.items():
            path = Path(filename).resolve()
            try:
                relative = path.relative_to(root)
            except ValueError:
                continue
            if not (relative.parts and relative.parts[0] in {"src", "actions"}):
                continue
            target = merged.setdefault(relative, {})
            for line, status in lines.items():
                line_no = int(line)
                status = int(status)
                previous = target.get(line_no)
                if previous is None or status > previous:
                    target[line_no] = status

    runtime_files = sorted(list((root / "src").rglob("*.php")) + list((root / "actions").rglob("*.php")))
    executable = sum(len(lines) for lines in merged.values())
    covered = sum(1 for lines in merged.values() for status in lines.values() if status > 0)
    line_percent = (covered / executable * 100.0) if executable else 0.0
    file_percent = (len(merged) / len(runtime_files) * 100.0) if runtime_files else 0.0

    markdown = f"""# PHP test coverage metrics

- Runtime PHP files in scope (src/ + actions/): **{len(runtime_files)}**
- Files observed by Xdebug during unit/contract tests: **{len(merged)}** ({file_percent:.1f}%)
- Executable lines observed by Xdebug: **{executable}**
- Observed executable lines executed: **{covered}** ({line_percent:.1f}%)

> The line percentage applies only to files loaded by the current standalone test suite.
> File reachability is reported separately so unloaded runtime files cannot be mistaken for covered code.
> View rendering remains covered primarily by contract/native-UI checks and real browser/field validation.
"""
    args.output.write_text(markdown, encoding="utf-8")
    print(markdown)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
