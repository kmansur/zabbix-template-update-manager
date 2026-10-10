#!/usr/bin/env python3
"""Offline tests for independent pinned-source SHA-256 verification."""
import hashlib
import importlib.util
from pathlib import Path

script = Path(__file__).resolve().parents[2] / "tools" / "verify-historical-source.py"
spec = importlib.util.spec_from_file_location("ztum_verify_source", script)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class Response:
    def __init__(self, body, url):
        self.body = body
        self.url = url
    def __enter__(self):
        return self
    def __exit__(self, *_):
        return False
    def geturl(self):
        return self.url
    def read(self, size):
        return self.body[:size]

def opener(req, timeout):
    assert timeout == 15
    return Response(b"example", req.full_url)

commit = "a" * 40
path = "templates/app/rdap/template_rdap.yaml"
sha = hashlib.sha256(b"example").hexdigest()
assert module.verify(commit, path, sha, opener) == (True, sha)
match, digest = module.verify(commit, path, "0" * 64, opener)
assert match is False and digest == sha
for bad_path in ("../secret.yaml", "templates/../secret.yaml", "https://example.com/a.yaml"):
    try:
        module.verify(commit, bad_path, sha, opener)
    except ValueError:
        pass
    else:
        raise AssertionError("Unsafe path accepted")
def redirect(req, timeout):
    return Response(b"example", "https://example.org/source")
try:
    module.verify(commit, path, sha, redirect)
except ValueError:
    pass
else:
    raise AssertionError("Redirect to untrusted origin accepted")
print("Historical source verification tests passed.")
