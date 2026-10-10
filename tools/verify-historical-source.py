#!/usr/bin/env python3
"""Read-only independent source verification for a pinned Zabbix Git revision.

No Zabbix API calls, imports, credentials or local-cache reads are performed.
Exit codes: 0 identical, 1 mismatch, 2 input/network error.
"""
import argparse
import hashlib
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request

def verify(commit: str, path: str, expected: str, opener=None) -> tuple[bool, str]:
    if not re.fullmatch(r"[a-f0-9]{40}", commit):
        raise ValueError("Invalid full Git commit hash")
    if not re.fullmatch(r"templates/[A-Za-z0-9_./-]+\.ya?ml", path) or ".." in path.split("/"):
        raise ValueError("Invalid template path")
    if not re.fullmatch(r"[a-f0-9]{64}", expected):
        raise ValueError("Invalid expected SHA-256")
    encoded = "/".join(urllib.parse.quote(segment, safe="") for segment in path.split("/"))
    url = "https://git.zabbix.com/projects/ZBX/repos/zabbix/raw/" + encoded + "?at=" + commit
    request = urllib.request.Request(url, headers={"User-Agent": "ZTUM-readonly-verify/1"})
    if opener is None:
        opener = urllib.request.urlopen
    with opener(request, timeout=15) as reply:
        final = urllib.parse.urlparse(reply.geturl())
        if final.scheme != "https" or final.hostname != "git.zabbix.com":
            raise ValueError("Unexpected redirect origin")
        data = reply.read(10 * 1024 * 1024 + 1)
    if len(data) > 10 * 1024 * 1024 or not data:
        raise ValueError("Source is empty or exceeds limit")
    digest = hashlib.sha256(data).hexdigest()
    return digest == expected, digest

def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--commit", required=True)
    parser.add_argument("--path", required=True)
    parser.add_argument("--sha256", required=True)
    args = parser.parse_args()
    try:
        match, digest = verify(args.commit, args.path, args.sha256)
    except (ValueError, OSError, urllib.error.URLError) as error:
        print("ERROR: " + str(error), file=sys.stderr)
        return 2
    print("Official source SHA-256:", digest)
    print("Expected cached SHA-256:", args.sha256)
    print("MATCH" if match else "MISMATCH")
    return 0 if match else 1

if __name__ == "__main__":
    sys.exit(main())
