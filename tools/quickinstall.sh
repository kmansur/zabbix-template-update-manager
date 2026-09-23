#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY="kmansur/zabbix-template-update-manager"
REF="${ZTUM_REF:-main}"
MODULES_DIR=""
PHP_USER=""
ASSUME_YES=0
TARGET_NAME="zabbix-template-update-manager"
RUNTIME_BASE="/var/lib/zabbix-template-update-manager"

info() { printf '[ZTUM] %s\n' "$*"; }
warn() { printf '[ZTUM] WARNING: %s\n' "$*" >&2; }
die() { printf '[ZTUM] ERROR: %s\n' "$*" >&2; exit 1; }

usage() {
  cat <<'EOF'
ZTUM Quick Install (English)

Usage:
  sudo bash tools/quickinstall.sh [options]

Options:
  --ref REF             Git branch/tag/commit to install (default: main)
  --modules-dir DIR     Zabbix frontend modules directory
  --php-user USER       PHP-FPM/web runtime user
  --yes                 Non-interactive mode
  -h, --help            Show this help

This installer is intended for a NEW laboratory installation.
It refuses to overwrite an existing ZTUM installation.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --ref) REF="${2:-}"; shift 2 ;;
    --modules-dir) MODULES_DIR="${2:-}"; shift 2 ;;
    --php-user) PHP_USER="${2:-}"; shift 2 ;;
    --yes) ASSUME_YES=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) die "Unknown option: $1" ;;
  esac
done

[[ -n "$REF" ]] || die "--ref cannot be empty."

if [[ ${EUID:-$(id -u)} -ne 0 ]]; then
  if command -v sudo >/dev/null 2>&1; then
    info "Root privileges are required. Re-running with sudo."
    exec sudo --preserve-env=ZTUM_REF bash "$0" "$@"
  fi
  die "Run this installer as root (or with sudo)."
fi

for command in tar find install cp chmod chown sort awk grep sed mktemp; do
  command -v "$command" >/dev/null 2>&1 || die "Required command not found: $command"
done

if command -v curl >/dev/null 2>&1; then
  DOWNLOADER="curl"
elif command -v wget >/dev/null 2>&1; then
  DOWNLOADER="wget"
else
  die "curl or wget is required. Install one of them and run this installer again."
fi

TMP_DIR="$(mktemp -d -t ztum-quickinstall.XXXXXX)"
trap 'rm -rf "$TMP_DIR"' EXIT
mkdir -p "$TMP_DIR/extract"

if [[ "$REF" =~ ^[0-9a-fA-F]{40}$ ]]; then
  ARCHIVE_URL="https://github.com/${REPOSITORY}/archive/${REF}.tar.gz"
elif [[ "$REF" == v* ]]; then
  ARCHIVE_URL="https://github.com/${REPOSITORY}/archive/refs/tags/${REF}.tar.gz"
else
  ARCHIVE_URL="https://github.com/${REPOSITORY}/archive/refs/heads/${REF}.tar.gz"
fi

info "Downloading ZTUM source: $REF"
if [[ "$DOWNLOADER" == "curl" ]]; then
  curl --fail --location --silent --show-error     --connect-timeout 10 --max-time 120     "$ARCHIVE_URL" -o "$TMP_DIR/ztum.tar.gz"     || die "Unable to download $ARCHIVE_URL"
else
  wget -q --timeout=120 -O "$TMP_DIR/ztum.tar.gz" "$ARCHIVE_URL"     || die "Unable to download $ARCHIVE_URL"
fi

tar -xzf "$TMP_DIR/ztum.tar.gz" -C "$TMP_DIR/extract"
SOURCE_DIR="$(find "$TMP_DIR/extract" -mindepth 1 -maxdepth 1 -type d -print -quit)"
[[ -n "$SOURCE_DIR" ]] || die "The downloaded archive could not be extracted."

for required in Module.php manifest.json VERSION actions src views tools/ztum-runtime-setup.sh; do
  [[ -e "$SOURCE_DIR/$required" ]] || die "Downloaded source is incomplete: missing $required"
done

VERSION="$(tr -d '\r\n' < "$SOURCE_DIR/VERSION")"
[[ -n "$VERSION" ]] || die "VERSION is empty."
info "Downloaded version: $VERSION"

