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
12. Fail closed when runtime, upstream identity, source provenance, historical provenance or version semantics cannot be verified safely.
13. Never infer a Git tag from template `vendor.version` metadata.

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
             current-upstream preview
                         |
                 update_available?
                         |
                         v
       UpstreamTemplateHistoryRepository
                         |
                         v
          path-specific canonical history
                         |
                         v
       HistoricalTemplateBaselineService
                         |
             +-----------+-----------+
             |                       |
             v                       v
 historical immutable YAML       stable UUID +
 by commit + same path           vendor.version check
             |                       |
             +-----------+-----------+
                         |
                         v
          isolated historical baseline
                         |
                         v
          API::Configuration()->importcompare()
                         |
                         v
          installed-vs-baseline summary
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

Before publishing refreshed indexes, the workflow verifies both canonical public interfaces used by runtime comparison:

1. raw YAML retrieval through an exact immutable commit;
2. path-specific commit history with `until=<immutable commit>` and rename following.

The history smoke test validates that returned commit IDs are full 40-character hashes. This dependency is verified before runtime historical lookup relies on it.

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

Retrieves official template source by immutable commit and validated path.

The normal current-upstream path uses:

- the exact 40-character Git commit recorded by the upstream index;
- one of the official YAML paths recorded for that UUID.

Historical baseline lookup can call the same repository with a validated commit ID returned by canonical path history.

Security constraints:

- fixed `git.zabbix.com` host;
- no user-provided repository URL or ref;
- strict path validation including explicit rejection of `.` and `..` segments;
- commit IDs must be exactly 40 hexadecimal characters;
- TLS peer/hostname verification;
- HTTPS-only redirect policy when cURL supports protocol restriction;
- maximum three redirects;
- effective redirect destination must still be `git.zabbix.com`;
- 10 MiB response limit;
- short connection/request timeouts.

If a current upstream UUID maps to more than one distinct official content hash, current content retrieval fails closed rather than selecting a content variant silently.

### UpstreamTemplateHistoryRepository

Retrieves path-specific commit history from the canonical Bitbucket REST endpoint:

```text
https://git.zabbix.com/rest/api/1.0/projects/ZBX/repos/zabbix/commits
```

Requests are built internally from validated values and include:

- `path=<validated templates/*.yaml path>`;
- `until=<current immutable upstream commit>`;
- `followRenames=true`;
- bounded pagination.

Repository guarantees:

- fixed canonical host;
- validated immutable `until` commit;
- validated template path;
- maximum 25 records per page;
- maximum 75 path commits per lookup;
- maximum 2 MiB JSON response per page;
- TLS verification;
- canonical-host redirect validation;
- strict JSON response validation;
- every returned commit ID must be a full 40-character hash;
- pagination cursors must advance monotonically.

If the configured scan limit is exhausted while more history exists, the repository reports truncation. Callers must not reinterpret a truncated scan as proof that a historical version does not exist.

### Native YAML reader

Current and historical YAML sources are parsed with Zabbix's own `CImportReaderFactory`/YAML reader instead of introducing a second runtime YAML parser dependency.

This keeps runtime parsing aligned with the installed Zabbix frontend's import semantics.

### UpstreamTemplateDocumentService

Official YAML bundles can contain several templates. The comparison must not feed unrelated sibling templates to `configuration.importcompare`.

For current upstream content, the document service:

1. finds exactly one template with the expected UUID;
2. verifies UUID, visible/technical name and vendor metadata against the validated index;
3. retains only the selected template;
4. retains only top-level template-group definitions referenced by that template;
5. retains host-group definitions referenced by discovered host prototypes;
6. emits a minimal JSON Zabbix export for import comparison.

For historical content, strict current name/version identity cannot be required because those fields may legitimately differ over time. Historical isolation instead requires:

- the same stable UUID;
- the exact requested historical `vendor.version`;
- the expected official vendor name when supplied.

Referenced groups are isolated in the same way as current content.

### HistoricalTemplateBaselineService

Historical baseline lookup is used only for an official template whose current version state is `update_available` and whose installed vendor version is known.

Inputs:

