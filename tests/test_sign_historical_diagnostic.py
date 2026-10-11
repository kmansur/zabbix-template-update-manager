import copy
import unittest

from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey

from tools.sign_historical_diagnostic import sign, verify


class SignatureDiagnosticTest(unittest.TestCase):
    def test_tampering_and_key_substitution_fail_closed(self):
        key = Ed25519PrivateKey.generate()
        other = Ed25519PrivateKey.generate()
        private = key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption())
        public = key.public_key().public_bytes(serialization.Encoding.PEM, serialization.PublicFormat.SubjectPublicKeyInfo)
        wrong_public = other.public_key().public_bytes(serialization.Encoding.PEM, serialization.PublicFormat.SubjectPublicKeyInfo)
        report = {"schema_version": 1, "purpose": "diagnostic-only",
                  "authoritative": False, "history_complete": False,
                  "source_commit": "a" * 40, "candidates": [{"commit": "b" * 40, "vendor_version": "7.0-0"}]}
        envelope = sign(report, private, "release-test-key")
        self.assertTrue(verify(report, envelope, public, "release-test-key"))
        self.assertFalse(verify(report, envelope, wrong_public, "release-test-key"))
        self.assertFalse(verify(report, envelope, public, "another-key"))
        changed = copy.deepcopy(report)
        changed["candidates"][0]["vendor_version"] = "7.0-1"
        self.assertFalse(verify(changed, envelope, public, "release-test-key"))
        changed = copy.deepcopy(report)
        changed["history_complete"] = True
        self.assertFalse(verify(changed, envelope, public, "release-test-key"))
        bad = copy.deepcopy(envelope)
        bad["signature"] = "A" * 88
        self.assertFalse(verify(report, bad, public, "release-test-key"))
        bad = copy.deepcopy(envelope)
        bad["source"] = "external"
        self.assertFalse(verify(report, bad, public, "release-test-key"))


if __name__ == "__main__":
    unittest.main()
