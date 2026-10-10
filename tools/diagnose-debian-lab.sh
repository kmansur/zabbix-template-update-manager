#!/bin/sh
# ZTUM Debian laboratory diagnostics. Read-only; no credentials or configs dumped.
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
echo "ZTUM laboratory diagnostics"
echo "Date UTC: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
if [ -r /etc/os-release ]; then
  . /etc/os-release
  printf 'OS: %s\n' "${PRETTY_NAME:-unknown}"
fi
printf 'Kernel: %s\n' "$(uname -r)"
if command -v php >/dev/null 2>&1; then
  printf 'PHP CLI: %s\n' "$(php -r 'echo PHP_VERSION;')"
  for ext in json mbstring curl xml dom libxml openssl; do
    if php -m | grep -ixq "$ext"; then
      printf 'PHP %-10s OK\n' "$ext"
    else
      printf 'PHP %-10s MISSING (CLI)\n' "$ext"
    fi
  done
else
  echo 'PHP CLI: MISSING'
fi
for binary in git nginx apache2 zabbix_server; do
  if command -v "$binary" >/dev/null 2>&1; then
    if [ "$binary" = zabbix_server ]; then
      printf 'Zabbix server: %s\n' "$(zabbix_server -V 2>/dev/null | head -n 1)"
    else
      printf '%s: installed\n' "$binary"
    fi
  fi
done
if command -v git >/dev/null 2>&1 && git -C "$root" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  printf 'ZTUM commit: %s\n' "$(git -C "$root" rev-parse --short HEAD)"
fi
if [ -r "$root/manifest.json" ] && command -v php >/dev/null 2>&1; then
  php -r '$m=json_decode(file_get_contents($argv[1]),true); printf("ZTUM version: %s\n", (string)($m["version"] ?? "unknown"));' "$root/manifest.json"
fi
echo "Lab checks complete; no configuration or Zabbix data were changed."
