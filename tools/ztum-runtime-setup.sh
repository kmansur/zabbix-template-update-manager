#!/usr/bin/env bash
set -euo pipefail

MODE="check"
RUNTIME_USER=""
BASE_DIR="/var/lib/zabbix-template-update-manager"

usage() {
  cat <<'EOF'
Usage: tools/ztum-runtime-setup.sh [--check|--apply] [--user USER] [--base DIR]

Default mode is --check. --apply must be run as root.

The script never modifies PHP-FPM configuration. It only validates or creates
private ZTUM runtime directories after resolving the PHP-FPM runtime account.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --check) MODE="check"; shift ;;
    --apply) MODE="apply"; shift ;;
    --user) RUNTIME_USER="${2:-}"; shift 2 ;;
    --base) BASE_DIR="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

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

  if [[ ${#candidates[@]} -eq 0 ]]; then
    return 1
  fi

  mapfile -t candidates < <(printf '%s
' "${candidates[@]}" | sort -u)
  if [[ ${#candidates[@]} -ne 1 ]]; then
    echo "Unable to choose one PHP-FPM user automatically: ${candidates[*]}" >&2
    return 1
  fi

  printf '%s
' "${candidates[0]}"
}

if [[ -z "$RUNTIME_USER" ]]; then
  if ! RUNTIME_USER="$(detect_runtime_user)"; then
    echo "PHP-FPM runtime user could not be determined safely. Re-run with --user USER." >&2
    exit 3
  fi
fi

if ! id "$RUNTIME_USER" >/dev/null 2>&1; then
  echo "Runtime user does not exist: $RUNTIME_USER" >&2
  exit 3
fi

RUNTIME_GROUP="$(id -gn "$RUNTIME_USER")"
BASE_DIR="${BASE_DIR%/}"
[[ -n "$BASE_DIR" && "$BASE_DIR" = /* ]] || {
  echo "--base must be an absolute path." >&2
  exit 2
}

DIRS=("$BASE_DIR" "$BASE_DIR/backups" "$BASE_DIR/offline" "$BASE_DIR/locks")

if [[ "$MODE" == "apply" ]]; then
  [[ "$(id -u)" -eq 0 ]] || {
    echo "--apply must be run as root." >&2
    exit 4
  }

  for dir in "${DIRS[@]}"; do
    if [[ -L "$dir" ]]; then
      echo "Refusing symbolic-link runtime directory: $dir" >&2
      exit 5
    fi
    install -d -o "$RUNTIME_USER" -g "$RUNTIME_GROUP" -m 0700 "$dir"
  done
fi

failed=0
for dir in "${DIRS[@]}"; do
  if [[ ! -d "$dir" || -L "$dir" ]]; then
    echo "FAIL  $dir (missing or unsafe)"
    failed=1
    continue
  fi

  owner="$(stat -c '%U' "$dir" 2>/dev/null || stat -f '%Su' "$dir")"
  mode="$(stat -c '%a' "$dir" 2>/dev/null || stat -f '%Lp' "$dir")"

  if [[ "$owner" != "$RUNTIME_USER" || "$mode" != "700" ]]; then
    echo "FAIL  $dir (owner=$owner mode=$mode; expected owner=$RUNTIME_USER mode=700)"
    failed=1
  else
    echo "OK    $dir (owner=$owner mode=$mode)"
  fi
done

if command -v runuser >/dev/null 2>&1; then
  if ! runuser -u "$RUNTIME_USER" -- test -w "$BASE_DIR/backups"; then
    echo "FAIL  backup directory is not writable by $RUNTIME_USER"
    failed=1
  fi
fi

echo "Runtime account: $RUNTIME_USER:$RUNTIME_GROUP"
echo "Mode: $MODE"

if [[ "$failed" -ne 0 ]]; then
  echo "ZTUM runtime directory validation failed." >&2
  exit 6
fi

echo "ZTUM runtime directory validation passed."
