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
RUNTIME_CHECK=0
UPGRADE=0
ROLLBACK=""
BACKUP_BASE="/var/backups/zabbix-template-update-manager"
RUNTIME_BASE="/var/lib/zabbix-template-update-manager"
STAGE=""

log() { printf '[ZTUM] %s\n' "$*"; }
die() { printf '[ZTUM] ERROR: %s\n' "$*" >&2; exit 1; }
usage() {
  cat <<'HELP'
Usage: sudo bash install.sh [--check] [--modules-dir DIR] [--php-user USER]
       sudo bash install.sh --runtime-check [--php-user USER]
       sudo bash install.sh --upgrade [--modules-dir DIR] [--php-user USER]
       sudo bash install.sh --rollback BACKUP_ID [--modules-dir DIR]
       bash install.sh --check [--modules-dir /path/to/modules] [--php-user USER]

Installs from the local Git checkout, copying only runtime files.
--check is read-only. Existing installations are never overwritten by install.
--upgrade makes a verified code backup, then replaces only frontend code.
--rollback restores a verified code backup by its local BACKUP_ID.
Runtime data is preserved by code upgrade/rollback. On first install,
private runtime directories are created/validated automatically.
--runtime-check validates existing directories without changes.
For Zabbix 7/8 use the frontend's actual modules directory.
HELP
}
while (($#)); do
  case "$1" in
    --modules-dir) (($# >= 2)) || die "Missing --modules-dir value"; MODULES_DIR="$2"; shift 2;;
    --php-user) (($# >= 2)) || die "Missing --php-user value"; PHP_USER="$2"; shift 2;;
    --check) DRY_RUN=1; shift;;
    --runtime-check) RUNTIME_CHECK=1; shift;;
    --upgrade) UPGRADE=1; shift;;
    --rollback) (($# >= 2)) || die "Missing backup ID"; ROLLBACK="$2"; shift 2;;
    -h|--help) usage; exit 0;;
    *) die "Unknown argument: $1";;
  esac
done
((UPGRADE == 0 || DRY_RUN == 0)) || die "--upgrade and --check cannot be combined"
((RUNTIME_CHECK == 0 || (DRY_RUN == 0 && UPGRADE == 0 && -z "$ROLLBACK"))) || die "Conflicting runtime-check options"
[[ -z "$ROLLBACK" || ( "$UPGRADE" -eq 0 && "$DRY_RUN" -eq 0 ) ]] || die "--rollback cannot be combined with --upgrade/--check"

for file in Module.php manifest.json VERSION; do
  [[ -f "$SOURCE_DIR/$file" && ! -L "$SOURCE_DIR/$file" ]] || die "Missing or linked source file: $file"
done
for dir in actions assets src views; do
  [[ -d "$SOURCE_DIR/$dir" && ! -L "$SOURCE_DIR/$dir" ]] || die "Missing or linked source directory: $dir"
done
[[ -f "$SOURCE_DIR/assets/js/ztum-update-batch.js" && -f "$SOURCE_DIR/assets/js/ztum-install-batch.js" ]] || die "Missing registered JavaScript assets"
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

