#!/usr/bin/env bash
# ZTUM local-source installer — new installations only.
set -Eeuo pipefail
umask 022

SOURCE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
MODULE_NAME="zabbix-template-update-manager"
MODULES_DIR=""
PHP_USER=""
ZABBIX_VERSION=""
DRY_RUN=0
RUNTIME_BASE="/var/lib/zabbix-template-update-manager"
STAGE=""

log() { printf '[ZTUM] %s\n' "$*"; }
die() { printf '[ZTUM] ERROR: %s\n' "$*" >&2; exit 1; }
usage() {
  cat <<'HELP'
Usage: sudo bash install.sh [--modules-dir /path/to/modules] [--php-user USER] [--check]
       bash install.sh --check [--modules-dir /path/to/modules] [--php-user USER]

Installs from the local Git checkout, copying only runtime files.
--check is read-only. Existing installations are NEVER overwritten.
For Zabbix 7/8 use the frontend's actual modules directory.
HELP
}
while (($#)); do
  case "$1" in
    --modules-dir) (($# >= 2)) || die "Missing --modules-dir value"; MODULES_DIR="$2"; shift 2;;
    --php-user) (($# >= 2)) || die "Missing --php-user value"; PHP_USER="$2"; shift 2;;
    --check) DRY_RUN=1; shift;;
    -h|--help) usage; exit 0;;
    *) die "Unknown argument: $1";;
  esac
done

for file in Module.php manifest.json VERSION; do
  [[ -f "$SOURCE_DIR/$file" && ! -L "$SOURCE_DIR/$file" ]] || die "Missing or linked source file: $file"
done
for dir in actions assets src views; do
  [[ -d "$SOURCE_DIR/$dir" && ! -L "$SOURCE_DIR/$dir" ]] || die "Missing or linked source directory: $dir"
done
[[ -f "$SOURCE_DIR/assets/ztum-update-batch.js" && -f "$SOURCE_DIR/assets/ztum-install-batch.js" ]] || die "Missing registered JavaScript assets"
[[ -f "$SOURCE_DIR/tools/ztum-runtime-setup.sh" ]] || die "Runtime setup helper is missing"
if find "$SOURCE_DIR/actions" "$SOURCE_DIR/assets" "$SOURCE_DIR/src" "$SOURCE_DIR/views" -type l -print -quit | grep -q .; then
  die "Refusing symlinks inside runtime source directories"
fi
if command -v php >/dev/null 2>&1; then
  php -r '
    $m=json_decode(file_get_contents($argv[1]), true);
    if (!is_array($m) || ($m["manifest_version"] ?? null) != 2.0) exit(1);
    $v=trim(file_get_contents($argv[2]));
    if (($m["version"] ?? null)!==$v) exit(2);
  ' "$SOURCE_DIR/manifest.json" "$SOURCE_DIR/VERSION" || die "Invalid manifest or VERSION mismatch"
else
  die "PHP CLI is required to validate manifest.json"
fi

if [[ -z "$MODULES_DIR" ]]; then
  candidates=()
  for d in /usr/share/zabbix/ui/modules /usr/share/zabbix/modules /usr/local/share/zabbix/ui/modules /usr/local/share/zabbix/modules /var/www/html/zabbix/modules /var/www/zabbix/modules; do
    [[ -d "$d" ]] && candidates+=("$d")
  done
  (("${#candidates[@]}" == 1)) || die "Expected exactly one frontend modules path; use --modules-dir DIR (detected: ${candidates[*]:-none})"
  MODULES_DIR="${candidates[0]}"
fi
[[ -d "$MODULES_DIR" && ! -L "$MODULES_DIR" ]] || die "Invalid or linked modules directory: $MODULES_DIR"
MODULES_DIR="$(cd "$MODULES_DIR" && pwd -P)"
FRONTEND="$(dirname "$MODULES_DIR")"
[[ "$(basename "$MODULES_DIR")" == modules && -f "$FRONTEND/index.php" ]] || die "Not a recognizable Zabbix frontend modules directory: $MODULES_DIR"

# Version discovery is a prerequisite, never guessed from the source version.
for f in "$FRONTEND/include/defines.inc.php" "$FRONTEND/include/version.inc.php" "$FRONTEND/include/classes/core/ZBase.php"; do
  [[ -f "$f" ]] || continue
  if [[ -z "$ZABBIX_VERSION" ]]; then
    ZABBIX_VERSION="$(sed -nE "s/.*(define\\(['\"]ZABBIX_VERSION['\"],[[:space:]]*['\"]|const[[:space:]]+ZABBIX_VERSION[[:space:]]*=[[:space:]]*['\"])([0-9]+\\.[0-9]+(\\.[0-9]+)?).*/\\2/p" "$f" | head -n1)"
  fi
done
if [[ -z "$ZABBIX_VERSION" ]] && command -v dpkg-query >/dev/null 2>&1; then
  for pkg in zabbix-frontend-php zabbix-frontend-php-mysql zabbix-frontend-php-pgsql; do
    v="$(dpkg-query -W -f='${Version}' "$pkg" 2>/dev/null || true)"
    if [[ "$v" =~ ^[0-9]+:[0-9]+ ]]; then v="${v#*:}"; fi
    if [[ "$v" =~ ^([78])\\.([0-9]+)\\. ]]; then ZABBIX_VERSION="${BASH_REMATCH[0]%.}"; break; fi
  done
fi
[[ "$ZABBIX_VERSION" =~ ^([78])\\.[0-9]+(\\.[0-9]+)?$ ]] || die "Could not safely verify frontend version 7.x or 8.x; inspect the frontend installation first"
log "Detected Zabbix frontend: $ZABBIX_VERSION"

TARGET="$MODULES_DIR/$MODULE_NAME"
[[ ! -e "$TARGET" && ! -L "$TARGET" ]] || die "ZTUM already installed: $TARGET. Refusing overwrite; use a dedicated upgrade procedure."

if [[ -z "$PHP_USER" ]]; then
  files=()
  shopt -s nullglob
  files=(/etc/php/*/fpm/pool.d/*.conf /etc/php-fpm.d/*.conf /usr/local/etc/php-fpm.d/*.conf)
  shopt -u nullglob
  users=()
  for f in "${files[@]}"; do
    while IFS= read -r user; do [[ -n "$user" && "$user" != root ]] && users+=("$user"); done < <(
      sed -nE 's/^[[:space:]]*user[[:space:]]*=[[:space:]]*([a-z_][a-z0-9_-]*).*$/\\1/p' "$f")
  done
  if (("${#users[@]}")); then
    mapfile -t users < <(printf '%s\n' "${users[@]}" | sort -u)
  fi
  (("${#users[@]}" == 1)) || die "PHP-FPM user ambiguous or unknown; provide --php-user USER"
  PHP_USER="${users[0]}"
fi
id "$PHP_USER" >/dev/null 2>&1 || die "Unknown web runtime account: $PHP_USER"
[[ "$PHP_USER" != root ]] || die "PHP-FPM runtime cannot be root"

log "Source: $SOURCE_DIR"
log "Destination: $TARGET"
log "PHP-FPM account: $PHP_USER"
log "Runtime directory: $RUNTIME_BASE"
if ((DRY_RUN)); then
  log "Read-only validation successful. No files were changed."
  exit 0
fi

((EUID == 0)) || die "Run as root for installation; use --check for read-only validation"
# Protect user data: never remove, replace or chown the existing runtime tree wholesale.
bash "$SOURCE_DIR/tools/ztum-runtime-setup.sh" --apply --user "$PHP_USER" --base "$RUNTIME_BASE"
bash "$SOURCE_DIR/tools/ztum-runtime-setup.sh" --check --user "$PHP_USER" --base "$RUNTIME_BASE"

cleanup() { [[ -z "$STAGE" ]] || rm -rf -- "$STAGE"; }
trap cleanup EXIT
STAGE="$(mktemp -d "$MODULES_DIR/.ztum-install.XXXXXXXX")"
for d in actions assets src views; do
  cp -R -- "$SOURCE_DIR/$d" "$STAGE/"
done
for f in Module.php manifest.json VERSION; do cp -- "$SOURCE_DIR/$f" "$STAGE/"; done
chown -R root:root "$STAGE"
find "$STAGE" -type d -exec chmod 0755 {} +
find "$STAGE" -type f -exec chmod 0644 {} +
[[ -r "$STAGE/Module.php" && -r "$STAGE/manifest.json" && -r "$STAGE/assets/ztum-update-batch.js" && -r "$STAGE/assets/ztum-install-batch.js" ]] || die "Staging verification failed"
[[ ! -e "$TARGET" && ! -L "$TARGET" ]] || die "Target appeared during installation; refusing overwrite"
mv -T -- "$STAGE" "$TARGET"
STAGE=""
log "Installed ZTUM $(cat "$SOURCE_DIR/VERSION") (laboratory release)."
log "Next: Administration > General > Modules > Scan directory > Enable module."
log "Access: Data collection > Template updates (Super Admin only)."
log "No Zabbix database, template, Nginx or PHP-FPM configuration was changed."
