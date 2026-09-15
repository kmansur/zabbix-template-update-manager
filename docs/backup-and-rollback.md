# Backup and rollback foundation

## Purpose

Any future write-enabled template update must have a verified copy of the currently installed template before `configuration.import` is ever called.

The current milestone implements only the backup foundation. It remains read-only with respect to Zabbix configuration and does not expose an update or rollback action yet.

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

A backup consists of two private files:

```text
backups/
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

## File safety

Template configuration backups must be treated as sensitive operational data.

The repository therefore:

- creates private directories with requested mode `0700`;
- writes source and manifest files with requested mode `0600`;
- uses atomic temporary-file + rename writes;
- validates source byte count and SHA-256 before persistence;
- rejects invalid template IDs and UUIDs;
- applies a hard export size limit;
- removes the YAML artifact if manifest persistence fails.

The default development/runtime location remains below the private project cache area in the system temporary directory. A production packaging milestone can move this to a persistent administrator-configured data directory such as `/var/lib/zabbix-template-update-manager/backups` with explicit ownership and retention policy.

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
backup current installed template
        |
verify backup fingerprint
        |
explicit administrator confirmation
        |
controlled configuration.import
        |
post-import validation
        |
retain backup for rollback
```

The project will not collapse these steps into a single automatic replacement operation.

## Current boundary

Implemented now:

- native one-template YAML export service;
- SHA-256 and byte-count verification;
- private local backup repository;
- deterministic backup manifest;
- unit tests for export contract and backup artifact integrity.

Not implemented yet:

- backup button/action in the frontend;
- retention/cleanup policy;
- persistent production data-directory configuration;
- rollback import;
- `configuration.import`;
- post-update validation.

Those remain later milestones after the read-only comparison path has been validated on real Zabbix installations.