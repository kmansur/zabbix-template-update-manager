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
12. Fail closed when runtime, upstream identity, source provenance or version semantics cannot be verified safely.

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
              Native Zabbix inventory view
                         |
                  official template
                         |
                         v
                 TemplateCompare
                         |
            +------------+-------------+
            |                          |
            v                          v
UpstreamTemplateSourceRepository    local Zabbix state
            |                          |
            v                          |
native Zabbix YAML reader              |
            |                          |
            v                          |
UpstreamTemplateDocumentService        |
            |                          |
            +------------+-------------+
                         |
                         v
          API::Configuration()->importcompare()
                         |
                         v
              ImportCompareSummary
                         |
                         v
          ContentComparisonClassifier
                         |
                         v
              Native comparison view
```

### TemplateRepository

Retrieves the minimum read-only local dataset required by the inventory:

- template ID;
- technical and visible name;
- UUID;
- vendor name/version;
- template groups;
- direct host-link count.

It also supports retrieving one selected visible template by ID for the comparison detail page.

It does not access the database directly.

### TemplateInventoryService

Normalizes local records and produces inventory summary information.

`vendor_name = Zabbix` is only vendor metadata. It does not classify a template as official.

### Upstream index generation

A GitHub Actions workflow builds compact indexes from the canonical Zabbix Git repository.

The generator parses official YAML exports and stores the metadata needed by the comparison stages:

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

Before publishing refreshed indexes, the workflow fetches an official YAML through the canonical raw-file endpoint using an exact immutable commit from the generated index. This network smoke test verifies that the runtime source URL pattern remains usable.

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
- strict schema/line/commit/UUID/path/hash validation;
- source paths must remain below `templates/`, end in `.yaml` and cannot contain `.` or `..` traversal segments;
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

A version result is metadata-only. `update_available` does not imply that importing the upstream template is safe.

### UpstreamTemplateSourceRepository

Retrieves the official template source only after an authoritative UUID match.

The source URL is constructed internally from two already validated values:

- the exact 40-character Git commit recorded by the upstream index;
- one of the official YAML paths recorded for that UUID.

Security constraints:

- fixed `git.zabbix.com` host;
- no user-provided repository URL, ref or path;
- strict path validation including explicit rejection of `.` and `..` segments;
- TLS peer/hostname verification;
- HTTPS-only redirect policy when cURL supports protocol restriction;
- maximum three redirects;
- the effective redirect destination must still be `git.zabbix.com`;
- 10 MiB response limit;
- short connection/request timeouts.

If an upstream UUID maps to more than one distinct official content hash, content retrieval fails closed rather than selecting a content variant silently.

### Native YAML reader

The fetched YAML is parsed with Zabbix's own `CImportReaderFactory`/YAML reader instead of introducing a second runtime YAML parser dependency.

This keeps runtime parsing aligned with the installed Zabbix frontend's import semantics.

### UpstreamTemplateDocumentService

Official YAML bundles can contain several templates. The comparison must not feed unrelated sibling templates to `configuration.importcompare`.

The document service therefore:

1. finds exactly one template with the expected UUID;
2. verifies UUID, visible/technical name and vendor metadata against the validated index;
3. retains only the selected template;
4. retains only top-level template-group definitions referenced by that template;
5. retains host-group definitions referenced by discovered host prototypes;
6. emits a minimal JSON Zabbix export for import comparison.

Identity mismatch, missing references or duplicate target UUIDs fail closed.

### TemplateImportCompareService

Runs the native read-only API preview:

```text
API::Configuration()->importcompare()
```

The service enables create/update comparison for the selected template and its supported child entities. `deleteMissing` is enabled where the Zabbix import rules support it so local-only entities can appear as **preview removals**.

This does not call `configuration.import` and does not mutate Zabbix configuration.

The current preview scope includes:

- template/template groups and referenced host groups;
- template dashboards;
- template linkage;
- items;
- discovery rules;
- triggers;
- graphs;
- web scenarios;
- value maps.

Hosts are intentionally outside this comparison input.

### ImportCompareSummary

The raw `configuration.importcompare` structure is recursively reduced to:

- total added entities;
- total updated entities;
- total removed entities;
- total changes;
- counts grouped by entity type.

`before` and `after` snapshots are not recursively counted as independent changes.

### ContentComparisonClassifier

Interprets an import-comparison result together with the version state.

Current states:

- `matches_current_upstream`;
- `local_modifications_detected`;
- `preview_against_newer_upstream`;
- `historical_baseline_required`;
- `not_available`.

The key safety rule is semantic, not just technical:

- `current` + zero differences → content matches current upstream;
- `current` + differences → local modifications are detected;
- `update_available` → differences are only a preview against newer upstream content;
- ambiguous/missing/newer installed version states → historical baseline required.

An outdated installed template must **not** be labeled locally modified merely because it differs from today's upstream template. Those differences can be legitimate upstream evolution.

### Historical baseline and future three-way comparison

The next comparison stage must retrieve an official historical template baseline matching the installed `vendor.version` when possible.

That enables the intended three-way model:

```text
A = official historical baseline matching installed version
B = installed local template
C = current official upstream template

A vs B -> local customization
A vs C -> upstream evolution
B vs C -> proposed update result
```

Only after those three relationships are known should the project classify merge conflicts or update risk for outdated customized templates.

### Frontend

The inventory view is responsible for:

- inventory summary;
- upstream source metadata;
- identity summary;
- official version summary;
- per-template identity and version state;
- link to read-only content comparison for eligible official templates.

The comparison view is responsible for:

- installed/upstream version context;
- immutable source path/commit context;
- content classification;
- added/updated/removed summary;
- changes grouped by entity type;
- explicit explanation of the classification limit.

Only Zabbix administrators and super administrators can open the comparison action because `configuration.importcompare` applies the same import-related role access checks used by Zabbix itself.

All views use native Zabbix components and do not add a UI framework.

### Comparison engine — next responsibilities

- historical upstream baseline lookup by installed vendor version;
- verification that the historical candidate has the same stable UUID identity;
- three-way normalization and comparison;
- granular field-level diff presentation;
- local/upstream change separation;
- conflict detection;
- impact analysis.

### Risk analyzer

Future classifications:

- low;
- medium;
- high;
- conflict.

No risk classification is made solely from vendor version or current-upstream import preview.

### Update engine

Not enabled during the read-only phase.

Future responsibilities:

- backup/export;
- reviewed update;
- explicit confirmation;
- controlled import;
- post-import validation;
- rollback.
