#!/usr/bin/env bash
set -euo pipefail

tmp="$(mktemp -d)"
trap 'sudo rm -rf "$tmp"' EXIT
user="$(id -un)"

bash -n tools/ztum-runtime-setup.sh
sudo tools/ztum-runtime-setup.sh --apply --user "$user" --base "$tmp/state"
tools/ztum-runtime-setup.sh --check --user "$user" --base "$tmp/state"

for dir in backups offline locks; do
  test "$(stat -c '%a' "$tmp/state/$dir")" = "700"
  test "$(stat -c '%U' "$tmp/state/$dir")" = "$user"
done

echo "Runtime setup helper tests passed."
