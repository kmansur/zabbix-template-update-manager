# Changelog

All notable changes to Zabbix Template Update Manager will be documented in this file.

## [Unreleased]

## [0.1.0-beta.13] - 2026-09-22

### Fixed

- Rollback review now passes the stored artifact format to `configuration.importcompare` instead of always treating rollback YAML as JSON.
- Post-rollback validation now uses the same artifact format, preventing a successful YAML restore from failing its final comparison with `Cannot read JSON: Syntax error`.
- Rollback preflight evidence now binds the target format and the final rollback target revalidation verifies that the format has not changed.

### Safety

- Unsupported rollback comparison formats fail closed before a Zabbix API comparison call.
- The stored format participates in rollback TOCTOU evidence and target matching; no configuration-write boundary was added or changed.

### Tests

- Added regression coverage proving stored YAML backup bytes reach `configuration.importcompare` with `format=yaml` during review and post-rollback validation.
- Added format-preservation assertions across rollback preflight and rollback result descriptors.

## [0.1.0-beta.12] - 2026-09-21

### Fixed

- Isolated upstream documents now preserve top-level `graphs` and `triggers` that belong exclusively to the selected template instead of dropping them outside the nested `templates` object.
- Template dashboards that reference an official top-level graph now receive that graph in both current-upstream comparison and controlled import sources.
- The Acronis Cyber Protect Cloud MSP update no longer misclassifies the official `Acronis CPC: Alerts overview` graph as a LOCAL-only customization merely because the graph is stored at export top level.
- Controlled import no longer sends a dashboard that references a graph omitted by the isolation layer.

### Safety

- Top-level graphs or triggers that reference more than the selected template fail closed instead of silently importing cross-template dependencies.
- Dashboard references to missing or foreign-template top-level graphs fail before `configuration.import`.
- Historical baseline isolation uses the same dependency-preserving rules as current upstream isolation, keeping BASE / LOCAL / UPSTREAM analysis consistent.

### Tests

- Added regression coverage for top-level graph preservation, dashboard-to-graph dependencies, top-level triggers, historical baseline isolation and cross-template dependency rejection.

## [0.1.0-beta.11] - 2026-09-21

### Fixed

- Controlled update now distinguishes the SHA-256 of the exact raw upstream YAML file from the canonical per-template content fingerprint stored by the upstream index.
- `TemplateUpdateCandidateService` verifies the immutable commit/path raw source bytes against a dedicated per-path source fingerprint instead of incorrectly comparing whole-file YAML bytes with a template-object hash.
- Legacy validated indexes remain usable for read-only inventory/comparison, but controlled update fails closed until an index with raw source fingerprints is available.
- Upstream source and commit-history HTTP clients now use the packaged project version in their User-Agent instead of the stale `0.1.0-dev` literal.

### Added

- Per-source-path raw SHA-256 fingerprints in generated upstream indexes under `sources`, while retaining `content_sha256s` as canonical template-content fingerprints.
- Preflight evidence schema 5 binds both `upstream_source_sha256` and `upstream_content_sha256`.
- Regression coverage using intentionally different raw-source and template-content fingerprints so the beta.10 hash-contract bug cannot silently return.
- Upstream-index workflow smoke validation that compares bytes fetched from the canonical raw endpoint with the generated source fingerprint.

### Changed

- Preflight and update result views display raw source and template-content fingerprints separately.
- The controlled update candidate contract now carries both fingerprints through fresh preflight, TOCTOU evidence comparison, candidate reconstruction and result reporting.

### Safety

- The failing beta.10 path stopped before `configuration.import`; beta.11 preserves that fail-closed behavior and strengthens the immutable-source verification contract.
- No additional Zabbix configuration-write boundary was introduced.

## [0.1.0-beta.10] - 2026-09-21

### Added