detect_php_user() {
if [[ -z "$PHP_USER" ]]; then
  files=()
  shopt -s nullglob
  files=(/etc/php/*/fpm/pool.d/*.conf /etc/php-fpm.d/*.conf /usr/local/etc/php-fpm.d/*.conf)
  shopt -u nullglob
  users=()
  for f in "${files[@]}"; do
    while IFS= read -r user; do [[ -n "$user" && "$user" != root ]] && users+=("$user"); done < <(
      sed -nE 's/^[[:space:]]*user[[:space:]]*=[[:space:]]*([a-z_][a-z0-9_-]*).*$/\1/p' "$f")
  done
  if (("${#users[@]}")); then
    mapfile -t users < <(printf '%s\n' "${users[@]}" | sort -u)
  fi
  (("${#users[@]}" == 1)) || die "PHP-FPM user ambiguous or unknown; provide --php-user USER"
  PHP_USER="${users[0]}"
fi
id "$PHP_USER" >/dev/null 2>&1 || die "Unknown web runtime account: $PHP_USER"
[[ "$PHP_USER" != root ]] || die "PHP-FPM runtime cannot be root"

}

# Runtime storage is private and is never removed or recursively chowned.
runtime_dirs() {
  printf '%s\\n' "$RUNTIME_BASE" "$RUNTIME_BASE/backups" "$RUNTIME_BASE/offline" "$RUNTIME_BASE/locks" "$RUNTIME_BASE/batches" "$RUNTIME_BASE/cache"
}
check_runtime() {
  local dir owner mode failed=0
  while IFS= read -r dir; do
    if [[ ! -d "$dir" || -L "$dir" ]]; then
      log "FAIL runtime: missing/unsafe $dir"
      failed=1
      continue
    fi
    owner="$(stat -c '%U' "$dir")" || die "Unable to inspect runtime owner"
    mode="$(stat -c '%a' "$dir")" || die "Unable to inspect runtime permissions"
    if [[ "$owner" != "$PHP_USER" || "$mode" != "700" ]]; then
      log "FAIL runtime: $dir (owner=$owner, mode=$mode; expected $PHP_USER:700)"
      failed=1
    else
      log "OK runtime: $dir"
    fi
  done < <(runtime_dirs)
  ((failed == 0)) || die "Runtime storage validation failed"
  if ((EUID == 0)) && command -v runuser >/dev/null 2>&1; then
    runuser -u "$PHP_USER" -- test -w "$RUNTIME_BASE/backups" || die "PHP-FPM cannot write backups"
    runuser -u "$PHP_USER" -- test -w "$RUNTIME_BASE/cache" || die "PHP-FPM cannot write cache"
  fi
}
prepare_runtime() {
  ((EUID == 0)) || die "Runtime setup requires root"
  local dir group
  group="$(id -gn "$PHP_USER")"
  while IFS= read -r dir; do
    [[ ! -L "$dir" ]] || die "Runtime directory is a symlink: $dir"
    [[ ! -e "$dir" || -d "$dir" ]] || die "Runtime path is not a directory: $dir"
    if [[ -d "$dir" ]]; then
      [[ "$(stat -c '%U' "$dir")" == "$PHP_USER" ]] || die "Unsafe owner of existing runtime directory: $dir"
      chmod 0700 "$dir" || die "Cannot secure runtime directory: $dir"
    else
      install -d -o "$PHP_USER" -g "$group" -m 0700 "$dir" || die "Unable to create runtime directory: $dir"
    fi
  done < <(runtime_dirs)
  check_runtime
}

if ((RUNTIME_CHECK)); then
  detect_php_user
  check_runtime
  log "Runtime validation passed (read-only)."
  exit 0
fi

if [[ -z "$MODULES_DIR" ]]; then
  candidates=()
  for d in /usr/share/zabbix/ui/modules /usr/share/zabbix/modules /usr/local/share/zabbix/ui/modules /usr/local/share/zabbix/modules /var/www/html/zabbix/modules /var/www/zabbix/modules; do
    [[ -d "$d" ]] && candidates+=("$d")
  done
  valid_candidates=()
  for d in "${candidates[@]}"; do
    [[ -f "$(dirname "$d")/index.php" ]] && valid_candidates+=("$d")
  done
  (("${#valid_candidates[@]}" == 1)) || die "Unable to select a unique Zabbix frontend; specify --modules-dir DIR (candidates: ${valid_candidates[*]:-none})"
  MODULES_DIR="${valid_candidates[0]}"
fi
[[ -d "$MODULES_DIR" && ! -L "$MODULES_DIR" ]] || die "Invalid or linked modules directory: $MODULES_DIR"
MODULES_DIR="$(cd "$MODULES_DIR" && pwd -P)"
FRONTEND="$(dirname "$MODULES_DIR")"
[[ "$(basename "$MODULES_DIR")" == modules && -f "$FRONTEND/index.php" ]] || die "Not a recognizable Zabbix frontend modules directory: $MODULES_DIR"

# Version discovery is a prerequisite, never guessed from the source version.
for f in "$FRONTEND/include/defines.inc.php" "$FRONTEND/include/version.inc.php" "$FRONTEND/include/classes/core/ZBase.php"; do
  [[ -f "$f" ]] || continue
  if [[ -z "$ZABBIX_VERSION" ]]; then
    ZABBIX_VERSION="$(sed -nE "s/.*(define\\(['\"]ZABBIX_VERSION['\"],[[:space:]]*['\"]|const[[:space:]]+ZABBIX_VERSION[[:space:]]*=[[:space:]]*['\"])([0-9]+\.[0-9]+(\.[0-9]+)?).*/\\2/p" "$f" | head -n1)"
  fi
done
if [[ -z "$ZABBIX_VERSION" ]] && command -v dpkg-query >/dev/null 2>&1; then
  for pkg in zabbix-frontend-php zabbix-frontend-php-mysql zabbix-frontend-php-pgsql; do
    v="$(dpkg-query -W -f='${Version}' "$pkg" 2>/dev/null || true)"
    if [[ "$v" =~ ^[0-9]+:[0-9]+ ]]; then v="${v#*:}"; fi
    if [[ "$v" =~ ^([78])\.([0-9]+)\. ]]; then ZABBIX_VERSION="${BASH_REMATCH[0]%.}"; break; fi
  done
fi
[[ "$ZABBIX_VERSION" =~ ^([78])\.[0-9]+(\.[0-9]+)?$ ]] || die "Could not safely verify frontend version 7.x or 8.x; inspect the frontend installation first"
log "Zabbix frontend: $ZABBIX_VERSION"

TARGET="$MODULES_DIR/$MODULE_NAME"

# All backup metadata is private, off-web and owned by root. Lock prevents two
# cooperating installer processes from racing each other.
hash_tree() {
  local dir="$1"
  ( cd "$dir"
    find . -type l -print -quit | grep -q . && return 2
    find . -type f -print0 | LC_ALL=C sort -z | xargs -0 -r sha256sum
  )
}
verify_tree() {
  local dir="$1" expected="$2" actual
  [[ -d "$dir" && ! -L "$dir" ]] || return 1
  actual="$(hash_tree "$dir")" || return 1
  [[ "$actual" == "$expected" ]]
}
verified_backup() {
  local id="$1" folder expected
  folder="$BACKUP_BASE/$id"
  [[ "$id" =~ ^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{8}$ ]] || die "Invalid backup ID"
  [[ -d "$folder" && ! -L "$folder" ]] || die "Backup not found"
  [[ "$(stat -c '%U:%a' "$folder")" == "root:700" ]] || die "Unsafe backup permissions"
  [[ -f "$folder/SHA256SUMS" && ! -L "$folder/SHA256SUMS" ]] || die "Missing checksum manifest"
  log "Rollback preflight: checking backup $id" >&2
  expected="$(cat "$folder/SHA256SUMS")"
  [[ -n "$expected" ]] || die "Empty backup checksum manifest; rollback aborted before changes"
  verify_tree "$folder/module" "$expected" || die "Backup integrity failure (missing, changed or extra files); rollback aborted before changes"
  log "Rollback preflight: SHA-256 inventory verified; no missing, changed or extra files" >&2
  printf '%s\n' "$folder/module"
}
prepare_stage() {
  local origin="$1" stage="$2"
  for item in Module.php manifest.json VERSION actions assets src views; do
    [[ -e "$origin/$item" && ! -L "$origin/$item" ]] || die "Missing or linked runtime component: $item"
    cp -a -- "$origin/$item" "$stage/"
  done
  find "$stage" -type l -print -quit | grep -q . && die "Symlink found in staged module"
  chown -R root:root "$stage"
  find "$stage" -type d -exec chmod 0755 {} +
  find "$stage" -type f -exec chmod 0644 {} +
  php -r '
    $m=json_decode(file_get_contents($argv[1]."/manifest.json"), true);
    $v=trim(file_get_contents($argv[1]."/VERSION"));
    if (!is_array($m) || ($m["version"] ?? null) !== $v) exit(1);
  ' "$stage" || die "Staged version/manifest mismatch"
  [[ -f "$stage/assets/js/ztum-update-batch.js" && -f "$stage/assets/js/ztum-install-batch.js" ]] || die "Staged assets missing"
}
do_upgrade_or_rollback() {
  (( EUID == 0 )) || die "Upgrade/rollback requires root"
  if [[ -z "$ROLLBACK" ]]; then
    detect_php_user
    prepare_runtime
  fi
  for tool in flock sha256sum stat mktemp; do command -v "$tool" >/dev/null || die "Missing tool: $tool"; done
  [[ -d "$TARGET" && ! -L "$TARGET" ]] || die "Existing installation required; refusing upgrade/rollback"
  [[ ! -L "$BACKUP_BASE" ]] || die "Unsafe backup root symlink"
  install -d -o root -g root -m 0700 "$BACKUP_BASE"
  [[ "$(stat -c '%U:%a' "$BACKUP_BASE")" == "root:700" ]] || die "Unsafe backup root"
  exec 9>/run/lock/ztum-installer.lock
  flock -n 9 || die "Another installer is running"

  stage=""
  old=""
  local id="" folder="" saved="" original_hash="" stage_hash="" current_version="" new_version="" failed_dir=""
  stage="$(mktemp -d "$MODULES_DIR/.ztum-stage.XXXXXXXX")"
  old=""
  cleanup_upgrade() {
    if [[ -n "$old" && -d "$old" && ! -e "$TARGET" ]]; then
      mv -T -- "$old" "$TARGET" || true
    fi
    if [[ -n "$old" && -e "$old" ]]; then
      printf '[ZTUM] CRITICAL: original module preserved for manual recovery: %s\n' "$old" >&2
    fi
    [[ -z "$stage" || ! -e "$stage" ]] || rm -rf -- "$stage"
  }
  trap cleanup_upgrade EXIT

  current_version="$(cat "$TARGET/VERSION" 2>/dev/null || true)"
  if [[ -n "$ROLLBACK" ]]; then
    saved="$(verified_backup "$ROLLBACK")"
    log "Restoring verified backup: $ROLLBACK"
    prepare_stage "$saved" "$stage"
    log "Rollback preflight: staged files validated; backup integrity confirmed"
  else
    new_version="$(cat "$SOURCE_DIR/VERSION")"
    [[ -n "$current_version" ]] || die "Cannot identify installed version"
    php -r 'exit(version_compare($argv[1],$argv[2], ">") ? 0 : 1);' "$new_version" "$current_version" || die "Upgrade requires a newer version (current: $current_version; source: $new_version)"
    prepare_stage "$SOURCE_DIR" "$stage"
  fi
  stage_hash="$(hash_tree "$stage")" || die "Unable to hash staged files"
  [[ -n "$stage_hash" ]] || die "Empty staging inventory"

  # Preserve every original code file, including legacy files, in a private backup.
  id="$(date -u +%Y%m%dT%H%M%SZ)-$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')"
  folder="$BACKUP_BASE/$id"
  install -d -o root -g root -m 0700 "$folder"
  cp -a -- "$TARGET" "$folder/module"
  original_hash="$(hash_tree "$TARGET")" || die "Existing installation has unsafe symlinks; backup rejected"
  [[ "$original_hash" == "$(hash_tree "$folder/module")" ]] || die "Existing code backup verification failed"
  printf '%s\n' "$original_hash" > "$folder/SHA256SUMS"
  chmod 0600 "$folder/SHA256SUMS"
  [[ "$stage_hash" == "$(hash_tree "$stage")" ]] || die "Staged source changed during preparation"
  old="$(mktemp -d "$MODULES_DIR/.ztum-previous.XXXXXXXX")"
  rmdir "$old"
  mv -T -- "$TARGET" "$old" || die "Unable to park original code"
  if ! mv -T -- "$stage" "$TARGET"; then
    mv -T -- "$old" "$TARGET" || die "CRITICAL: restore manually from $old"
    old=""
    die "Activation failed; original restored"
  fi
  stage=""
  if [[ "$stage_hash" != "$(hash_tree "$TARGET")" ]]; then
    # Restore immediately if deployed contents were unexpectedly modified.
    failed_dir="$(mktemp -d "$MODULES_DIR/.ztum-failed.XXXXXXXX")"
    rmdir "$failed_dir"
    mv -T -- "$TARGET" "$failed_dir" || die "CRITICAL: deployed module could not be parked; original preserved: $old"
    if mv -T -- "$old" "$TARGET"; then
      old=""
      rm -rf -- "$failed_dir"
      die "Deployed content verification failed; original restored"
    fi
    die "CRITICAL: original preserved at $old; inspect manually before retry"
  fi
  rm -rf -- "$old"
  old=""
  trap - EXIT
  log "Operation completed: $(cat "$TARGET/VERSION")"
  log "Verified rollback backup ID: $id"
  log "Restore command: sudo bash install.sh --rollback $id"
  log "Private runtime state untouched. Confirm web UI, menu and PHP-FPM cache."
}

if ((UPGRADE)) || [[ -n "$ROLLBACK" ]]; then
  do_upgrade_or_rollback
  exit 0
fi
if [[ -e "$TARGET" || -L "$TARGET" ]]; then
  if ((DRY_RUN)); then
    log "Existing module: $TARGET"
    log "CHECK: installed directory detected; no files were changed."
    log "Use --runtime-check to validate private runtime storage."
    exit 0
  fi
  die "ZTUM already installed: $TARGET. Refusing overwrite; use --upgrade only after validation."
fi

detect_php_user

log "Source: $SOURCE_DIR"
log "Destination: $TARGET"
log "PHP-FPM account: $PHP_USER"
log "Runtime directory: $RUNTIME_BASE"
if ((DRY_RUN)); then
  log "CHECK: READY for a new installation. Runtime directories will be secured during install; no files changed."
  exit 0
fi

((EUID == 0)) || die "Run as root for installation; use --check for read-only validation"
# Protect user data: never remove, replace or chown the existing runtime tree wholesale.
prepare_runtime

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
[[ -r "$STAGE/Module.php" && -r "$STAGE/manifest.json" && -r "$STAGE/assets/js/ztum-update-batch.js" && -r "$STAGE/assets/js/ztum-install-batch.js" ]] || die "Staging verification failed"
[[ ! -e "$TARGET" && ! -L "$TARGET" ]] || die "Target appeared during installation; refusing overwrite"
mv -T -- "$STAGE" "$TARGET"
STAGE=""
log "----------------------------------------"
log "ZTUM Installer — completed"
log "Zabbix:          $ZABBIX_VERSION"
log "PHP-FPM:         $PHP_USER"
log "ZTUM:            $(cat "$SOURCE_DIR/VERSION")"
log "Files:           OK"
log "Permissions:     root:root (directories 0755, files 0644)"
log "Private runtime: OK"
log "Installation:    COMPLETE (laboratory beta)"
log "----------------------------------------"
log "Next: Administration > General > Modules > Scan directory > Enable module."
log "Access: Data collection > Template updates (Super Admin only)."
log "No Zabbix database, template, Nginx or PHP-FPM configuration was changed."
