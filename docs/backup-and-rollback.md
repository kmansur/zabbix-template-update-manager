# Backup and rollback foundation

## Purpose

Any future write-enabled template update must have a verified copy of the currently installed template before a configuration write is ever allowed.

The current milestone remains read-only with respect to Zabbix configuration. It now exposes a deliberately narrow rollback-backup action for templates that reach the `candidate_for_backup` readiness state. That action performs only:

1. native Zabbix template export;
2. local persistent backup-file creation;
3. SHA-256 and byte-count integrity recording.

It does **not** modify, import, replace or delete Zabbix configuration.

## Native export source

Backups are produced from the installed Zabbix instance through the native read-only API method:

```text
API::Configuration()->export()
```

The export contract is intentionally narrow:

```text
format: yaml
prettyprint: true
options:
  templates:
    - <selected template ID>
```

Exactly one numeric template ID is requested. The returned bytes are preserved unchanged and fingerprinted with SHA-256.

## Local backup artifact

Runtime backups are persistent by default and are stored below:

```text
/var/lib/zabbix-template-update-manager/backups/
```

Each backup consists of two private files:

```text
/var/lib/zabbix-template-update-manager/backups/
  template-<templateid>/
    backup-<UTC timestamp>-<sha prefix>.yaml
    backup-<UTC timestamp>-<sha prefix>.json
```

The YAML file is the exact native Zabbix export. The JSON manifest records:

- creation time;
- template ID;
- template UUID when available;
- technical and visible template names;
- installed vendor version;
- export format;
- exact byte count;
- SHA-256 fingerprint;
- YAML artifact basename.

Template names are metadata only and are never used to construct filesystem paths.

There is intentionally no silent fallback to `/tmp` or another ephemeral directory. If persistent storage is not prepared or writable, backup creation fails closed and the administrator receives an error.

## Preparing persistent storage

The directory must be writable by the operating-system account that runs the Zabbix frontend PHP process.

For a typical Debian/Ubuntu nginx or Apache installation using the `www-data` PHP user:

```bash
sudo install -d -o www-data -g www-data -m 0700 \
  /var/lib/zabbix-template-update-manager/backups
```

Before using that command in production, confirm the actual PHP-FPM or Apache runtime account. Other distributions may use accounts such as `apache` instead of `www-data`.

Do not make the directory world-writable. In particular, do not use mode `0777` as a workaround for permission problems.

Recommended verification:

```bash
sudo stat -c '%U %G %a %n' /var/lib/zabbix-template-update-manager/backups
```

The expected ownership is the frontend PHP runtime user/group and the expected directory mode is `700`.

## File safety

Template configuration backups must be treated as sensitive operational data.

The repository therefore:

- creates template-specific private directories with requested mode `0700`;
- writes source and manifest files with requested mode `0600`;
- uses atomic temporary-file + rename writes;
- validates source byte count and SHA-256 before persistence;
- rejects invalid template IDs and UUIDs;
- applies a hard export size limit;
- removes the YAML artifact if manifest persistence fails;
- uses numeric template IDs, never template names, in filesystem paths;
- fails if the persistent destination cannot be created or written.

## Frontend backup action

The comparison page exposes **Create rollback backup** only when the read-only readiness gate reports:

```text
candidate_for_backup
```

The frontend form:

- uses HTTP POST explicitly;
- carries the native Zabbix CSRF token for `ztum.template.backup`;
- submits the selected numeric template ID;
- is processed only for Zabbix administrators and super administrators.

The controller intentionally leaves native CSRF validation enabled. It reloads the selected template through the Zabbix API before exporting it and then delegates export/persistence to the backup services.

The action creates a local rollback artifact only. It does not enable any template update operation.

## Why backup is separate from update

A successful backup does not mean an update is safe. The intended future sequence is:

```text
current upstream comparison
        |
historical baseline
        |
three-way analysis
        |
risk + impact review
        |
readiness gate
        |
backup current installed template
        |
verify backup fingerprint against current installed export
        |
explicit administrator confirmation
        |
controlled configuration write
        |
post-write validation
        |
retain backup for rollback
```

The project will not collapse these steps into a single automatic replacement operation.

## Current boundary

Implemented now:

- native one-template YAML export service;
- SHA-256 and byte-count verification;
- persistent private local backup repository;
- deterministic backup manifest;
- `candidate_for_backup`-gated native frontend button;
- POST + native Zabbix CSRF protection;
- administrator/super-administrator permission check;
- redirect back to the comparison page with success/error messaging;
- unit tests for export contract, artifact integrity and frontend backup-action security contract.

Not implemented yet:

- listing or browsing stored backups in the frontend;
- re-reading and verifying a stored backup before a future update;
- retention/cleanup policy;
- configurable backup root directory;
- rollback import;
- Zabbix configuration write/import operation;
- explicit update confirmation;
- post-update validation.

The next safe milestone is backup inventory and verification. A stored artifact must be revalidated before it can ever become part of a future write-enabled workflow.
