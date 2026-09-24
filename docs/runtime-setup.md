# Runtime directory setup

Template Update Manager deliberately fails closed when persistent rollback storage cannot be created or protected.

The helper below reduces setup errors without guessing the PHP-FPM account or silently using a world-writable fallback:

```bash
sudo tools/ztum-runtime-setup.sh --check
sudo tools/ztum-runtime-setup.sh --apply
sudo tools/ztum-runtime-setup.sh --check
```

If more than one PHP-FPM pool user is detected, the script refuses to choose:

```bash
sudo tools/ztum-runtime-setup.sh --apply --user www-data
```

Default persistent root:

```text
/var/lib/zabbix-template-update-manager
├── backups/
├── offline/
├── locks/
├── update-policy.json       # created on first Never update policy change
└── operation-history.json   # created on first recorded operation
```

`update-policy.json` and `operation-history.json` are created as mode `0600` by the PHP-FPM/web runtime account. The first stores the global UUID-based **Never update** policy; the second stores bounded supplemental operator history. Back up these files with the rest of the ZTUM runtime state if continuity matters across server rebuilds.

All directories are created as mode `0700` and owned by the resolved runtime account. The script refuses symbolic-link targets and does not edit PHP-FPM configuration.

The controlled-operation lock defaults to `/var/lib/zabbix-template-update-manager/locks`. `ZTUM_LOCK_DIR` may override that location for deployments that require shared storage with reliable cross-node `flock()` semantics. The runtime helper creates and validates the default private `locks/` directory.

For an air-gapped bundle, copy the verified bundle below `offline/` (or another private directory) and configure the PHP-FPM environment as documented in [offline-mode.md](offline-mode.md).
