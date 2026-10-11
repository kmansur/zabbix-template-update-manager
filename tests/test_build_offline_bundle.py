#!/usr/bin/env python3
from __future__ import annotations

import hashlib
import json
import subprocess
import sys
import tempfile
import base64
from pathlib import Path

from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey


def run(*args: str, cwd: Path | None = None) -> subprocess.CompletedProcess:
    result = subprocess.run(args, cwd=cwd, check=False, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.decode("utf-8", "replace"))
    return result


def main() -> int:
    root = Path(__file__).resolve().parents[1]
    tool = root / "tools" / "build_offline_bundle.py"
    verifier = root / "tools" / "verify_offline_bundle.py"

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
        target.write_bytes(b"zabbix_export:\n  version: '7.0'\n")
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
        signing_key = Ed25519PrivateKey.generate()
        signing_key_path = tmp / "signing-key.pem"
        signing_key_path.write_bytes(signing_key.private_bytes(
            serialization.Encoding.PEM,
            serialization.PrivateFormat.PKCS8,
            serialization.NoEncryption(),
        ))

        run(
            sys.executable,
            str(tool),
            "--zabbix-repo",
            str(repo),
            "--index",
            str(index_file),
            "--output",
            str(output),
            "--signing-private-key",
            str(signing_key_path),
            "--key-id",
            "test-key",
        )

        manifest = json.loads((output / "manifest.json").read_text())
        assert manifest["schema_version"] == 1
        assert manifest["signature"]["schema"] == "ztum-offline-manifest-signature-v1"
        assert manifest["signature"]["key_id"] == "test-key"
        assert len(manifest["signature"]["signature"]) == 88
        unsigned = {key: value for key, value in manifest.items() if key != "signature"}
        signing_key.public_key().verify(
            base64.b64decode(manifest["signature"]["signature"]),
            (json.dumps(unsigned, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n").encode(),
        )
        assert f"indexes/7.0.json" in manifest["files"]
        assert f"sources/{commit}/{source_path.as_posix()}" in manifest["files"]
        history_key = hashlib.sha256(source_path.as_posix().encode()).hexdigest()
        history_file = output / "history" / commit / f"{history_key}.json"
        assert history_file.is_file()
        history = json.loads(history_file.read_text())
        assert history["commits"][0]["id"] == commit

        public_key_path = tmp / "trusted-public-key.pem"
        public_key_path.write_bytes(signing_key.public_key().public_bytes(
            serialization.Encoding.PEM,
            serialization.PublicFormat.SubjectPublicKeyInfo,
        ))
        run(sys.executable, str(verifier), "--bundle", str(output), "--public-key", str(public_key_path), "--key-id", "test-key")

        source_file = output / "sources" / commit / source_path
        source_file.write_bytes(source_file.read_bytes() + b"tampered\n")
        tampered = subprocess.run(
            [sys.executable, str(verifier), "--bundle", str(output), "--public-key", str(public_key_path), "--key-id", "test-key"],
            check=False,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
        assert tampered.returncode != 0

        renamed_path = Path("templates/os/linux/template_os_linux_renamed.yaml")
        run("git", "mv", str(source_path), str(renamed_path), cwd=repo)
        run("git", "commit", "-qm", "rename", cwd=repo)
        renamed_commit = run("git", "rev-parse", "HEAD", cwd=repo).stdout.decode().strip()
        renamed_raw = (repo / renamed_path).read_bytes()
        renamed_index = dict(index)
        renamed_index["source"] = dict(index["source"], commit=renamed_commit)
        renamed_template = dict(index["templates"][uuid], paths=[renamed_path.as_posix()],
                                sources=[{"path": renamed_path.as_posix(),
                                          "sha256": hashlib.sha256(renamed_raw).hexdigest()}])
        renamed_index["templates"] = {uuid: renamed_template}
        renamed_index_file = tmp / "7.0-renamed.json"
        renamed_index_file.write_text(json.dumps(renamed_index), encoding="utf-8")
        renamed_output = tmp / "renamed-bundle"
        run(sys.executable, str(tool), "--zabbix-repo", str(repo), "--index", str(renamed_index_file),
            "--output", str(renamed_output), "--signing-private-key", str(signing_key_path), "--key-id", "test-key")
        rename_key = renamed_output / "renames" / renamed_commit / f"{hashlib.sha256(renamed_path.as_posix().encode()).hexdigest()}.json"
        assert rename_key.is_file()
        rename_record = json.loads(rename_key.read_text())
        assert rename_record["previous_path"] == source_path.as_posix()

        incomplete_output = tmp / "incomplete-bundle"
        incomplete = subprocess.run(
            [
                sys.executable,
                str(tool),
                "--zabbix-repo",
                str(repo),
                "--index",
                str(renamed_index_file),
                "--output",
                str(incomplete_output),
                "--signing-private-key",
                str(signing_key_path),
                "--key-id",
                "test-key",
                "--history-limit",
                "1",
                "--require-complete-history",
            ],
            check=False,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
        assert incomplete.returncode != 0
        assert b"exceeds --history-limit" in incomplete.stderr

    print("Offline bundle generator tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
