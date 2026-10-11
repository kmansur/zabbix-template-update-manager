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
# Invalid combinations must fail before any deployment or runtime mutation.
for args in \
  '--reinstall --check' \
  '--reinstall --upgrade' \
  '--reinstall --runtime-check' \
  '--reinstall --rollback example'; do
  if bash install.sh $args >/dev/null 2>&1; then
    echo "Unexpectedly accepted conflicting options: $args" >&2
    exit 1
  fi
done
echo 'Installer runtime integration contract passed.'
