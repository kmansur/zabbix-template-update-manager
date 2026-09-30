#!/usr/bin/env bash
set -euo pipefail

MODULE_DIR=""
OUTPUT_DIR=""
BROWSER="not-recorded"
THEME="not-recorded"

usage() {
  cat <<'EOF'
Usage:
  tools/ztum-validation-bundle.sh --module-dir DIR --output DIR [--browser TEXT] [--theme TEXT]

Creates a sanitized, read-only validation evidence bundle. Review its contents before
sharing externally. No Zabbix configuration write is performed.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --module-dir) MODULE_DIR="${2:-}"; shift 2 ;;
    --output) OUTPUT_DIR="${2:-}"; shift 2 ;;
    --browser) BROWSER="${2:-}"; shift 2 ;;
    --theme) THEME="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

[[ -d "$MODULE_DIR" ]] || { echo "--module-dir must exist" >&2; exit 2; }
[[ -n "$OUTPUT_DIR" ]] || { echo "--output is required" >&2; exit 2; }
mkdir -p "$OUTPUT_DIR"
chmod 0700 "$OUTPUT_DIR" 2>/dev/null || true

version="$(tr -d '\r\n' < "$MODULE_DIR/VERSION")"
commit="unknown"
if git -C "$MODULE_DIR" rev-parse HEAD >/dev/null 2>&1; then
  commit="$(git -C "$MODULE_DIR" rev-parse HEAD)"
fi

php_version="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unavailable)"
sodium="$(php -r 'echo function_exists("sodium_crypto_sign_verify_detached") ? "yes" : "no";' 2>/dev/null || echo unavailable)"
trusted_key="no"
[[ -n "${ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64:-}" ]] && trusted_key="yes"

cat > "$OUTPUT_DIR/environment.md" <<EOF
# ZTUM external validation evidence

- Captured at (UTC): $(date -u +%Y-%m-%dT%H:%M:%SZ)
- ZTUM version: $version
- ZTUM commit: $commit
- PHP version: $php_version
- PHP Sodium Ed25519 verification: $sodium
- Browser: $BROWSER
- Theme: $THEME
- Module directory: $MODULE_DIR
- Signed-index enforcement: ${ZTUM_REQUIRE_SIGNED_INDEX:-0}
- Trusted-index key configured: $trusted_key
- Offline-only mode: ${ZTUM_OFFLINE_ONLY:-0}

This bundle contains no credentials by design. Review every file before publishing.
EOF

if [[ -f "$MODULE_DIR/tools/ztum-field-evidence.sh" ]]; then
  bash "$MODULE_DIR/tools/ztum-field-evidence.sh" \
    --module-dir "$MODULE_DIR" \
    --browser "$BROWSER" \
    --theme "$THEME" \
    > "$OUTPUT_DIR/field-evidence.md"
fi

(
  cd "$MODULE_DIR"
  find . -type f ! -path './.git/*' ! -path './node_modules/*' -print0 |
    sort -z | xargs -0 sha256sum
) > "$OUTPUT_DIR/module-files.sha256"

cat > "$OUTPUT_DIR/reviewer-checklist.md" <<'EOF'
# Independent reviewer checklist

Record PASS / FAIL / NOT TESTED and attach evidence for:

- exact immutable tag/commit and archive checksum;
- Zabbix generation and exact version;
- module discovery and catalog;
- standard controlled update;
- reviewed/local-overwrite controlled update;
- controlled installation;
- batch stop-on-first-failure;
- interrupted/resumed batch behavior;
- rollback + recovery backup;
- signed online index verification;
- signed offline bundle verification;
- tampered index/signature/manifest/source rejection;
- global operation serialization;
- non-Super-Admin and CSRF negative tests;
- light/dark UI pass;
- no PHP fatal/browser console errors.

Any uncertain write outcome is a STOP condition. Do not retry automatically.
EOF

files=(environment.md reviewer-checklist.md module-files.sha256)
[[ -f "$OUTPUT_DIR/field-evidence.md" ]] && files+=(field-evidence.md)
(
  cd "$OUTPUT_DIR"
  sha256sum "${files[@]}" > SHA256SUMS
)

echo "Validation bundle created at: $OUTPUT_DIR"
