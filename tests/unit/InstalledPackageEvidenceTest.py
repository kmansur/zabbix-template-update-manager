#!/usr/bin/env python3
"""Offline tests for installed Zabbix package evidence collection."""
import importlib.util
from pathlib import Path
from types import SimpleNamespace

path = Path(__file__).resolve().parents[2] / "tools" / "ztum-installed-package-evidence.py"
spec = importlib.util.spec_from_file_location("ztum_installed_evidence", path)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

output = ("zabbix-server-pgsql\t1:8.0.0~rc1-1+debian13\tinstall ok installed\n"
          "zabbix-frontend-php\t1:8.0.0~rc1-1+debian13\tinstall ok installed\n"
          "zabbix-agent\t1:7.0.31-1\tdeinstall ok config-files\n"
          "other-secret-package\t1:1.0\tinstall ok installed\n")
report = module.collect(lambda: SimpleNamespace(returncode=0, stdout=output))
assert len(report["installed_zabbix_packages"]) == 2
assert report["installed_zabbix_packages"][0]["package"] == "zabbix-frontend-php"
assert report["template_source_commit_verified"] is False
assert report["historical_baseline_authoritative"] is False
assert "secret" not in str(report)
print("Installed package evidence tests passed.")
