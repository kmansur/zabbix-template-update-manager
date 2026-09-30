#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path


def run(*args: str, cwd: Path | None = None) -> subprocess.CompletedProcess:
    return subprocess.run(args, cwd=cwd, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    tool = root / "tools" / "build_offline_bundle.py"

    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        repo = tmp / "zabbix"
        repo.mkdir()
        run("git", "init", "-q", cwd=repo)
        run("git", "config", "user.email", "ztum-test@example.invalid", cwd=repo)
        run("git", "config", "user.name", "ZTUM Test", cwd=repo)

        source_path = Path("templates/os/linux/template_os_linux.yaml")
        target = repo / source_path
        target.parent.mkdir(parents=True)
        target.write_text("zabbix_export:\n  version: '7.0'\n", encoding="utf-8")
        run("git", "add", ".", cwd=repo)
        run("git", "commit", "-qm", "initial", cwd=repo)
        commit = run("git", "rev-parse", "HEAD", cwd=repo).stdout.decode().strip()
        raw = target.read_bytes()
        sha = hashlib.sha256(raw).hexdigest()

        uuid = "f8f7908280354f2abeed07dc788c3747"
        index = {
            "schema_version": 1,
            "source": {"line": "7.0", "ref": "release/7.0", "commit": commit},
            "templates": {
                uuid: {
                    "uuid": uuid,
                    "name": "Linux by Zabbix agent",
                    "technical_name": "Linux by Zabbix agent",
                    "vendor_name": "Zabbix",
                    "vendor_version": "7.0-1",
                    "paths": [source_path.as_posix()],
                    "content_sha256s": ["b" * 64],
                    "sources": [{"path": source_path.as_posix(), "sha256": sha}],
                }
            },
        }
        index_file = tmp / "7.0.json"
        index_file.write_text(json.dumps(index), encoding="utf-8")
        output = tmp / "bundle"

        run(
            sys.executable,
            str(tool),
            "--zabbix-repo",
            str(repo),
            "--index",
            str(index_file),
            "--output",
            str(output),
        )

        manifest = json.loads((output / "manifest.json").read_text())
        assert manifest["schema_version"] == 1
        assert f"indexes/7.0.json" in manifest["files"]
        assert f"sources/{commit}/{source_path.as_posix()}" in manifest["files"]
        history_key = hashlib.sha256(source_path.as_posix().encode()).hexdigest()
        history_file = output / "history" / commit / f"{history_key}.json"
        assert history_file.is_file()
        history = json.loads(history_file.read_text())
        assert history["commits"][0]["id"] == commit

    print("Offline bundle generator tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
