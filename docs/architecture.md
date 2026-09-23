# Architecture

## Template Update Manager

The project is designed as a native Zabbix frontend module.

## Design principles

1. Do not modify Zabbix core files.
2. Preserve the native Zabbix UI and UX.
3. Prefer Zabbix native frontend components.
4. Keep discovery, comparison and preflight configuration-read-only; permit writes only through the reviewed controlled-import boundary.
5. Never modify templates during discovery or comparison.
6. Identify official templates primarily by UUID.
7. Treat vendor metadata as metadata, not proof of upstream identity.
8. Detect local modifications before proposing updates.
9. Use Zabbix API capabilities whenever possible.
10. Support Zabbix 7.x and 8.x.
11. Design repository providers independently from the comparison engine.
12. Fail closed when runtime, upstream identity, source provenance, historical provenance or version semantics cannot be verified safely.
13. Never infer a Git tag from template `vendor.version` metadata.
14. Reuse Zabbix's normalized import-comparison output instead of implementing a second import engine.

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
 current official source          LOCAL Zabbix state
            |                          |
            v                          |
 native YAML reader                    |
            |                          |
            v                          |
 isolate selected UUID                 |
            |                          |
            +------------+-------------+
                         |
                         v
       configuration.importcompare (LOCAL -> UPSTREAM)
                         |
                  current preview
                         |
                 update_available?
                         |
                         v
       UpstreamTemplateHistoryRepository
                         |
                         v
       HistoricalTemplateBaselineService
                         |
                         v
              historical BASE source
                         |
                         v
       configuration.importcompare (LOCAL -> BASE)
                         |
             +-----------+-----------+
             |                       |
             v                       v
      historical diff           current diff
             |                       |
             +-----------+-----------+
                         |
                         v
          ImportCompareEntityExtractor
                         |
                         v
            ThreeWayChangeAnalyzer
                         |
                         v
        BASE / LOCAL / UPSTREAM field states
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

The current-upstream path uses:

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
6. retains relevant top-level trigger/graph definitions;
7. in strict mode, rejects cross-template top-level references;
8. in dependency-aware install/update mode, preserves those references without importing sibling templates and reports the external template-name set;
9. emits a minimal JSON Zabbix export for import comparison.

Dependency-aware update preflight binds the external template-name set into its evidence. Immutable candidate reconstruction must reproduce the same set before a write can proceed.

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

The same service is used twice for an outdated official template with a resolved baseline:

```text
LOCAL -> UPSTREAM
LOCAL -> BASE
```

The installed LOCAL state is therefore the shared pivot of both normalized Zabbix comparisons.

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

This remains a high-level content state. Three-way overlap is a separate layer so the simple status is not overloaded with merge semantics.

### ImportCompareEntityExtractor

`CConfigurationImportcompare` already returns recursive normalized `before` and `after` snapshots and matches structured entities by UUID first. The module consumes that output rather than reparsing raw template content for overlap analysis.

The extractor:

- walks the recursive `added` / `updated` / `removed` tree;
- creates hierarchical entity paths;
- uses normalized UUID as the preferred identity token;
- falls back to object-specific uniqueness fields when UUID is unavailable;
- preserves normalized before/after snapshots;
- rejects duplicate/ambiguous extracted paths;
- marks identities without a reliable UUID/uniqueness key as unresolved.

Example path:

```text
/templates:uuid-<template UUID>/items:uuid-<item UUID>
```

### ThreeWayChangeAnalyzer

The analyzer reconstructs three states from the two native previews:

```text
historical comparison: LOCAL (before) -> BASE (after)
current comparison:    LOCAL (before) -> UPSTREAM (after)
```

For a stable entity path, the two LOCAL snapshots must be equal. A mismatch fails closed as `unresolved`.

When an entity exists in BASE, LOCAL and UPSTREAM, the analyzer compares normalized fields individually. When existence differs, it classifies the entity state/snapshot as a whole instead of inventing values for absent fields.

Classification rules:

```text
BASE == LOCAL, UPSTREAM differs
    -> upstream_only

LOCAL differs, BASE == UPSTREAM
    -> local_only_overwrite

BASE differs, LOCAL == UPSTREAM
    -> converged

BASE, LOCAL and UPSTREAM all differ
    -> conflict

identity/pivot cannot be proven
    -> unresolved
```

`local_only_overwrite` is intentionally separate from `conflict`. Upstream did not independently change that field, but importing the current official template would tend to restore the BASE value and therefore overwrite the local customization.

The analyzer returns:

- counts for every classification;
- total normalized differences;
- affected-entity count;
- bounded detailed rows with BASE / LOCAL / UPSTREAM values;
- overall review status.

Overall status precedence is conservative:

1. conflict detected;
2. unresolved / needs review;
3. local overwrite risk;
4. compatible/converged overlap;
5. upstream-only;
6. no changes.

