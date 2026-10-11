# Local Git checkout installation (Zabbix 7 and 8)

**Status:** laboratory beta only; not a production approval. Without flags, the local-source installer is new-install-only; explicit `--upgrade`, `--reinstall` and `--rollback BACKUP_ID` operations are available for laboratory testing. The root-level `install.sh` is the only active installer; retired quickinstall scripts are not supported on `main`.

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
4. Uses integrated `install.sh` runtime setup to create/validate private `0700` directories under `/var/lib/zabbix-template-update-manager/` for `backups`, `offline`, `locks`, `batches` and `cache`. It does not reset or remove stored backup, policy, history or batch data.
5. Stages and installs only `Module.php`, `manifest.json`, `VERSION`, `actions/`, `assets/`, `src/`, `views/` as root-owned, web-readable files (directories `0755`, files `0644`). Git metadata, test harnesses, scripts, docs and release machinery are not copied.
6. Does not modify Nginx, Apache, PHP-FPM, the Zabbix database or template configuration and does not restart services.

In the Zabbix UI, sign in as **Super Admin**, open **Administration → General → Modules**, select **Scan directory**, enable **Template Update Manager**, then open **Data collection → Template updates**.

## Check installation boundaries

```bash
sudo find /usr/share/zabbix/ui/modules/zabbix-template-update-manager \
  -maxdepth 1 -mindepth 1 -printf '%f\n' | sort
sudo bash /usr/local/src/zabbix-template-update-manager/install.sh \
  --runtime-check --php-user www-data
```

The installed module root should contain only `Module.php`, `manifest.json`, `VERSION`, `actions`, `assets`, `src` and `views`. Private runtime state remains outside the web tree. No `.git`, `tests`, `tools`, or `docs` directory should be present inside the module.

## Controlled upgrade, same-version reinstall and code rollback (laboratory only)

Use `sudo bash install.sh --reinstall` only to deploy changed code with an **identical** `VERSION` in a laboratory. The same root-private SHA-256 backup, atomic code replacement and rollback procedure applies. A reinstall refuses a different version and refuses identical installed/source files. Do not use it to bypass release versioning in production.

## Upgrade and rollback precautions

**Laboratory only:** `--upgrade` and `--rollback` have not yet passed disposable end-to-end failure injection. Do not use these operations on production until that validation is complete. Schedule a maintenance window, suspend ZTUM operations, verify no active batches or imports, and capture a separate backup of `/var/lib/zabbix-template-update-manager` and of the Zabbix database as appropriate before upgrading. A code rollback cannot reverse Zabbix template changes.

The update source is the current local checkout; no frontend request or GitHub release checker writes the filesystem. Check and pin the intended source commit before running:

```bash
cd /usr/local/src/zabbix-template-update-manager
git pull --ff-only origin main
git rev-parse HEAD
cat VERSION
sudo bash install.sh --upgrade
```

The updater requires an already-installed module and a **strictly newer** source version. It checks the source, stages only runtime components, serializes installer operations with an exclusive `flock`, copies the entire original module tree to a private backup under `/var/backups/zabbix-template-update-manager/BACKUP_ID/module`, records a SHA-256 inventory and verifies it before activation. The installed code is root-owned and read-only to PHP-FPM. It does not rewrite or delete the private runtime directory.

The command prints an exact **BACKUP_ID**, for example `20261010T180000Z-8a1b2c3d`. Preserve this identifier and operational logs. For a requested code rollback, run:

```bash
sudo bash install.sh --rollback BACKUP_ID
```

Before changing the installed module, rollback now prints an explicit **Rollback preflight** result: it verifies the backup ID, root-private directory, non-empty SHA-256 inventory, and exact file inventory (including detection of missing, modified or unexpected files), then validates the staged module. Any integrity failure aborts the operation without replacing installed code. This verification is mandatory and cannot be bypassed. On success it makes a fresh backup of the version being replaced and restores the selected code. Refuse to continue if checksum verification fails. The code activation uses two renames in the same modules filesystem; there can be a short interval with no active directory, so do not execute it while users are operating the ZTUM frontend. On failure, the updater attempts automatic code recovery and preserves the prior copy if recovery cannot complete. Verify the active version and native interface after any change; inspect PHP-FPM opcode caching if an old version remains visible.

Existing clones inside `modules/` must not be removed manually. They can have extra source-only files; upgrade makes a private copy of the entire old tree before installing only the runtime set. The old `tools/quickinstall*` scripts were retired. A new installation continues to reject overwriting an existing module.

**Limitations:** backup SHA-256 is integrity evidence within a root-controlled local backup, not a cryptographic signature from an external trusted party. The installer does not automatically update Git, drain PHP-FPM connections, inspect in-flight ZTUM jobs, migrate databases or roll back Zabbix configuration imports. The normal Zabbix 8 upgrade → rollback → upgrade cycle, SHA-256 verification and two negative integrity cases were operator-tested; activation-failure injection, Zabbix 7 rollback and independent platform testing remain open.

## Security notes

- Review scripts before running them as root; avoid piping unverified remote content into a shell.
- An explicit PHP-FPM user is safer when multiple pools use different accounts.
- The installed code must not be writable by the PHP-FPM account; runtime write access is restricted to `/var/lib/zabbix-template-update-manager`.
- The `--check` mode reads source, frontend files and account configuration; it does not install the module.
- The PHP-FPM user must not be `root`. Keep the web server's normal static-file protections in place.
