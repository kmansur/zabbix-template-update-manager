# Runtime directory setup

No separate runtime setup helper is needed. The root-run `install.sh` automatically
prepares and validates ZTUM's private directories on a new installation and
during code upgrades. It does not change PHP-FPM, Nginx or the Zabbix database.

For a read-only validation of an installed runtime:

```bash
sudo bash install.sh --runtime-check
```

If PHP-FPM has multiple pool users, specify the correct account explicitly:

```bash
sudo bash install.sh --runtime-check --php-user www-data
```

The default persistent root is:

```text
/var/lib/zabbix-template-update-manager
├── backups/
├── offline/
├── locks/
├── batches/
├── cache/
│   └── historical-sources/  # created privately on demand
├── update-policy.json
└── operation-history.json
```

The installer creates or secures each runtime directory as `0700`, owned
by the selected PHP-FPM account. Existing directories with unexpected ownership,
symlinks, or non-directory paths cause an explicit failure rather than
recursive ownership changes or a fallback to `/tmp`.

Existing runtime files are never removed or rewritten by installation or code
upgrade. Private artifacts should be backed up before server maintenance.
Backups may contain sensitive values included in Zabbix template exports:
treat them as confidential. **Do not redact rollback artifacts** because
they must represent the original state exactly.

The cache stores only catalog/source retrieval artifacts. Offline-only mode
still forbids network fallback. The global lock is local to its filesystem;
deployments with multiple independent frontends need operational serialization.