See `docs/three-way-analysis.md` for detailed semantics.

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
- installed-vs-historical-baseline difference count;
- high-level content classification;
- three-way status and summary;
- granular BASE / LOCAL / UPSTREAM field details;
- explicit explanation that conflict/overwrite classifications are review signals, not automatic update-safety decisions.

Only Zabbix administrators and super administrators can open the comparison action because `configuration.importcompare` applies the same import-related role access checks used by Zabbix itself.

All views use native Zabbix components and do not add a UI framework. Native UI conventions, status tones, theme behavior and automated UI guards are documented in `docs/ui-style.md`.

### Implemented comparison and safety layers

The comparison engine now includes:

- cached historical baseline resolution;
- current and historical `configuration.importcompare` normalization;
- three-way BASE / LOCAL / UPSTREAM analysis;
- entity/field risk analysis;
- direct-host impact reporting;
- readiness evaluation;
- rollback-backup verification;
- standard and reviewed preflight evidence;
- dependency-aware handling of cross-template references;
- request-bounded batch preparation.

Current risk classifications include `none`, `low`, `medium`, `high`, `conflict` and `unknown`. Standard-path eligibility remains a separate decision from technical severity. Medium/high/local-overwrite cases require reviewed paths unless an explicitly tested bounded exception is allowlisted.

No operational safety decision is made solely from vendor version or the absence of a three-way conflict.

### Controlled write engines

Update, installation and rollback writes are implemented.

All configuration writes converge on the single approved boundary:

```text
src/Service/TemplateConfigurationImportService.php
```

Implemented write-path properties include:

- persistent rollback backup and fresh verification before update;
- explicit reviewed override for medium/high technical-risk changes;
- additional acknowledgement for reviewed local-customization overwrite;
- immutable candidate reconstruction;
- fresh preflight and evidence comparison immediately before import;
- request-bounded sequential batch update/install;
- stop-on-first-failure behavior;
- post-update, post-install and post-rollback validation;
- explicit rollback review and fresh recovery backup before restore;
- no automatic retry or automatic rollback after ambiguous writes.

### Remaining comparison/update work

- rename-aware historical raw-path resolution;
- richer inherited/indirect host impact analysis;
- more granular semantic decomposition where stable keys are proven safe;
- filters/drill-down for very large three-way detail sets;
- persistent human-readable operation history/audit UI;
- continued field validation against Zabbix 7.x and 8.x.


## Production-readiness controls added in beta.40

### Air-gapped / offline source provider

Runtime upstream repositories now prefer a configured local `OfflineBundleRepository` when `ZTUM_OFFLINE_BUNDLE_DIR` is set.

The bundle contract is intentionally narrow:

- one private administrator-configured root;
- `manifest.json` schema validation;
- exact SHA-256 verification for every consumed file;
- existing upstream-index schema/UUID/path/hash validation remains mandatory;
- current/historical source paths are derived internally from validated commit/path values;
- no arbitrary filesystem path is accepted from frontend input;
- `ZTUM_OFFLINE_ONLY=1` converts every missing required bundle artifact into a fail-closed error instead of using network fallback.

The bundle generator consumes a local canonical Zabbix Git checkout and validated upstream index. It verifies current raw YAML bytes against the index before packaging them.

Historical bundle generation deliberately preserves the current rename limitation: if an older commit cannot be read at the current path, generation stops/truncates instead of guessing a renamed historical path.

### Controlled-operation serialization

All configuration-changing controllers acquire `TemplateOperationLockService` before invoking the fresh controlled-operation service.

The lock:

- is global per shared lock filesystem, not per template;
- is non-blocking: a second operation fails closed immediately;
- is acquired before the fresh authoritative preflight;
- remains held through `configuration.import` and post-operation validation;
- applies to individual update, batch update, individual install, batch install and rollback;
- does not replace evidence/preflight checks.

The default lock directory is private below the PHP temporary directory. `ZTUM_LOCK_DIR` can place it in persistent private storage. Multi-node frontends must use storage with reliable cross-node `flock()` semantics for serialization to be meaningful across nodes.

### Runtime storage provisioning

`tools/ztum-runtime-setup.sh` detects a single PHP-FPM runtime account or requires an explicit `--user`, then creates/checks private `0700` runtime directories without modifying PHP-FPM configuration or falling back to world-writable storage.

### Release and security automation

Beta.40 adds independent CI surfaces for:

- deterministic runtime security invariants;
- dependency auditing;
- workflow YAML/action pin validation;
- runtime-directory helper validation;
- offline bundle generation/integrity;
- transparent Xdebug coverage metrics;
- formal tagged release packaging/checksums.

These gates remain automation evidence only; real Zabbix 7.x/8.x runtime validation remains separate.
