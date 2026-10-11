import copy
import subprocess
import tempfile
import unittest
from pathlib import Path

from tools.audit_historical_revision_coverage import audit
from tools.build_historical_candidate_diagnostic import build_report

UUID = "f8f7908280354f2abeed07dc788c3747"
PATH = "templates/os/linux/template_os_linux.yaml"


def git(repo, *args):
    return subprocess.check_output(["git", "-C", str(repo), *args], text=True).strip()


class CoverageAuditTest(unittest.TestCase):
    def test_detects_missing_window_and_forged_flags(self):
        with tempfile.TemporaryDirectory() as tmp:
            repo = Path(tmp)
            git(repo, "init", "-q")
            git(repo, "config", "user.name", "Test")
            git(repo, "config", "user.email", "test@example.org")
            target = repo / PATH
            target.parent.mkdir(parents=True)
            for description in ("first", "second", "third"):
                target.write_text(
                    "zabbix_export:\n  templates:\n"
                    f"    - uuid: {UUID}\n      vendor:\n"
                    f"        name: Zabbix\n        version: 7.0-0\n"
                    f"      description: {description}\n"
                )
                git(repo, "add", ".")
                git(repo, "commit", "-qm", description)
            report = build_report(repo, git(repo, "rev-parse", "HEAD"), PATH, UUID, 2)
            self.assertEqual([], audit(report, repo, 2))
            changed = copy.deepcopy(report)
            changed["scanned_commits"] = 3
            self.assertTrue(audit(changed, repo, 2))
            changed = copy.deepcopy(report)
            changed["truncated"] = False
            self.assertTrue(audit(changed, repo, 2))
            changed = copy.deepcopy(report)
            changed["candidates"][0]["commit"] = "a" * 40
            self.assertTrue(audit(changed, repo, 2))
            changed = copy.deepcopy(report)
            changed["missing_revisions"] = [changed["source_commit"]]
            changed["missing_history_path"] = True
            self.assertTrue(any("Missing-path revisions differ" in e for e in audit(changed, repo, 2)))
            changed = copy.deepcopy(report)
            changed["shallow_repository"] = True
            self.assertTrue(audit(changed, repo, 2))


if __name__ == "__main__":
    unittest.main()
