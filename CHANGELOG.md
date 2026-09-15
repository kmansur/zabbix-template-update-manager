# Changelog

All notable changes to Zabbix Template Update Manager will be documented in this file.

## [Unreleased]

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
