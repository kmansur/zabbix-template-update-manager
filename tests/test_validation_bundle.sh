#!/usr/bin/env bash
set -euo pipefail

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

bash -n tools/ztum-validation-bundle.sh
bash tools/ztum-validation-bundle.sh   --module-dir "$(pwd)"   --output "$tmp/evidence"   --browser "CI Chromium"   --theme "dark"

for required in environment.md reviewer-checklist.md module-files.sha256 SHA256SUMS; do
  test -s "$tmp/evidence/$required"
done

(
  cd "$tmp/evidence"
  sha256sum -c SHA256SUMS
)

grep -q 'ZTUM version:' "$tmp/evidence/environment.md"
grep -q 'interrupted/resumed batch behavior' "$tmp/evidence/reviewer-checklist.md"

echo "External validation bundle helper tests passed."
