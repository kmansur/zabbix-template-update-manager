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
└── locks/
```

All directories are created as mode `0700` and owned by the resolved runtime account. The script refuses symbolic-link targets and does not edit PHP-FPM configuration.

The controlled-operation lock defaults to a private directory below the PHP temporary directory unless `ZTUM_LOCK_DIR` is configured. The persistent `locks/` directory is created so deployments can explicitly point the lock there if desired.

For an air-gapped bundle, copy the verified bundle below `offline/` (or another private directory) and configure the PHP-FPM environment as documented in [offline-mode.md](offline-mode.md).
