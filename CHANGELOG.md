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
- Per-template read-only content comparison through Zabbix `configuration.importcompare`.
- Historical baseline lookup by stable UUID plus installed `vendor.version` from canonical path history.
- Three-way BASE / LOCAL / UPSTREAM field analysis with upstream-only, local-overwrite, converged, conflict and unresolved classifications.
- Conservative update review-priority classification that keeps technical severity separate from three-way coverage.
- Exact directly linked host count as known impact breadth, without arbitrary host-count severity thresholds.
- Local immutable historical-baseline cache with SHA-256 validation and private atomic writes.
- Native one-template YAML export service backed by Zabbix `configuration.export`.
- Private local template backup repository with exact byte count and SHA-256 verification.
- Deterministic JSON backup manifests recording template identity and export provenance.
- Template backup service combining native export with local rollback-artifact persistence.
- Read-only update readiness gate with explicit blocked/review/candidate workflow states.
- Fail-closed readiness blockers for missing baseline, unresolved analysis, three-way conflict and local-customization overwrite risk.
- Explicit `candidate_for_backup` state as the strongest positive analysis result before rollback verification; it never enables Zabbix configuration writes.
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
- Dedicated backup/rollback and update-readiness architecture documentation.
- Unit coverage for upstream identity/version/source/history, baseline cache, import comparison, three-way analysis, update risk/readiness, native export contract, backup artifact integrity, tamper detection and current-state backup verification.

### Changed

- The initial page now displays the detected Zabbix version and compatibility state.
- The template page now renders an inventory summary and installed-template table using native Zabbix components.
- The read-only guard rejects Zabbix API write methods and direct database writes while allowing read-only export/comparison operations.
- Official identity is determined by UUID, not vendor metadata alone.
- Version comparison is numeric and independent from identity matching.
- Successful historical baseline resolution reuses a validated local cache on subsequent comparisons instead of rescanning canonical path history every time.
- Content differences are classified as local modifications only against the current matching official version or a resolved historical official baseline of the same vendor version.
- Overall update review priority is forced to `Unknown` when three-way local-overlap coverage is unavailable or unresolved.
- Direct host count is presented as impact context rather than being used to inflate technical severity.
- Backup artifacts use template IDs rather than names for filesystem paths and are written as private local files.
- Runtime rollback backups moved from temporary development storage to persistent `/var/lib/zabbix-template-update-manager/backups` storage.
- The milestone is described as read-only with respect to **Zabbix configuration**; persistent rollback-artifact files are the only intentional frontend-triggered local write.
- Readiness never labels an update safe or ready-to-import; medium/high risk remains manual-review state and all Zabbix configuration write operations stay disabled.
- After `candidate_for_backup`, the comparison page revalidates the newest stored artifact and advances only an exact current export match to `backup_verified`.
- `backup_verified` still keeps `write_enabled = false` and has no configuration-write action attached to it.

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
