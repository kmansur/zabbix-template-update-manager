# Backup and rollback foundation

## Purpose

Any future write-enabled template update must have a verified copy of the currently installed template before a configuration write is ever allowed.

The current milestone remains read-only with respect to Zabbix configuration. It exposes a deliberately narrow rollback-backup action for templates that reach the `candidate_for_backup` readiness state, then re-reads the stored artifact and compares it with a fresh export of the installed template.

The implemented backup path performs only:

1. native Zabbix template export;
2. local persistent backup-file creation;
3. stored-artifact integrity validation;
4. fresh native export of the current installed template;
5. exact byte-count and SHA-256 comparison.

It does **not** modify, import, replace or delete Zabbix configuration.

## Native export source

Backups and current-state verification are produced from the installed Zabbix instance through the native read-only API method:

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

- creates template-specific private directories with mode `0700` on Unix;
- writes source and manifest files with mode `0600` on Unix;
- uses atomic temporary-file + rename writes;
- validates source byte count and SHA-256 before persistence;
- rejects invalid template IDs and UUIDs;
- applies a hard 20 MiB export size limit;
- removes the YAML artifact if manifest persistence fails;
- uses numeric template IDs, never template names, in filesystem paths;
- fails if the persistent destination cannot be created, secured or written.

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

## Backup inventory and integrity inspection

The repository can inspect a bounded set of recent artifacts for one numeric template ID. Runtime verification currently examines at most the newest 10 artifacts; the repository has a hard maximum inspection limit of 50.

Every inspected manifest is checked conservatively before its backup is considered usable:

- manifest filename must match the generated backup naming convention;
- manifest must be a regular readable file, not a symlink;
- manifest is limited to 1 MiB;
- JSON must decode successfully;
- manifest schema version must be supported;
- recorded template ID must match the requested template ID;
- recorded UUID and identity metadata must be valid;
- export format must be YAML;
- source filename must be a basename only and must share the manifest stem;
- recorded byte count must be within the export size limit;
- recorded SHA-256 must be valid;
- YAML source must be a regular readable file, not a symlink;
- actual YAML byte count must equal the manifest;
- actual YAML SHA-256 must equal the manifest;
- on Unix, source and manifest files must have exact mode `0600`.

An invalid newest artifact is not silently bypassed in favor of an older valid backup. The verification path fails closed and reports the newest artifact as invalid.

## Matching the current installed template

Artifact integrity alone is not enough. A perfectly intact backup may still be stale if the installed template changed after the backup was created.

`TemplateBackupVerificationService` therefore:

1. inspects the newest stored rollback artifact;
2. rejects an invalid newest artifact;
3. checks the artifact identity against the current template metadata;
4. performs a fresh one-template `configuration.export`;
5. compares the fresh export byte count and SHA-256 with the stored artifact.

Current verification states are:

- `no_backup` — no rollback artifact exists for the template;
- `repository_unavailable` — the persistent repository cannot be read safely;
- `latest_invalid` — the newest artifact failed integrity validation;
- `current_mismatch` — the newest intact artifact does not represent the current installed export;
- `current_match` — the newest artifact is intact and exactly matches a fresh current export.

Only `current_match` satisfies the current rollback prerequisite.

## Readiness progression

When all comparison/risk evidence is complete and technical review priority is `none` or `low`, readiness first becomes:

```text
candidate_for_backup
```

If a persistent artifact then verifies as `current_match`, readiness advances to:

```text
backup_verified
```

`backup_verified` means only that the rollback artifact matches the exact currently installed template export. It does **not** mean the upstream update is safe, approved or authorized.

The readiness result continues to contain:

```text
write_enabled = false
```

and the next step is explicitly:

```text
await_write_enabled_milestone
```

## Why backup is separate from update

A successful and verified backup does not mean an update is safe. The intended future sequence remains:

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
re-read and validate stored artifact
        |
fresh current export fingerprint match
        |
backup_verified
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
- bounded stored-backup inventory;
- manifest/path/size/permission/SHA-256 validation;
- fail-closed handling when the newest artifact is invalid;
- fresh current-template export after stored-artifact validation;
- exact current-export fingerprint comparison;
- `backup_verified` readiness state when the newest artifact exactly matches current installed state;
- native comparison-page verification summary;
- unit tests for export contract, artifact integrity, tamper detection, current-state verification and frontend backup-action security contract.

Not implemented yet:

- a dedicated historical backup browser/download UI;
- retention/cleanup policy;
- configurable backup root directory;
- rollback import;
- Zabbix configuration write/import operation;
- explicit update confirmation;
- post-update validation.

A future write-enabled milestone must re-evaluate every prerequisite server-side and must not trust the presence of a button, a previous page state or a historical `backup_verified` result.
