import json
import subprocess
import tempfile
import unittest
from pathlib import Path

from tools.build_historical_candidate_diagnostic import build_report

UUID = "f8f7908280354f2abeed07dc788c3747"
PATH = "templates/os/linux/template_os_linux.yaml"


def run(repo, *args):
    return subprocess.check_output(["git", "-C", str(repo), *args], text=True).strip()


class HistoricalCandidateDiagnosticTest(unittest.TestCase):
    def test_first_parent_diagnostic_is_never_authoritative(self):
        with tempfile.TemporaryDirectory() as temporary:
            repo = Path(temporary)
            run(repo, "init", "-q")
            run(repo, "config", "user.name", "Test")
            run(repo, "config", "user.email", "test@example.org")
            path = repo / PATH
            path.parent.mkdir(parents=True)
            def write(version, item):
                path.write_text(
                    "zabbix_export:\n  version: '7.0'\n  templates:\n"
                    f"    - uuid: {UUID}\n      vendor:\n        name: Zabbix\n"
                    f"        version: {version}\n      description: {item}\n"
                )
                run(repo, "add", PATH)
                run(repo, "commit", "-qm", item)
            write("7.0-0", "one")
            write("7.0-0", "two")
            write("7.0-1", "three")
            commit = run(repo, "rev-parse", "HEAD")
            report = build_report(repo, commit, PATH, UUID)
            self.assertFalse(report["authoritative"])
            self.assertEqual("diagnostic-only", report["purpose"])
            self.assertFalse(report["truncated"])
            self.assertEqual(["7.0-1", "7.0-0", "7.0-0"],
                             [x["vendor_version"] for x in report["candidates"]])
            self.assertEqual(3, len({x["raw_sha256"] for x in report["candidates"]}))
            report_short = build_report(repo, commit, PATH, UUID, 1)
            self.assertTrue(report_short["truncated"])

    def test_rejects_bad_commit_and_path(self):
        with tempfile.TemporaryDirectory() as temporary:
            for commit, path in [("deadbeef", PATH), ("a" * 40, "../bad.yaml"),
                                 ("a" * 40, "templates/../secret.yaml")]:
                with self.assertRaises(ValueError):
                    build_report(Path(temporary), commit, path, UUID)


if __name__ == "__main__":
    unittest.main()
