import copy
import subprocess
import tempfile
import unittest
from pathlib import Path

from tools.build_historical_candidate_diagnostic import build_report
from tools.verify_historical_candidate_diagnostic import verify

UUID = "f8f7908280354f2abeed07dc788c3747"
PATH = "templates/os/linux/template_os_linux.yaml"


def git(repo, *args):
    return subprocess.check_output(["git", "-C", str(repo), *args], text=True).strip()


class VerifyHistoricalDiagnosticTest(unittest.TestCase):
    def test_valid_and_tampered_sources(self):
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp)
            git(repo, "init", "-q")
            git(repo, "config", "user.name", "Test")
            git(repo, "config", "user.email", "test@example.org")
            target = repo / PATH
            target.parent.mkdir(parents=True)
            target.write_text(
                "zabbix_export:\n  templates:\n"
                f"    - uuid: {UUID}\n      vendor:\n"
                "        name: Zabbix\n        version: 7.0-0\n"
            )
            git(repo, "add", ".")
            git(repo, "commit", "-qm", "source")
            report = build_report(repo, git(repo, "rev-parse", "HEAD"), PATH, UUID)
            self.assertEqual([], verify(report, repo))
            self.assertTrue(verify([], repo))
            for path in (None, "templates/../outside.yaml", "templates/bad\x00.yaml",
                         "templates/bad\\path.yaml"):
                with self.subTest(path=path):
                    malformed = copy.deepcopy(report)
                    malformed["path"] = path
                    malformed["candidates"][0]["path"] = path
                    self.assertTrue(verify(malformed, repo))

            corrupted = copy.deepcopy(report)
            corrupted["candidates"][0]["raw_sha256"] = "0" * 64
            self.assertTrue(any("fingerprint mismatch" in e for e in verify(corrupted, repo)))

            promoted = copy.deepcopy(report)
            promoted["authoritative"] = True
            self.assertTrue(any("deny authority" in e for e in verify(promoted, repo)))

            promoted = copy.deepcopy(report)
            promoted["history_complete"] = True
            self.assertTrue(verify(promoted, repo))


if __name__ == "__main__":
    unittest.main()
