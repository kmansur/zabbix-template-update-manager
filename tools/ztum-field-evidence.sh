#!/usr/bin/env bash
set -euo pipefail

MODULE_DIR=""
RUNTIME_USER=""
RUNTIME_BASE="/var/lib/zabbix-template-update-manager"
BROWSER="not recorded"
THEME="not recorded"
ARTIFACT=""
EXPECTED_SHA256=""

usage() {
  cat <<'USAGE'
Usage: tools/ztum-field-evidence.sh [options]

Collects a privacy-conscious, read-only Markdown snapshot for ZTUM field validation.
It intentionally excludes IP addresses, database host/name/user/password, secrets,
tokens, configuration contents and log contents.

Options:
  --module-dir DIR       ZTUM module directory (auto-detected when omitted)
  --runtime-user USER    PHP-FPM runtime account (auto-detected when omitted)
  --runtime-base DIR     Runtime root (default: /var/lib/zabbix-template-update-manager)
  --browser TEXT         Browser/version used by the operator
  --theme TEXT           Zabbix theme used by the operator
  --artifact FILE        Optional release archive used for installation
  --expected-sha256 HEX  Optional expected SHA-256 for --artifact
  -h, --help             Show this help
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --module-dir) MODULE_DIR="${2:-}"; shift 2 ;;
    --runtime-user) RUNTIME_USER="${2:-}"; shift 2 ;;
    --runtime-base) RUNTIME_BASE="${2:-}"; shift 2 ;;
    --browser) BROWSER="${2:-}"; shift 2 ;;
    --theme) THEME="${2:-}"; shift 2 ;;
    --artifact) ARTIFACT="${2:-}"; shift 2 ;;
    --expected-sha256) EXPECTED_SHA256="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

