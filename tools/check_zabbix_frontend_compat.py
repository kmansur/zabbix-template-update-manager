#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
from pathlib import Path

RUNTIME_DIRS = ("actions", "views", "src")
EXTRA_RUNTIME_FILES = ("Module.php",)

CONST_RE = re.compile(r"\b(?:ZBX_[A-Z0-9_]+|USER_TYPE_[A-Z0-9_]+|PROFILE_TYPE_[A-Z0-9_]+|CSRF_TOKEN_NAME)\b")
CLASS_PATTERNS = (
    re.compile(r"\bnew\s+(C[A-Z][A-Za-z0-9_]*)\b"),
    re.compile(r"^use\s+(C[A-Z][A-Za-z0-9_]*)\s*;", re.M),
    re.compile(r"\b(C[A-Z][A-Za-z0-9_]*)::"),
)
DEFINE_RE = re.compile(r"\bdefine\(\s*['\"]([A-Z][A-Z0-9_]*)['\"]")
CONST_DECL_RE = re.compile(r"\bconst\s+([A-Z][A-Z0-9_]*)\s*=")
CLASS_DECL_RE = re.compile(r"\b(?:abstract\s+|final\s+)?class\s+(C[A-Z][A-Za-z0-9_]*)\b")
INTERFACE_DECL_RE = re.compile(r"\binterface\s+(C[A-Z][A-Za-z0-9_]*)\b")
TRAIT_DECL_RE = re.compile(r"\btrait\s+(C[A-Z][A-Za-z0-9_]*)\b")


def php_files(root: Path):
    for rel in EXTRA_RUNTIME_FILES:
        path = root / rel
        if path.is_file():
            yield path
    for rel in RUNTIME_DIRS:
        directory = root / rel
        if not directory.is_dir():
            continue
        yield from directory.rglob("*.php")


def module_requirements(root: Path):
    constants: dict[str, set[str]] = {}
    classes: dict[str, set[str]] = {}

    for path in php_files(root):
        text = path.read_text(encoding="utf-8", errors="replace")
        rel = str(path.relative_to(root))

        for line in text.splitlines():
            for name in CONST_RE.findall(line):
                guarded = (
                    f"defined('{name}')" in line
                    or f'defined("{name}")' in line
                )
                if not guarded:
                    constants.setdefault(name, set()).add(rel)

        for pattern in CLASS_PATTERNS:
            for name in pattern.findall(text):
                classes.setdefault(name, set()).add(rel)

    return constants, classes


def zabbix_symbols(ui_root: Path):
    constants: set[str] = set()
    classes: set[str] = set()

    for path in ui_root.rglob("*.php"):
        text = path.read_text(encoding="utf-8", errors="replace")
        constants.update(DEFINE_RE.findall(text))
        constants.update(CONST_DECL_RE.findall(text))
        classes.update(CLASS_DECL_RE.findall(text))
        classes.update(INTERFACE_DECL_RE.findall(text))
        classes.update(TRAIT_DECL_RE.findall(text))

    return constants, classes


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Check ZTUM native frontend symbols against one Zabbix source tree."
    )
    parser.add_argument("--module-root", default=".")
    parser.add_argument("--zabbix-root", required=True)
    parser.add_argument("--label", default="Zabbix")
    args = parser.parse_args()

    module_root = Path(args.module_root).resolve()
    zabbix_root = Path(args.zabbix_root).resolve()
    ui_root = zabbix_root / "ui"

    if not ui_root.is_dir():
        raise SystemExit(f"{args.label}: missing Zabbix UI tree: {ui_root}")

    required_constants, required_classes = module_requirements(module_root)
    available_constants, available_classes = zabbix_symbols(ui_root)

    missing_constants = {
        name: sorted(files)
        for name, files in required_constants.items()
        if name not in available_constants
    }
    missing_classes = {
        name: sorted(files)
        for name, files in required_classes.items()
        if name not in available_classes
    }

    if missing_constants or missing_classes:
        print(f"{args.label}: frontend compatibility check FAILED")
        for name, files in sorted(missing_constants.items()):
            print(f"  missing constant {name}: {', '.join(files)}")
        for name, files in sorted(missing_classes.items()):
            print(f"  missing class {name}: {', '.join(files)}")
        return 1

    print(
        f"{args.label}: frontend compatibility check passed "
        f"({len(required_constants)} constants, {len(required_classes)} native classes)."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
