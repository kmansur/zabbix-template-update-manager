# Signed upstream trust

ZTUM supports detached Ed25519 signatures for upstream indexes and offline-bundle manifests. PHP Sodium supplies runtime verification. The private signing key is never stored in the repository.

## Generate a signing key

Run once in a protected administrative environment:

```bash
php tools/generate_signing_key.php
```

Store `secret_key_b64` only in a protected secret manager. For the GitHub index publisher, create the Actions secret `ZTUM_INDEX_SIGNING_SECRET_KEY_B64`.

Configure the corresponding public key in the Zabbix frontend PHP-FPM environment:

```ini
env[ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64] = <public_key_b64>
env[ZTUM_REQUIRE_SIGNED_INDEX] = 1
```

Reload PHP-FPM after the change.

When a trusted key is configured, an absent, modified, wrong-key or invalid signature fails closed. When `ZTUM_REQUIRE_SIGNED_INDEX=1`, a missing trust anchor also fails closed.

## Key ID and rotation

The key ID is deterministic:

```text
ed25519:<first 24 hex characters of SHA-256(public-key-bytes)>
```

Rotation is explicit: publish artifacts with the new private key, deploy the new public key to frontends, verify the new key ID, then retire the old key. Never overwrite a secret key with unrelated bytes while frontends still trust its public key.

## Offline bundles

Build from signed indexes and require a signed manifest:

```bash
export ZTUM_INDEX_SIGNING_SECRET_KEY_B64='<secret>'
python tools/build_offline_bundle.py \
  --zabbix-repo /srv/git/zabbix \
  --index /tmp/indexes/7.0.json \
  --index /tmp/indexes/8.0.json \
  --output /tmp/ztum-offline \
  --require-signature
```

The bundle contains `manifest.sig.json` plus detached index signatures. Runtime verification authenticates the manifest before trusting its file hash map.