[[ "$RUNTIME_BASE" = /* ]] || {
  echo "--runtime-base must be an absolute path." >&2
  exit 2
}
RUNTIME_BASE="${RUNTIME_BASE%/}"

find_module_dir() {
  local candidate
  for candidate in \
    /usr/share/zabbix/modules/zabbix-template-update-manager \
    /usr/local/share/zabbix/modules/zabbix-template-update-manager \
    /var/www/zabbix/modules/zabbix-template-update-manager; do
    if [[ -f "$candidate/manifest.json" ]]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done
  return 1
}

detect_runtime_user() {
  local candidates=()
  local file value
  shopt -s nullglob
  for file in /etc/php/*/fpm/pool.d/*.conf /etc/php-fpm.d/*.conf /usr/local/etc/php-fpm.d/*.conf; do
    while IFS= read -r value; do
      value="${value%%;*}"
      value="${value#*=}"
      value="$(printf '%s' "$value" | xargs)"
      [[ -n "$value" ]] && candidates+=("$value")
    done < <(grep -E '^[[:space:]]*user[[:space:]]*=' "$file" 2>/dev/null || true)
  done
  shopt -u nullglob

  [[ ${#candidates[@]} -gt 0 ]] || return 1
  mapfile -t candidates < <(printf '%s\n' "${candidates[@]}" | sort -u)
  [[ ${#candidates[@]} -eq 1 ]] || return 1
  printf '%s\n' "${candidates[0]}"
}

sha256_file() {
  local file="$1"
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$file" | awk '{print $1}'
  elif command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$file" | awk '{print $1}'
  else
    printf '%s\n' "unavailable"
  fi
}

stat_owner_group_mode() {
  local path="$1"
  if stat -c '%U:%G %a' "$path" >/dev/null 2>&1; then
    stat -c '%U:%G %a' "$path"
  else
    stat -f '%Su:%Sg %Lp' "$path"
  fi
}

frontend_version() {
  local file line value
  shopt -s nullglob
  for file in \
    /usr/share/zabbix/include/defines.inc.php \
    /usr/local/share/zabbix/include/defines.inc.php \
    /var/www/zabbix/include/defines.inc.php; do
    [[ -f "$file" ]] || continue
    line="$(grep -E "define\(['\"]ZABBIX_VERSION['\"]" "$file" 2>/dev/null | head -n1 || true)"
    value="$(printf '%s' "$line" | sed -nE "s/.*ZABBIX_VERSION['\"][[:space:]]*,[[:space:]]*['\"]([^'\"]+)['\"].*/\1/p")"
    if [[ -n "$value" ]]; then
      printf '%s\n' "$value"
      shopt -u nullglob
      return 0
    fi
  done
  shopt -u nullglob
  return 1
}

database_type() {
  local file line value
  shopt -s nullglob
  for file in \
    /etc/zabbix/web/zabbix.conf.php \
    /usr/share/zabbix/conf/zabbix.conf.php \
    /usr/local/share/zabbix/conf/zabbix.conf.php \
    /var/www/zabbix/conf/zabbix.conf.php; do
    [[ -f "$file" ]] || continue
    line="$(grep -E "\[['\"]TYPE['\"]\]" "$file" 2>/dev/null | head -n1 || true)"
    value="$(printf '%s' "$line" | sed -nE "s/.*=[[:space:]]*['\"]([^'\"]+)['\"].*/\1/p")"
    if [[ -n "$value" ]]; then
      printf '%s\n' "$value"
      shopt -u nullglob
      return 0
    fi
  done
  shopt -u nullglob
  return 1
}

if [[ -z "$MODULE_DIR" ]]; then
  if ! MODULE_DIR="$(find_module_dir)"; then
    echo "ZTUM module directory could not be detected. Re-run with --module-dir DIR." >&2
    exit 3
  fi
fi
MODULE_DIR="${MODULE_DIR%/}"
[[ -f "$MODULE_DIR/manifest.json" ]] || {
  echo "manifest.json not found in module directory: $MODULE_DIR" >&2
  exit 3
}

if [[ -z "$RUNTIME_USER" ]]; then
  RUNTIME_USER="$(detect_runtime_user || true)"
fi

manifest_version="$(awk -F'"' '/"version"[[:space:]]*:/ {print $4; exit}' "$MODULE_DIR/manifest.json")"
[[ -n "$manifest_version" ]] || manifest_version="unavailable"
manifest_sha="$(sha256_file "$MODULE_DIR/manifest.json")"

version_file="unavailable"
if [[ -f "$MODULE_DIR/VERSION" ]]; then
  version_file="$(tr -d '\r\n' < "$MODULE_DIR/VERSION")"
fi

zabbix_frontend="$(frontend_version || true)"
[[ -n "$zabbix_frontend" ]] || zabbix_frontend="not detected"

zabbix_server="not detected"
if command -v zabbix_server >/dev/null 2>&1; then
  zabbix_server="$(zabbix_server -V 2>/dev/null | head -n1 | tr -d '\r')"
fi

php_cli="not detected"
if command -v php >/dev/null 2>&1; then
  php_cli="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
  [[ -n "$php_cli" ]] || php_cli="detected, version unavailable"
fi

db_type="$(database_type || true)"
[[ -n "$db_type" ]] || db_type="not detected"

os_name="not detected"
if [[ -r /etc/os-release ]]; then
  os_name="$(awk -F= '$1=="PRETTY_NAME" {gsub(/^"|"$/, "", $2); print $2; exit}' /etc/os-release)"
  [[ -n "$os_name" ]] || os_name="not detected"
fi

runtime_user_status="not detected"
runtime_group="not detected"
if [[ -n "$RUNTIME_USER" ]] && id "$RUNTIME_USER" >/dev/null 2>&1; then
  runtime_group="$(id -gn "$RUNTIME_USER")"
  runtime_user_status="$RUNTIME_USER:$runtime_group"
fi

printf '# ZTUM field evidence\n\n'
printf -- '- Collected at (UTC): `%s`\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
printf -- '- Collector: `tools/ztum-field-evidence.sh`\n'
printf -- '- Privacy mode: `safe metadata only`\n\n'

printf '## Module\n\n'
printf -- '- Module directory: `%s`\n' "$MODULE_DIR"
printf -- '- Manifest version: `%s`\n' "$manifest_version"
printf -- '- VERSION file: `%s`\n' "$version_file"
printf -- '- manifest.json SHA-256: `%s`\n' "$manifest_sha"

if [[ -n "$ARTIFACT" ]]; then
  if [[ ! -f "$ARTIFACT" ]]; then
    printf -- '- Release artifact: `missing: %s`\n' "$(basename "$ARTIFACT")"
  else
    artifact_sha="$(sha256_file "$ARTIFACT")"
    printf -- '- Release artifact: `%s`\n' "$(basename "$ARTIFACT")"
    printf -- '- Release artifact SHA-256: `%s`\n' "$artifact_sha"
    if [[ -n "$EXPECTED_SHA256" ]]; then
      if [[ "$artifact_sha" == "$EXPECTED_SHA256" ]]; then
        printf -- '- Release artifact checksum match: `YES`\n'
      else
        printf -- '- Release artifact checksum match: `NO`\n'
      fi
    fi
  fi
fi

printf '\n## Platform\n\n'
printf -- '- Operating system: `%s`\n' "$os_name"
printf -- '- Kernel: `%s`\n' "$(uname -sr)"
printf -- '- Zabbix frontend version: `%s`\n' "$zabbix_frontend"
printf -- '- Zabbix server version: `%s`\n' "$zabbix_server"
printf -- '- PHP CLI version: `%s`\n' "$php_cli"
printf -- '- Database type: `%s`\n' "$db_type"

printf '\n## Runtime safety metadata\n\n'
printf -- '- PHP-FPM runtime account: `%s`\n' "$runtime_user_status"
printf -- '- Runtime root: `%s`\n' "$RUNTIME_BASE"
for dir in "$RUNTIME_BASE" "$RUNTIME_BASE/backups" "$RUNTIME_BASE/offline" "$RUNTIME_BASE/locks"; do
  if [[ -d "$dir" && ! -L "$dir" ]]; then
    printf -- '- `%s`: `%s`\n' "$dir" "$(stat_owner_group_mode "$dir")"
  elif [[ -L "$dir" ]]; then
    printf -- '- `%s`: `UNSAFE symbolic link`\n' "$dir"
  else
    printf -- '- `%s`: `missing`\n' "$dir"
  fi
done

if [[ -n "$RUNTIME_USER" ]] && id "$RUNTIME_USER" >/dev/null 2>&1; then
  writable="not tested"
  if [[ "$(id -u)" -eq 0 ]] && command -v runuser >/dev/null 2>&1; then
    if runuser -u "$RUNTIME_USER" -- test -w "$RUNTIME_BASE/backups" 2>/dev/null; then
      writable="yes"
    else
      writable="no"
    fi
  elif [[ "$(id -un)" == "$RUNTIME_USER" ]]; then
    [[ -w "$RUNTIME_BASE/backups" ]] && writable="yes" || writable="no"
  fi
  printf -- '- Backup directory writable by runtime account: `%s`\n' "$writable"
fi

printf '\n## Operator metadata\n\n'
printf -- '- Browser: `%s`\n' "$BROWSER"
printf -- '- Theme: `%s`\n' "$THEME"

printf '\n## Deliberately excluded\n\n'
printf 'IP addresses, database host/name/user/password, API tokens, secrets, configuration contents and log contents are not collected by this helper. Add only the minimum sanitized error excerpts needed for a specific failed test.\n'
