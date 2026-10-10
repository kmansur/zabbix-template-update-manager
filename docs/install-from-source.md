# Local Git checkout installation (Zabbix 7 and 8)

**Status:** laboratory beta only; not a production approval. The local-source installer is new-install-only. The existing quickinstall scripts remain separate distribution paths.

## Requirements

- Zabbix 7.x or 8.x frontend installed locally on Linux, with a recognizable frontend modules directory
- root for installation; PHP CLI for manifest validation; PHP-FPM/web runtime user known
- `git`, `bash`, `install`, `cp`, `mv`, `find`, `sed` and standard GNU/Linux commands
- Review the repository revision/tag before executing a script as root. `main` is a moving development branch; use the immutable tagged, checksummed beta for formal reproducibility

### Simple installation: three commands

Keep the full Git repository outside the web root. On a fresh Zabbix 7.x or 8.x frontend:

```bash
cd /usr/local/src
git clone https://github.com/kmansur/zabbix-template-update-manager.git
sudo bash zabbix-template-update-manager/install.sh
```

The installer identifies the frontend modules directory and PHP-FPM account when they are unambiguous. Review the source revision before invoking a root-owned script. Use an immutable tagged release and validate its checksums when producing formal field-test evidence; `main` is a moving branch.

Optional **read-only diagnostic**:

```bash
sudo bash zabbix-template-update-manager/install.sh --check
```

When a module already exists, `--check` reports it without altering anything; an actual installation still refuses replacement. For a special environment with multiple frontends or PHP-FPM users, pass the appropriate overrides:

```bash
sudo bash zabbix-template-update-manager/install.sh \
  --modules-dir /usr/share/zabbix/ui/modules \
  --php-user www-data
```

Zabbix 7 deployments may use `/usr/share/zabbix/modules`; the actual installation layout controls the choice, not the major version alone.

## What install.sh does

1. Checks that the source contains `manifest.json`, `Module.php`, `VERSION`, `actions/`, `assets/`, `src/` and `views/`, and rejects source symlinks. It verifies that `manifest.json` agrees with `VERSION`.
2. Resolves the actual Zabbix frontend directory and verifies version 7.x/8.x, stopping when verification is inconclusive; detects the PHP-FPM user or asks for `--php-user`.
3. **Refuses to replace an existing installation**, including the development checkout currently deployed in the laboratories. In `--check` mode, existing installation is reported as a non-destructive diagnostic.
4. Uses the existing `tools/ztum-runtime-setup.sh` helper to create/validate private `0700` runtime directories under `/var/lib/zabbix-template-update-manager/` for `backups`, `offline`, `locks` and `batches`. It does not reset or remove stored backup, policy, history or batch data.
5. Stages and installs only `Module.php`, `manifest.json`, `VERSION`, `actions/`, `assets/`, `src/`, `views/` as root-owned, web-readable files (directories `0755`, files `0644`). Git metadata, test harnesses, scripts, docs and release machinery are not copied.
6. Does not modify Nginx, Apache, PHP-FPM, the Zabbix database or template configuration and does not restart services.

In the Zabbix UI, sign in as **Super Admin**, open **Administration → General → Modules**, select **Scan directory**, enable **Template Update Manager**, then open **Data collection → Template updates**.

## Check installation boundaries

```bash
sudo find /usr/share/zabbix/ui/modules/zabbix-template-update-manager \
  -maxdepth 1 -mindepth 1 -printf '%f\n' | sort
sudo bash /usr/local/src/zabbix-template-update-manager/tools/ztum-runtime-setup.sh \
  --check --user www-data
```

The installed module root should contain only `Module.php`, `manifest.json`, `VERSION`, `actions`, `assets`, `src` and `views`. Private runtime state remains outside the web tree. No `.git`, `tests`, `tools`, or `docs` directory should be present inside the module.

## Existing cloned installations and updates

The installer **will refuse** to act if `.../modules/zabbix-template-update-manager` already exists. Do not run `rm -rf` or overwrite it just to make the installer pass. The laboratories Zabbix 7/8 presently use in-place Git checkouts; migrate them only during a dedicated maintenance/upgrade procedure that first captures installed revision, records/backs up the runtime state and verifies rollback. Running this new-install script is **not** an upgrade procedure.

For this prerelease, there is no automatic installer-to-installer update and no download/write path in the ZTUM frontend. Reproducible, checksum-verified packaging and upgrade tests remain release gates.

## Security notes

- Review scripts before running them as root; avoid piping unverified remote content into a shell.
- An explicit PHP-FPM user is safer when multiple pools use different accounts.
- The installed code must not be writable by the PHP-FPM account; runtime write access is restricted to `/var/lib/zabbix-template-update-manager`.
- The `--check` mode reads source, frontend files and account configuration; it does not install the module.
- The PHP-FPM user must not be `root`. Keep the web server's normal static-file protections in place.
