#!/usr/bin/env bash
set -euo pipefail

tmp="$(mktemp -d)"
trap 'sudo rm -rf "$tmp"' EXIT
user="$(id -un)"

bash -n tools/ztum-runtime-setup.sh
sudo tools/ztum-runtime-setup.sh --apply --user "$user" --base "$tmp/state"
tools/ztum-runtime-setup.sh --check --user "$user" --base "$tmp/state"

test "$(stat -c '%a' "$tmp/state/backups")" = "700"
test "$(stat -c '%U' "$tmp/state/backups")" = "$user"

echo "Runtime setup helper tests passed."
