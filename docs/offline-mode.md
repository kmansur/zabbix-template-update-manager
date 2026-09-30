# Air-gapped / offline upstream mode

Template Update Manager can run without internet egress from the Zabbix frontend by using a verified local upstream bundle.

## Security model

Offline mode does not relax identity or write gates.

- the bundle has a SHA-256 manifest;
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
  --output /tmp/ztum-offline
```

The output contains:

```text
manifest.json
indexes/<line>.json
sources/<commit>/templates/.../*.yaml
history/<commit>/<sha256(path)>.json
```

The generator validates current raw YAML bytes against the index before including them.

Historical bundle generation intentionally stops when the current path can no longer be read at an older commit. That mirrors the current fail-closed rename limitation; it does not guess historical paths.

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
```

Reload PHP-FPM after changing the pool configuration.

With offline-only mode enabled, an absent index/source/history record produces a blocked/error state instead of attempting internet access.

## Hybrid mode

Set only `ZTUM_OFFLINE_BUNDLE_DIR` and omit `ZTUM_OFFLINE_ONLY` to prefer verified local bundle data while allowing remote fallback for artifacts that are not in the bundle.

For production-isolated networks, offline-only mode is recommended because it proves that no runtime egress is required.