choose_modules_dir() {
  local candidates=()
  local candidate

  for candidate in     /usr/share/zabbix/modules     /usr/share/zabbix/ui/modules     /usr/local/share/zabbix/modules     /usr/local/share/zabbix/ui/modules     /var/www/html/zabbix/modules     /var/www/zabbix/modules; do
    [[ -d "$candidate" ]] && candidates+=("$candidate")
  done

  while IFS= read -r candidate; do
    [[ -n "$candidate" ]] && candidates+=("$candidate")
  done < <(
    find /usr/share/zabbix /usr/local/share/zabbix /var/www       -maxdepth 6 -type d -name modules 2>/dev/null || true
  )

  mapfile -t candidates < <(printf '%s\n' "${candidates[@]}" | awk 'NF' | sort -u)

  if [[ ${#candidates[@]} -eq 0 ]]; then
    die "No Zabbix frontend modules directory was found. Re-run with --modules-dir /path/to/zabbix/modules"
  fi

  if [[ ${#candidates[@]} -eq 1 ]]; then
    MODULES_DIR="${candidates[0]}"
    return
  fi

  printf '\nMore than one Zabbix modules directory was found:\n'
  local i=1
  for candidate in "${candidates[@]}"; do
    printf '  %d) %s\n' "$i" "$candidate"
    ((i++))
  done

  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
    die "Multiple module directories were found. Re-run with --modules-dir DIR."
  fi

  local choice
  read -r -p "Choose the Zabbix modules directory [1-${#candidates[@]}]: " choice
  [[ "$choice" =~ ^[0-9]+$ ]] || die "Invalid selection."
  (( choice >= 1 && choice <= ${#candidates[@]} )) || die "Invalid selection."
  MODULES_DIR="${candidates[choice-1]}"
}

if [[ -z "$MODULES_DIR" ]]; then
  choose_modules_dir
fi
[[ -d "$MODULES_DIR" ]] || die "Modules directory does not exist: $MODULES_DIR"

TARGET_DIR="$MODULES_DIR/$TARGET_NAME"
if [[ -e "$TARGET_DIR" ]]; then
  die "ZTUM already exists at $TARGET_DIR. This quick installer only performs a new installation and will not overwrite it."
fi

detect_php_user() {
  local pool_files=()
  local users=()
  local file value

  shopt -s nullglob
  pool_files=(
    /etc/php/*/fpm/pool.d/*zabbix*.conf
    /etc/php-fpm.d/*zabbix*.conf
    /usr/local/etc/php-fpm.d/*zabbix*.conf
  )
  shopt -u nullglob

  if [[ ${#pool_files[@]} -eq 0 ]]; then
    shopt -s nullglob
    pool_files=(
      /etc/php/*/fpm/pool.d/*.conf
      /etc/php-fpm.d/*.conf
      /usr/local/etc/php-fpm.d/*.conf
    )
    shopt -u nullglob
  fi

  for file in "${pool_files[@]}"; do
    while IFS= read -r value; do
      value="${value%%;*}"
      value="${value#*=}"
      value="$(printf '%s' "$value" | xargs)"
      [[ -n "$value" && "$value" != "root" ]] && users+=("$value")
    done < <(grep -E '^[[:space:]]*user[[:space:]]*=' "$file" 2>/dev/null || true)
  done

  mapfile -t users < <(printf '%s\n' "${users[@]}" | awk 'NF' | sort -u)

  if [[ ${#users[@]} -eq 1 ]]; then
    PHP_USER="${users[0]}"
    return
  fi

  if [[ ${#users[@]} -gt 1 ]]; then
    printf '\nMore than one PHP-FPM runtime user was detected:\n'
    local i=1
    for value in "${users[@]}"; do
      printf '  %d) %s\n' "$i" "$value"
      ((i++))
    done

    if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
      die "Multiple PHP-FPM users were found. Re-run with --php-user USER."
    fi

    local choice
    read -r -p "Choose the PHP-FPM user [1-${#users[@]}]: " choice
    [[ "$choice" =~ ^[0-9]+$ ]] || die "Invalid selection."
    (( choice >= 1 && choice <= ${#users[@]} )) || die "Invalid selection."
    PHP_USER="${users[choice-1]}"
    return
  fi

  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
    die "Unable to detect the PHP-FPM user. Re-run with --php-user USER."
  fi

  read -r -p "PHP-FPM/web user (common examples: www-data, apache, nginx): " PHP_USER
}

if [[ -z "$PHP_USER" ]]; then
  detect_php_user
fi
id "$PHP_USER" >/dev/null 2>&1 || die "System user does not exist: $PHP_USER"

info "Zabbix modules directory: $MODULES_DIR"
info "PHP-FPM/web user: $PHP_USER"
info "Preparing private runtime directories."
bash "$SOURCE_DIR/tools/ztum-runtime-setup.sh" --apply --user "$PHP_USER" --base "$RUNTIME_BASE"
bash "$SOURCE_DIR/tools/ztum-runtime-setup.sh" --check --user "$PHP_USER" --base "$RUNTIME_BASE"

info "Installing the module."
install -d -o root -g root -m 0755 "$TARGET_DIR"
cp -a   "$SOURCE_DIR/Module.php"   "$SOURCE_DIR/manifest.json"   "$SOURCE_DIR/VERSION"   "$SOURCE_DIR/actions"   "$SOURCE_DIR/src"   "$SOURCE_DIR/views"   "$TARGET_DIR/"

chown -R root:root "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 0755 {} +
find "$TARGET_DIR" -type f -exec chmod 0644 {} +

[[ -r "$TARGET_DIR/manifest.json" && -r "$TARGET_DIR/Module.php" ]]   || die "Installation verification failed."

printf '\n'
info "ZTUM $VERSION was installed successfully."
printf '\nNext steps in the Zabbix web interface:\n'
printf '  1. Sign in as a Zabbix Super Admin.\n'
printf '  2. Open: Administration -> General -> Modules.\n'
printf '  3. Click: Scan directory.\n'
printf '  4. Enable: Template Update Manager.\n'
printf '  5. Open: Data collection -> Template updates.\n'
printf '\n'
warn "This is a laboratory beta. Production use is not recommended yet."
printf 'If the module is not shown after scanning, restart your PHP-FPM service and scan again.\n'
printf 'Installed path: %s\n' "$TARGET_DIR"
printf 'Runtime path:   %s\n' "$RUNTIME_BASE"
