import copy
import subprocess
import tempfile
import unittest
from pathlib import Path

from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

from tools.build_historical_candidate_diagnostic import build_report
from tools.sign_historical_diagnostic import sign
from tools.verify_signed_historical_diagnostic import verify_signed_diagnostic

UUID = "f8f7908280354f2abeed07dc788c3747"
PATH = "templates/os/linux/template_os_linux.yaml"


def git(repo, *args):
    return subprocess.check_output(["git", "-C", str(repo), *args], text=True).strip()


class VerifySignedHistoricalDiagnosticTest(unittest.TestCase):
    def test_signature_and_object_checks_are_both_mandatory(self):
        with tempfile.TemporaryDirectory() as temporary:
            repo = Path(temporary)
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
            git(repo, "commit", "-qm", "canonical")
            report = build_report(repo, git(repo, "rev-parse", "HEAD"), PATH, UUID)
            key = Ed25519PrivateKey.generate()
            private = key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption())
            public = key.public_key().public_bytes(serialization.Encoding.PEM, serialization.PublicFormat.SubjectPublicKeyInfo)
            signed = sign(report, private, "test-only")
            self.assertEqual([], verify_signed_diagnostic(report, signed, public, "test-only", repo))
            # Re-sign corrupted source metadata: signature alone must NOT suffice.
            corrupt = copy.deepcopy(report)
            corrupt["candidates"][0]["raw_sha256"] = "0" * 64
            signed_corrupt = sign(corrupt, private, "test-only")
            self.assertTrue(any("fingerprint mismatch" in error for error in
                                verify_signed_diagnostic(corrupt, signed_corrupt, public, "test-only", repo)))
            # Correctly signed evidence may still be incomplete; it must
            # remain diagnostic, not an authorization for template imports.
            incomplete = copy.deepcopy(report)
            incomplete["truncated"] = True
            signed_incomplete = sign(incomplete, private, "test-only")
            self.assertEqual([], verify_signed_diagnostic(
                incomplete, signed_incomplete, public, "test-only", repo
            ))
            self.assertFalse(incomplete["history_complete"])
            self.assertFalse(incomplete["authoritative"])

            corrupted_schema = copy.deepcopy(report)
            corrupted_schema["missing_revisions"] = "ignored"
            signed_schema = sign(corrupted_schema, private, "test-only")
            self.assertTrue(any("evidence list" in error for error in
                                verify_signed_diagnostic(corrupted_schema, signed_schema, public, "test-only", repo)))
            unsigned_change = copy.deepcopy(report)
            unsigned_change["candidates"][0]["vendor_version"] = "7.0-1"
            self.assertTrue(any("signature" in error.lower() for error in
                                verify_signed_diagnostic(unsigned_change, signed, public, "test-only", repo)))


if __name__ == "__main__":
    unittest.main()