- validated current official path;
- current immutable upstream commit;
- stable template UUID;
- installed `vendor.version`;
- expected vendor name.

Algorithm:

```text
history = path commits from current upstream commit, newest -> oldest

for commit in history, capped at 75:
    fetch same official path at immutable commit
    parse with native Zabbix YAML reader
    locate stable template UUID

    if vendor.version != installed vendor.version:
        continue

    validate official vendor
    isolate historical template + referenced groups
    return newest matching baseline

if complete history exhausted:
    status = not_found
else if safety cap exhausted:
    status = history_limit_reached
```

The newest matching historical candidate is selected deliberately. If several official path commits exist with the same template vendor version, this represents the final known official state of that vendor version before later upstream evolution.

A template `vendor.version` is never translated to a guessed Git tag. For example, `7.0-3` means only template metadata; the resolver proves its corresponding source through actual Git history.

A current path may have been renamed in deeper history. `followRenames=true` keeps the commit history traversal authoritative, but the current implementation still fetches historical raw content using the selected current path. If an older commit cannot be retrieved safely at that path, baseline resolution fails closed rather than guessing an old path. Rename-aware historical raw-path resolution remains future work.

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

The same import-comparison service is used for both current-upstream preview and historical-baseline comparison, keeping Zabbix's native import semantics as the comparison authority.

### ImportCompareSummary

The raw `configuration.importcompare` structure is recursively reduced to:

- total added entities;
- total updated entities;
- total removed entities;
- total changes;
- counts grouped by entity type.

`before` and `after` snapshots are not recursively counted as independent changes.

### ContentComparisonClassifier

Interprets current-upstream comparison together with version state and, when available, historical-baseline comparison.

Current states:

- `matches_current_upstream`;
- `local_modifications_detected`;
- `update_available_no_local_modifications`;
- `update_available_local_modifications`;
- `preview_against_newer_upstream`;
- `historical_baseline_required`;
- `not_available`.

Safety rules:

- `current` + zero current-upstream differences → content matches current upstream;
- `current` + current-upstream differences → local modifications detected;
- `update_available` + resolved historical baseline + zero installed-vs-baseline differences → update available, no local modifications detected;
- `update_available` + resolved historical baseline + installed-vs-baseline differences → update available, local modifications detected;
- `update_available` without a safely resolved historical baseline → current differences remain only an update preview;
- ambiguous/missing/newer installed version states → historical baseline required or not available.

This prevents the central false positive the architecture was designed to avoid: an outdated but unmodified official template is not classified as locally modified merely because it differs from the latest upstream revision.

### Three-way model

Historical baseline lookup establishes two authoritative relationships:

```text
A = official historical baseline matching installed version
B = installed local template
C = current official upstream template

A vs B -> local customization
B vs C -> proposed current-upstream update preview
```

The remaining relationship required for field-level conflict classification is:

```text
A vs C -> upstream evolution at object/field level
```

Once A-vs-B and A-vs-C changes can be normalized into stable object/field identities, the module can detect overlap:

- local-only changes;
- upstream-only changes;
- non-overlapping changes;
- overlapping/conflicting changes.

The current milestone does **not** call an update safe merely because no local changes are present. Operational risk analysis is a separate stage.

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
- immutable current source path/commit context;
- current-upstream update preview;
- historical baseline status and commit when resolved;
- number of history commits examined;
- installed-vs-historical-baseline difference count;
- final conservative content classification;
- explicit explanation of unresolved conflict/risk limits.

Only Zabbix administrators and super administrators can open the comparison action because `configuration.importcompare` applies the same import-related role access checks used by Zabbix itself.

All views use native Zabbix components and do not add a UI framework.

### Comparison engine — next responsibilities

- cache successful historical baseline resolution to avoid repeated remote scans;
- rename-aware historical raw-path resolution;
- canonical object/field normalization for A-vs-B and A-vs-C;
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

No risk classification is made solely from vendor version, current-upstream preview or absence of local customization.

### Update engine

Not enabled during the read-only phase.

Future responsibilities:

- backup/export;
- reviewed update;
- explicit confirmation;
- controlled import;
- post-import validation;
- rollback.
