# Air-gapped / offline upstream mode

Template Update Manager can run without internet egress from the Zabbix frontend by using a verified local upstream bundle.

## Security model

Offline mode does not relax identity or write gates.

- the bundle has an Ed25519-signed canonical manifest containing SHA-256 file hashes;
- the upstream index is still schema/UUID/path/hash validated;
- current source bytes are still checked against the per-path upstream index fingerprint;
- history/source records are read only from internally constructed safe paths;
- `ZTUM_OFFLINE_ONLY=1` prevents network fallback when a required bundle artifact is missing;
- missing or tampered offline evidence fails closed.

## Build a bundle on a connected system

Requirements:

- this ZTUM repository;
- a local checkout of the canonical Zabbix Git repository containing the commit recorded in the selected index;
- one or more validated `indexes/<major.minor>.json` files.

Example:

```bash
python tools/build_offline_bundle.py \
  --zabbix-repo /srv/git/zabbix \
  --index /tmp/indexes/7.0.json \
  --index /tmp/indexes/8.0.json \
  --output /tmp/ztum-offline \
  --signing-private-key /secure/ztum/offline-ed25519-private.pem \
  --key-id ztum-offline-2026
```

The output contains:

```text
manifest.json
indexes/<line>.json
sources/<commit>/templates/.../*.yaml
history/<commit>/<sha256(path)>.json
renames/<commit>/<sha256(path)>.json
```

The generator validates current raw YAML bytes against the index before including them and refuses to create an unsigned bundle. Keep the private key on the connected build system only.

Before transferring a bundle to the isolated frontend, verify it independently
with the trusted public key. This checks the Ed25519 signature, every manifest
hash, missing files, symlinks and unlisted files without loading the bundle in
the Zabbix frontend:

```bash
python tools/verify_offline_bundle.py \
  --bundle /tmp/ztum-offline \
  --public-key /secure/ztum/offline-ed25519-public.pem \
  --key-id ztum-offline-2026
```

The verifier must print `PASS` before the bundle is copied. A failed or
tampered verification is a transfer failure and must not be bypassed.

Historical bundle generation records exact Git rename transitions and fails closed on ambiguous merge-parent mappings. It never guesses a historical path.

For a bundle intended to support an authoritative historical acceptance test,
add `--require-complete-history`. The generator then refuses to produce a
bundle whenever any requested path exceeds the selected history limit instead
of silently marking that record truncated. Increase `--history-limit` only
after reviewing the repository size and memory/time budget.

## Install on the isolated frontend

Copy the bundle to a private directory, for example:

```text
/var/lib/zabbix-template-update-manager/offline
```

The runtime setup helper can prepare the directory:

```bash
sudo tools/ztum-runtime-setup.sh --apply --user www-data
```

Then configure the PHP-FPM pool environment:

```ini
env[ZTUM_OFFLINE_BUNDLE_DIR] = /var/lib/zabbix-template-update-manager/offline
env[ZTUM_OFFLINE_ONLY] = 1
env[ZTUM_OFFLINE_BUNDLE_KEY_ID] = ztum-offline-2026
env[ZTUM_OFFLINE_BUNDLE_PUBLIC_KEY] = <base64-raw-ed25519-public-key>
```

Reload PHP-FPM after changing the pool configuration.

With offline-only mode enabled, an absent index/source/history record produces a blocked/error state instead of attempting internet access.
If the trusted key, key ID or manifest signature is missing or invalid, the bundle is rejected before any index, source or history data is used.

## Hybrid mode

Set only `ZTUM_OFFLINE_BUNDLE_DIR` and omit `ZTUM_OFFLINE_ONLY` to prefer verified local bundle data while allowing remote fallback for artifacts that are not in the bundle.

For production-isolated networks, offline-only mode is recommended because it proves that no runtime egress is required.
