# Changelog

All notable changes to Zabbix Template Update Manager will be documented in this file.

## [Unreleased]

### Added

- Initial project structure.
- Zabbix frontend module manifest.
- Initial template update menu.
- Runtime detection for supported Zabbix 7.x and 8.x major versions.
- Read-only installed-template inventory using the native Zabbix `template.get` API service.
- Inventory metadata for UUID, vendor, vendor version, template groups and directly linked host count.
- Compact official-upstream template indexes generated from the canonical Zabbix Git repository.
- Runtime upstream-index retrieval with strict validation, 15-minute cache and stale-cache fallback.
- UUID-based upstream identity matching with explicit fail-closed states.
- Read-only comparison of installed and official upstream `vendor.version` values.
- Canonical raw-source and path-history smoke tests against `git.zabbix.com`.
- Safe official-template source retrieval from `git.zabbix.com` by validated commit and path.
- Per-template content comparison through Zabbix `configuration.importcompare`.
- Historical baseline lookup by stable UUID plus installed `vendor.version` from canonical path history.
- Three-way BASE / LOCAL / UPSTREAM field analysis with upstream-only, local-overwrite, converged, conflict and unresolved classifications.
- Conservative update review-priority classification that keeps technical severity separate from three-way coverage.
- Exact directly linked host count as known impact breadth, without arbitrary host-count severity thresholds.
- Local immutable historical-baseline cache with SHA-256 validation and private atomic writes.
- Native one-template YAML export service backed by Zabbix `configuration.export`.
- Private local template backup repository with exact byte count and SHA-256 verification.
- Deterministic JSON backup manifests recording template identity and export provenance.
- Template backup service combining native export with local rollback-artifact persistence.
- Update readiness gate with explicit blocked/review/candidate/verified workflow states.
- Fail-closed readiness blockers for missing baseline, unresolved analysis, three-way conflict and local-customization overwrite risk.
- Explicit `candidate_for_backup` state before rollback verification.
- Native comparison-page readiness summary showing the required next workflow step.
- CSRF-protected POST action for creating a persistent rollback backup from the currently installed template.
- Native comparison-page **Create rollback backup** control shown when readiness reaches `candidate_for_backup`.
- Persistent default backup root at `/var/lib/zabbix-template-update-manager/backups` with no silent `/tmp` fallback.
- Administrator/super-administrator permission enforcement for rollback-backup creation.
- Static action-contract regression coverage for manifest registration, POST use, native CSRF protection and role restrictions.
- Bounded per-template rollback-artifact inventory with manifest, filename, path, size, mode and SHA-256 validation.
- Fail-closed newest-artifact semantics: an invalid newest backup is never silently replaced by an older valid artifact for readiness purposes.
- Fresh installed-template export verification against the newest intact rollback artifact.
- Explicit `backup_verified` readiness state when the newest artifact exactly matches the current installed export.
- Native comparison-page rollback verification summary including stored-artifact counts and current-match state.
- Administrator-only rollback backup history page with a bounded metadata-only view of the newest 50 artifacts for one template.
- Native inventory links from visible templates to their rollback backup history without scanning backup storage on the main inventory page.
- Backup-history action contract coverage enforcing role restrictions, bounded repository inspection, no fresh export, no filesystem path disclosure and no write controls.
- Reusable `TemplateUpdateAnalysisService` that rebuilds the complete current-upstream, historical-baseline, three-way, risk, readiness and rollback-verification pipeline from a numeric template ID.
- Fail-closed `TemplateUpdatePreflightService` that freshly recomputes authoritative update analysis and requires `backup_verified` before producing a passing non-write preflight result.
- Deterministic preflight evidence SHA-256 binding template identity, immutable upstream commit/path, validated upstream source hash, installed/available versions, verified rollback fingerprint, fresh current-export fingerprint and direct-host impact context.
- CSRF-protected native update-preflight action and evidence view for outdated official templates.
- Comparison/inventory preflight controls that rerun server-side evidence without performing a configuration write.
- Immutable update-candidate reconstruction that re-fetches exact commit/path source and validates SHA-256 against the upstream index before import.
- `TemplateConfigurationImportService` as the single approved `configuration.import` boundary, reusing the same rules as `configuration.importcompare`.
- `TemplateControlledUpdateService` with fresh preflight rerun, evidence-fingerprint TOCTOU protection, candidate revalidation, controlled import and post-import validation.
- Super-administrator-only CSRF-protected update action with explicit confirmation checkbox.
- Post-import validation that requires current vendor version, official UUID match, current-upstream content equality and zero remaining import-comparison differences.
- Native controlled-update result page showing whether a write occurred, candidate fingerprints and post-import validation status.
- CI controlled-write guard enforcing exactly one configuration-import call site and prohibiting other Zabbix API/direct-database write paths.
- Dedicated controlled-update documentation and updated preflight documentation.
- Unit/contract coverage for candidate source binding, preflight content hashes, stale evidence blocking, write-boundary isolation, post-import validation and validation exceptions after import.
- Dedicated backup/rollback and update-readiness architecture documentation.

### Changed

- The initial page now displays the detected Zabbix version and compatibility state.
- The template page now renders an inventory summary and installed-template table using native Zabbix components.
- Official identity is determined by UUID, not vendor metadata alone.
- Version comparison is numeric and independent from identity matching.
- Successful historical baseline resolution reuses a validated local cache on subsequent comparisons instead of rescanning canonical path history every time.
- Content differences are classified as local modifications only against the current matching official version or a resolved historical official baseline of the same vendor version.
- Overall update review priority is forced to `Unknown` when three-way local-overlap coverage is unavailable or unresolved.
- Direct host count is presented as impact context rather than being used to inflate technical severity.
- Backup artifacts use template IDs rather than names for filesystem paths and are written as private local files.
- Runtime rollback backups moved from temporary development storage to persistent `/var/lib/zabbix-template-update-manager/backups` storage.
- Medium/high technical risk remains manual-review state; the initial controlled automatic update path is limited to `none`/`low` risk with complete three-way evidence.
- After `candidate_for_backup`, the comparison page revalidates the newest stored artifact and advances only an exact current export match to `backup_verified`.
- `backup_verified` still keeps evaluator `write_enabled = false`; it now advances to the separate fresh controlled-preflight gate rather than directly authorizing a write.
- Preflight binds full upstream identity plus one unambiguous upstream content SHA-256 and remains a non-write evidence gate.
- Configuration writes are no longer globally disabled: exactly one reviewed `API::Configuration()->import()` boundary is permitted for the explicitly confirmed controlled update flow.
- The old read-only CI guard has evolved into a controlled-write guard while retaining its historical filename `tests/read_only_guard.php`.
- The comparison page itself remains read-only and now links verified backups to controlled preflight.
- `TemplateCompare` remains a thin frontend controller; update-analysis orchestration lives in `src/Service/TemplateUpdateAnalysisService.php` so comparison and write preflight use the same authoritative analysis path.

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