- Explicit reviewed-update path for authoritative candidates with no unresolved identity and no BASE / LOCAL / UPSTREAM conflict, but with known local-overwrite and/or medium/high technical-risk conditions.
- Second super-administrator acknowledgement checkbox before a reviewed override can reach the single controlled `configuration.import` boundary.
- Reviewed-mode and exact review reasons bound into the fresh preflight SHA-256 evidence to prevent changing update mode between review and write.
- Historical-baseline provenance details in the comparison view: same-version commit count, distinct official contents, exact LOCAL matches and closest semantic distance.

### Changed

- Known local-overwrite is no longer an absolute dead-end when the historical baseline and three-way analysis are authoritative. It enters `review_required`, may create/verify a rollback backup, then advances to `review_backup_verified` and a manually acknowledged preflight.
- Medium/high technical-risk candidates use the same explicit reviewed path instead of being permanently unable to advance.
- True three-way conflicts, unresolved identities, ambiguous/missing baselines and incomplete risk coverage remain hard blockers and cannot be overridden.
- Reviewed override candidates remain excluded from unattended batch `Ready`; batch execution continues to update only standard fully automatic candidates.
- Backup verification now runs for both standard backup candidates and explicit reviewed candidates.

### Safety

- The reviewed path never bypasses rollback creation, backup/current-export equality, immutable upstream commit/path/hash validation, fresh server-side preflight, TOCTOU evidence matching or post-import validation.
- No additional Zabbix write boundary was introduced.

## [0.1.0-beta.4] - 2026-09-16

### Added

- Bounded multi-template safety preparation for up to 25 explicitly selected official update candidates.
- Batch classification into `Ready`, `Manual review`, `Conflict / local overwrite` and `Blocked` using the existing per-template analysis/readiness pipeline.
- Persistent rollback artifact creation/refresh during batch preparation only for templates that reached `candidate_for_backup`.
- Fresh per-template preflight evidence binding for every batch candidate classified `Ready`.
- Super-administrator-only controlled sequential batch execution.
- Stop-on-first-non-success behavior with explicit `updated`, `failed` and `not_attempted` result groups.
- Batch result page that reports whether any configuration write occurred and whether the stopping template itself performed a write.
- Unit coverage for batch planning categories, rollback preparation, sequential order, evidence validation and stop behavior.
- Static batch-action contract coverage for CSRF, Super Admin authorization, explicit confirmation and the single approved configuration-write boundary.

### Changed

- Selected-template review is now bounded to 25 templates so the review limit matches the batch safety-preparation/execution limit.
- The selected-template review page can advance eligible candidates into `Prepare selected updates` for full safety analysis and rollback preparation.
- Batch execution reuses `TemplateControlledUpdateService` for every template instead of introducing a second import path.
- Every template reruns the complete server-side preflight immediately before its own `configuration.import`; stale evidence stops the batch before that template is written.
- Automatic rollback remains deliberately disabled. If one template fails after a write, execution stops and the operator must inspect/choose the correct rollback artifact.

### Validation status

- Zabbix 7.0.30 field validation has already confirmed inventory, upstream index retrieval, official UUID matching, version comparison and native selection controls.
- The upstream-index workflow now completes end-to-end with generated-index runtime-decoder validation, canonical raw/history endpoint smoke tests and publication.
- Beta.4 is the first laboratory candidate for end-to-end selected batch preparation and controlled sequential update testing.
- Zabbix 8.x runtime validation remains pending.
- Production use is not recommended.

## [0.1.0-beta.3] - 2026-09-15

### Added

- Native Zabbix `CCheckBox` row selection for official templates with an available upstream vendor-version update.
- Native select-all behavior using the same `checkAll()` pattern used by Zabbix list views.
- `Review selected updates` action using `CActionButtonList` and a CSRF-protected bounded selected-template review controller.
- Read-only selected-template review page that rebuilds inventory, upstream identity and vendor-version state for only the explicitly selected subset.
- Runtime validation notes promoted from the Zabbix 7.0.30 field pass.
- Generated-index validation through the same PHP runtime decoder before the upstream-index workflow may publish indexes.

### Fixed

