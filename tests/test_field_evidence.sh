#!/usr/bin/env bash
set -euo pipefail

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/module" "$tmp/state"/{backups,offline,locks}
chmod 700 "$tmp/state" "$tmp/state"/{backups,offline,locks}

cat > "$tmp/module/manifest.json" <<'JSON'
{
  "version": "0.1.0-beta.59"
}
JSON
printf '0.1.0-beta.59\n' > "$tmp/module/VERSION"
printf 'artifact\n' > "$tmp/release.tar.gz"
expected="$(sha256sum "$tmp/release.tar.gz" | awk '{print $1}')"

bash -n tools/ztum-field-evidence.sh

output="$(bash tools/ztum-field-evidence.sh \
  --module-dir "$tmp/module" \
  --runtime-user "$(id -un)" \
  --runtime-base "$tmp/state" \
  --browser 'Chromium test' \
  --theme 'dark' \
  --artifact "$tmp/release.tar.gz" \
  --expected-sha256 "$expected")"

grep -Fq '# ZTUM field evidence' <<<"$output"
grep -Fq 'Manifest version: `0.1.0-beta.59`' <<<"$output"
grep -Fq 'VERSION file: `0.1.0-beta.59`' <<<"$output"
grep -Fq 'Release artifact checksum match: `YES`' <<<"$output"
grep -Fq 'Browser: `Chromium test`' <<<"$output"
grep -Fq 'Theme: `dark`' <<<"$output"
grep -Fq 'Privacy mode: `safe metadata only`' <<<"$output"
grep -Fq 'IP addresses, database host/name/user/password' <<<"$output"

if grep -Eq 'DBHost|DBPassword|password[[:space:]]*=' <<<"$output"; then
  echo 'Sensitive configuration marker unexpectedly present in output.' >&2
  exit 1
fi

echo 'Field evidence helper tests passed.'
