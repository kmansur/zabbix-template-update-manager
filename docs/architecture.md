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
12. Fail closed when runtime, upstream identity or version semantics cannot be verified safely.

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
             TemplateVersionComparator
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

A GitHub Actions workflow builds compact indexes from the canonical Zabbix Git repository.

The generator parses official YAML exports and stores the metadata needed by the current and planned comparison stages:

- template UUID;
- technical name;
- visible name;
- vendor name;
- vendor version;
- all official source YAML paths for the UUID;
- SHA-256 hashes for distinct source-content variants.

Each index also records:

- Zabbix release line;
- source Git ref;
- exact source commit;
- source commit date;
- canonical Zabbix Git URL;
- official GitHub mirror URL.

The official repository can contain a shared template UUID in more than one YAML bundle. Repeated UUID definitions are merged only when technical identity and vendor metadata agree. Conflicting identity metadata fails index generation.

### Source-ref policy

For a requested Zabbix release line:

1. prefer `release/<major.minor>` if the branch exists;
2. otherwise use the latest matching `<major.minor>.*` tag, including prerelease tags when they are the available source for that line;
3. for 8.0 prereleases, allow guarded `master` only when `include/version.h` confirms major/minor 8.0;
4. otherwise fail index generation.

The canonical Git repository is used because it retains the complete ref/tag history. This avoids silently comparing against an unrelated development branch or losing historical release lines that are no longer mirrored on GitHub.

### UpstreamIndexRepository

The runtime frontend retrieves one compact JSON index from the project's `upstream-index` branch.

Runtime rules:

- HTTPS only;
- fixed repository URL, not user-provided;
- 10-second request timeout;
- maximum index size 5 MiB;
- strict schema/line/UUID/path/hash validation;
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

Vendor metadata is not used to manufacture an official match.

### TemplateVersionComparator

Runs only after `official_match` and compares installed/upstream `vendor.version` values when both use the numeric `major.minor-revision` format.

Current states:

- `current`;
- `update_available`;
- `installed_newer`;
- `installed_version_missing`;
- `upstream_version_missing`;
- `version_uncomparable`;
- `not_applicable`.

Comparison is numeric by major, minor and revision. Unexpected formats fail closed as `version_uncomparable` instead of being guessed.

A version result is metadata-only. `update_available` does not imply that importing the upstream template is safe because local content modifications are not yet analyzed.

### Frontend

Responsible for:

- inventory summary;
- upstream source metadata;
- identity summary;
- official version summary;
- per-template identity and version state;
- future filters, diff, risk and configuration.

The view uses native Zabbix components and does not add a UI framework.

### Comparison engine

Next responsibilities:

- `configuration.export`/read-only content acquisition as required;
- canonical content normalization;
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
