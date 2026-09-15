# Architecture

## Zabbix Template Update Manager

The project is designed as a native Zabbix frontend module.

## Design principles

1. Do not modify Zabbix core files.
2. Preserve the native Zabbix UI and UX.
3. Prefer Zabbix native frontend components.
4. Keep the initial implementation read-only.
5. Never modify templates during discovery or comparison.
6. Identify official templates primarily by UUID.
7. Treat vendor metadata as metadata, not proof of upstream identity.
8. Detect local modifications before proposing updates.
9. Use Zabbix API capabilities whenever possible.
10. Support Zabbix 7.x and 8.x.
11. Design repository providers independently from the comparison engine.
12. Fail closed when runtime or upstream identity cannot be verified safely.

## Current architecture

```text
Frontend action (TemplateList)
          |
          +------------------------------+
          |                              |
          v                              v
TemplateRepository             UpstreamIndexRepository
          |                              |
          v                              v
API::Template()->get()        compact upstream JSON index
          |                              |
          v                              |
TemplateInventoryService                |
          |                              |
          +--------------+---------------+
                         |
                         v
                  UpstreamMatcher
                         |
                         v
              Native Zabbix CTableInfo view
```

### TemplateRepository

Retrieves the minimum read-only local dataset required by the inventory:

- template ID;
- technical and visible name;
- UUID;
- vendor name/version;
- template groups;
- direct host-link count.

It does not access the database directly.

### TemplateInventoryService

Normalizes local records and produces inventory summary information.

`vendor_name = Zabbix` is only vendor metadata. It does not classify a template as official.

### Upstream index generation

A GitHub Actions workflow builds compact indexes from the official `zabbix/zabbix` GitHub mirror.

The generator parses official YAML exports and stores only identity metadata needed by the module:

- template UUID;
- technical name;
- visible name;
- vendor name;
- vendor version;
- source YAML path.

Each index also records:

- Zabbix release line;
- source Git ref;
- exact source commit;
- source commit date;
- canonical Zabbix Git URL;
- official GitHub mirror URL.

The index builder rejects duplicate or malformed template UUIDs.

### Source-ref policy

For a requested Zabbix release line:

1. prefer `release/<major.minor>` if the branch exists;
2. otherwise use the latest `<major.minor>.*` maintenance tag;
3. for 8.0 prereleases only, allow `master` when `include/version.h` confirms major/minor 8.0;
4. otherwise fail index generation.

This avoids silently comparing against an unrelated development branch.

### UpstreamIndexRepository

The runtime frontend retrieves one compact JSON index from the project's `upstream-index` branch.

Runtime rules:

- HTTPS only;
- fixed repository URL, not user-provided;
- 10-second request timeout;
- maximum index size 5 MiB;
- strict schema/line/UUID validation;
- 15-minute local cache;
- stale-cache fallback when refresh fails;
- no token or Git client required on the Zabbix frontend host.

If no valid index is available, upstream identity fails closed while the local template inventory remains usable.

### UpstreamMatcher

Matches local templates against the upstream map by normalized UUID only.

Current states:

- `official_match`;
- `not_found`;
- `no_uuid`;
- `invalid_uuid`;
- `repository_unavailable`.

It deliberately does not decide whether an update is available. Version comparison and three-way content analysis are separate later milestones.

### Frontend

Responsible for:

- inventory summary;
- upstream source metadata;
- identity summary;
- per-template identity state;
- future filters, diff, risk and configuration.

The view uses native Zabbix components and does not add a UI framework.

### Comparison engine

Future responsibilities:

- version comparison;
- `configuration.importcompare` integration;
- local modification detection;
- upstream modification detection;
- conflict detection;
- three-way comparison where required.

### Risk analyzer

Future classifications:

- low;
- medium;
- high;
- conflict.

### Update engine

Not enabled during the read-only phase.

Future responsibilities:

- backup/export;
- reviewed update;
- explicit confirmation;
- controlled import;
- post-import validation;
- rollback.
