#!/usr/bin/env python3
"""Read-only Debian Zabbix package provenance inventory.

Emits only package names and Debian version strings. Package build versions are
not proof of the source commit of an imported Zabbix template.
"""
import argparse
import json
import re
import subprocess
import sys

PACKAGE = re.compile(r"^zabbix(?:-[a-z0-9+.-]+)?$")
VERSION = re.compile(r"^[A-Za-z0-9:.+~_\-]+$")

def collect(query=None):
    if query is None:
        query = lambda: subprocess.run(
            ["dpkg-query", "-W", "-f=${binary:Package}\t${Version}\t${Status}\n", "zabbix*"],
            capture_output=True, text=True, check=False, timeout=10)
    result = query()
    if result.returncode not in (0, 1):
        raise RuntimeError("dpkg-query returned an error")
    packages = []
    for line in result.stdout.splitlines():
        columns = line.split("\t")
        if len(columns) != 3 or columns[2] != "install ok installed":
            continue
        name, version = columns[:2]
        if PACKAGE.fullmatch(name) and VERSION.fullmatch(version):
            packages.append({"package": name, "version": version})
    packages.sort(key=lambda item: item["package"])
    return {
        "schema": 1,
        "platform": "debian",
        "installed_zabbix_packages": packages,
        "template_source_commit_verified": False,
        "historical_baseline_authoritative": False
    }

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()
    try:
        result = collect()
    except (OSError, RuntimeError, subprocess.TimeoutExpired):
        print("Unable to inspect installed Zabbix packages.", file=sys.stderr)
        return 2
    if args.json:
        print(json.dumps(result, indent=2))
    else:
        for entry in result["installed_zabbix_packages"]:
            print(entry["package"] + "\t" + entry["version"])
        print("Source commit and historical template baseline are NOT verified.")
    return 0

if __name__ == "__main__":
    sys.exit(main())
