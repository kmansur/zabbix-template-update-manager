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
            for field, value in (
                ("missing_revisions", [{}]), ("invalid_revisions", [[]]),
                ("candidates", [None]),
                ("path", "templates/../outside.yaml"),
                ("path", "templates/bad\x00.yaml"),
                ("path", "templates/bad\\path.yaml"),
            ):
                with self.subTest(field=field, value=value):
                    malformed = copy.deepcopy(report)
                    malformed[field] = value
                    self.assertTrue(audit(malformed, repo, 2))
            for field in ("commit", "raw_sha256"):
                malformed = copy.deepcopy(report)
                malformed["candidates"][0][field] = {}
                self.assertTrue(audit(malformed, repo, 2))
            self.assertTrue(audit([], repo, 2))
            # A truncated or rewritten candidate inventory cannot hide a
            # distinct source file even if its topological counts still match.
            changed = copy.deepcopy(report)
            changed["candidates"] = changed["candidates"][:1]
            changed["candidate_count"] = len(changed["candidates"])
            self.assertTrue(any("Distinct historical YAML contents" in e for e in
                                audit(changed, repo, 2)))
            changed = copy.deepcopy(report)
            changed["scanned_commits"] = 3
            self.assertTrue(audit(changed, repo, 2))
            changed = copy.deepcopy(report)
            changed["distinct_vendor_versions"] = ["forged"]
            self.assertTrue(any("vendor-version inventory mismatch" in e for e in audit(changed, repo, 2)))
            changed = copy.deepcopy(report)
            changed["duplicate_vendor_versions"] = ["forged"]
            self.assertTrue(any("vendor-version inventory mismatch" in e for e in audit(changed, repo, 2)))
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
            withheld = changed["candidates"].pop()
            changed["candidate_count"] = len(changed["candidates"])
            changed["invalid_revisions"] = [withheld["commit"]]
            self.assertTrue(any("marked invalid contains a valid" in e for e in
                                audit(changed, repo, 2)))
            changed = copy.deepcopy(report)
            changed["shallow_repository"] = True
            self.assertTrue(audit(changed, repo, 2))


if __name__ == "__main__":
    unittest.main()
