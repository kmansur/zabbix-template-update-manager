#!/usr/bin/env python3
from __future__ import annotations

import subprocess
import tempfile
from pathlib import Path


def write(path: Path, content: str):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def run(checker: Path, module: Path, zabbix: Path):
    return subprocess.run(
        [
            "python3", str(checker),
            "--module-root", str(module),
            "--zabbix-root", str(zabbix),
            "--label", "fixture",
        ],
        text=True,
        capture_output=True,
        check=False,
    )


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    checker = root / "tools" / "check_zabbix_frontend_compat.py"

    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        module = tmp / "module"
        zabbix = tmp / "zabbix"

        write(
            module / "views" / "test.php",
            "<?php\nuse CFilter;\nnew CFilter();\necho ZBX_STYLE_GREEN;\n",
        )
        write(
            zabbix / "ui" / "include" / "defines.inc.php",
            "<?php\ndefine('ZBX_STYLE_GREEN', 'green');\n",
        )
        write(
            zabbix / "ui" / "include" / "classes" / "html" / "CFilter.php",
            "<?php\nclass CFilter {}\n",
        )

        ok = run(checker, module, zabbix)
        assert ok.returncode == 0, ok.stdout + ok.stderr

        write(
            module / "views" / "test.php",
            "<?php\nuse CFilter;\nnew CFilter();\necho ZBX_STYLE_REMOVED;\n",
        )
        bad = run(checker, module, zabbix)
        assert bad.returncode == 1, bad.stdout + bad.stderr
        assert "missing constant ZBX_STYLE_REMOVED" in bad.stdout

    print("Zabbix frontend compatibility checker tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
