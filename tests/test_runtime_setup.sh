#!/usr/bin/env bash
set -euo pipefail

# Installer owns private runtime lifecycle; no second setup script.
bash -n install.sh
bash install.sh --help | grep -q -- '--runtime-check'
grep -q 'prepare_runtime' install.sh
grep -q 'check_runtime' install.sh
grep -q '"$RUNTIME_BASE/cache"' install.sh
if grep -q 'tools/ztum-runtime-setup.sh' install.sh; then
  echo 'Installer must not depend on a separate runtime helper' >&2
  exit 1
fi
echo 'Installer runtime integration contract passed.'