- Runtime upstream-index validation now accepts the literal `+` character used by official MikroTik source paths such as `mikrotik_CRS305-1G-4S+IN_snmp`, while continuing to reject `.` / `..` traversal segments and paths outside `templates/.../*.yaml`.
- Invalid upstream paths now include a bounded sanitized path value in diagnostics, making future generator/runtime mismatches actionable in the lab.

### Changed

- The inventory's per-row `Update preflight` form was replaced by an `Update review` link so the table can use one native Zabbix selection form without invalid nested forms.
- Selection is intentionally a review scope in beta.3. Bulk `configuration.import` is not performed; every selected template still uses its own backup, preflight, explicit confirmation and single approved import boundary.
- Upstream index CI is triggered when runtime index-validation code changes and uses the packaged VERSION in canonical endpoint smoke tests.

### Test-release status

- Zabbix 7.0.30 local inventory is field-validated with 303 visible templates in the first lab environment.
- Beta.3 confirmed that the 7.0 upstream index validates, yielding 298 official UUID matches, 290 updates available and zero repository-unavailable results in the lab.
- Native selected-template checkbox behavior is visible in Zabbix 7.0.30.
- Zabbix 8.x runtime validation remains pending.
- Production use is not recommended.

## [0.1.0-beta.2] - 2026-09-15

### Added

- Runtime project-version source backed by the root `VERSION` file.
- Administrator-only upstream diagnostics showing the requested index endpoint, cURL availability, `allow_url_fopen`, OpenSSL availability and bounded failure detail.
- HTTP transport fallback: when cURL is available but fails, the upstream index loader can also try the PHP stream transport when `allow_url_fopen` is enabled.
- Regression coverage preventing the inventory page and upstream HTTP user agent from falling back to the stale `0.1.0-dev` literal.

### Fixed

- The inventory header no longer displays the hard-coded `0.1.0-dev`; it now reports the actual packaged version.
- The upstream HTTP user agent now tracks the packaged version instead of the stale development version.
- Upstream repository failures now retain a bounded diagnostic cause for administrators while continuing to fail closed for official-template identity.

### Test-release status

- First Zabbix 7.x runtime inventory pass observed successfully.
- The beta.2 upstream request reached and downloaded the index, but runtime validation rejected an official source path containing `+`; this is fixed in beta.3.
- Zabbix 8.x runtime validation remains pending.
- Production use is not recommended.

## [0.1.0-beta.1] - 2026-09-15

### Added

- Initial project structure and native Zabbix frontend module integration.
- Runtime detection for supported Zabbix 7.x and 8.x major versions.
- Read-only installed-template inventory using the native Zabbix `template.get` API service.
- Inventory metadata for UUID, vendor, vendor version, template groups and directly linked host count.
- Compact official-upstream template indexes generated from the canonical Zabbix Git repository.
- Runtime upstream-index retrieval with strict validation, 15-minute cache and stale-cache fallback.
- UUID-based upstream identity matching with explicit fail-closed states.
- Numeric comparison of installed and official upstream `vendor.version` values.
- Canonical raw-source and path-history smoke tests against `git.zabbix.com`.
- Safe official-template source retrieval from `git.zabbix.com` by validated immutable commit and path.
- Per-template content comparison through Zabbix `configuration.importcompare`.
- Historical baseline lookup by stable UUID plus installed `vendor.version` from canonical path history.
- Three-way BASE / LOCAL / UPSTREAM field analysis with upstream-only, local-overwrite, converged, conflict and unresolved classifications.
- Conservative update review-priority classification that keeps technical severity separate from three-way coverage.
- Exact directly linked host count as known impact breadth, without arbitrary host-count severity thresholds.
- Local immutable historical-baseline cache with SHA-256 validation and private atomic writes.
- Native one-template YAML export service backed by Zabbix `configuration.export`.
- Private local template backup repository with exact byte count and SHA-256 verification.
- Deterministic JSON backup manifests recording template identity and export provenance.
- Update readiness gate with explicit blocked/review/candidate/verified workflow states.
- Fail-closed readiness blockers for missing baseline, unresolved analysis, three-way conflict and local-customization overwrite risk.
- CSRF-protected POST action for persistent rollback-backup creation.
- Persistent default backup root at `/var/lib/zabbix-template-update-manager/backups` with no silent `/tmp` fallback.
- Bounded per-template rollback-artifact inventory with manifest, filename, path, size, mode and SHA-256 validation.
- Fresh installed-template export verification against the newest intact rollback artifact.
- Explicit `backup_verified` readiness state when the newest artifact exactly matches the current installed export.
- Administrator-only rollback backup history page with a bounded view of the newest 50 artifacts.
- Reusable `TemplateUpdateAnalysisService` for current-upstream, historical-baseline, three-way, risk, readiness and rollback-verification analysis.
- Fail-closed `TemplateUpdatePreflightService` that freshly recomputes authoritative update analysis and requires `backup_verified`.
- Deterministic update-preflight SHA-256 evidence binding template identity, immutable upstream source identity/hash, versions, rollback/current-export fingerprints and impact context.
- Immutable update-candidate reconstruction that re-fetches exact commit/path source and validates SHA-256 against the upstream index.
- `TemplateConfigurationImportService` as the repository's single approved `configuration.import` boundary, reusing the same rules as `configuration.importcompare`.
- `TemplateControlledUpdateService` with fresh preflight rerun, evidence-fingerprint TOCTOU protection, candidate revalidation, controlled import and post-import validation.
- Super-administrator-only CSRF-protected update action with explicit confirmation.
- Post-update validation requiring current official version, official UUID match, current-upstream content equality and zero remaining import-comparison differences.
- Controlled rollback review for explicitly selected valid stored artifacts.
- Deterministic rollback-preflight evidence binding current template export, selected artifact identity/fingerprint and import-preview counts.
- Fresh recovery backup creation before restoring an older artifact.
- Controlled rollback orchestration with a second read-only preflight, current-state drift detection and reuse of the single `configuration.import` boundary.
- Post-rollback validation requiring matching template ID/UUID/vendor version and zero remaining import-comparison differences.
- Super-administrator-only CSRF-protected rollback action with explicit confirmation.
- Native update and rollback result pages that distinguish blocked/no-write, successful/validated and write-performed-but-validation-failed states.
- CI controlled-write guard enforcing exactly one configuration-import call site and prohibiting additional Zabbix API/direct-database write paths.
- Root `VERSION` file and CI validation keeping project/module version metadata consistent.
- Unit and static action-contract coverage for inventory, upstream identity/version/source/history, baseline cache, import comparison, three-way analysis, risk/readiness, backups, update preflight/update execution and rollback execution.
- Dedicated documentation for controlled update, rollback, backup/readiness and three-way analysis.

### Changed

- Official identity is determined by UUID, not vendor metadata alone.
- Version comparison is independent from identity matching.
- Content differences on outdated templates are classified against a historical official baseline of the same vendor version rather than against current upstream alone.
- Overall update review priority is forced to `Unknown` when three-way local-overlap coverage is unavailable or unresolved.
- Direct host count is presented as impact context rather than being used to inflate technical severity.
- Backup artifacts use template IDs rather than names for filesystem paths and are written as private local files.
- Medium/high technical risk remains a manual-review state; the first controlled automatic update path is limited to `none`/`low` risk with complete three-way evidence.
- `backup_verified` advances only to a separate fresh controlled preflight; it never directly authorizes configuration import.
- Configuration writes are no longer globally disabled: exactly one reviewed `API::Configuration()->import()` boundary is permitted for explicit controlled update and rollback workflows.
- The historical `tests/read_only_guard.php` now acts as a controlled-write-boundary guard.
- Invalid backup artifacts are never selectable for rollback, and backup-history scanning remains bounded to the newest 50 manifests.
- Rollback never runs automatically after an update validation failure; it is a separate, explicit super-administrator operation.

### Test-release status

- Implementation: ready for laboratory testing.
- Automation validation: required CI suite must be green before tagging.
- Field validation: pending on real Zabbix 7.x and 8.x laboratory instances.
- Production use: not yet recommended.

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
