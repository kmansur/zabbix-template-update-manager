# Changelog

All notable changes to Zabbix Template Update Manager will be documented in this file.

## [Unreleased]

### Added

- Initial project structure.
- Zabbix frontend module manifest.
- Initial template update menu.
- Initial template list controller and view.
- Runtime detection for supported Zabbix 7.x and 8.x major versions.
- Basic GitHub Actions validation workflow.
- Manifest validation, read-only guard and Zabbix version unit tests.
- Repository agent/development rules in `AGENTS.md`.
- Read-only installed-template inventory using the native Zabbix `template.get` API service.
- Inventory metadata for UUID, vendor, vendor version, template groups and directly linked host count.
- Unit tests for the template repository query contract and inventory normalization.
- Compact official-upstream template indexes generated from the canonical Zabbix Git repository.
- Runtime upstream-index retrieval with strict validation, 15-minute cache and stale-cache fallback.
- UUID-based upstream identity matching with explicit fail-closed states.
- Python tests for deterministic upstream-index generation.
- PHP tests for upstream index validation and UUID matching.
- Upstream index provenance for repeated official UUID definitions, including all source paths and distinct content SHA-256 hashes.
- Read-only comparison of installed and official upstream `vendor.version` values.
- Explicit version states for current templates, available updates, installed-newer versions, missing versions and uncomparable formats.
- Unit tests that verify numeric vendor-version comparison across revisions and release lines.
- Canonical raw-source smoke test in the upstream-index workflow using an exact immutable source commit.
- Canonical path-specific commit-history smoke test against `git.zabbix.com` before runtime historical lookup is enabled.
- Safe official-template source retrieval from `git.zabbix.com` by validated commit and path.
- Per-template read-only content comparison through Zabbix `configuration.importcompare`.
- Isolation of the selected template UUID from official YAML bundles that contain multiple templates.
- Inclusion of only referenced template-group and discovered host-group definitions in comparison input.
- Read-only change summaries for added, updated and removed entities, including per-entity counts.
- Conservative content states for current matches, local modifications, newer-upstream previews and historical-baseline requirements.
- On-demand historical baseline lookup by stable UUID plus installed `vendor.version`, using canonical path history pinned to the current immutable upstream commit.
- Historical baseline comparison through `configuration.importcompare` for outdated official templates.
- Separate outdated-template states for updates with no detected local modifications and updates with detected local modifications.
- Capped historical scans with explicit `history_limit_reached` semantics instead of claiming a baseline does not exist after an incomplete scan.
- Unit coverage for source URL validation, path traversal rejection, history response validation, historical baseline selection, document isolation, nested import-comparison summaries, classification semantics and import-comparison rules.

### Changed

- Formatted the initial PHP frontend files and added final newlines.
- The initial page now displays the detected Zabbix version and compatibility state.
- The template page now renders an inventory summary and installed-template table using native Zabbix components.
- The read-only guard now rejects generic Zabbix API write-method calls and `DBexecute()`.
- CI now executes every `tests/unit/*Test.php` test automatically.
- Inventory wording now clarifies that results are templates visible to the current user.
- The previous vendor-classification column is replaced by authoritative upstream UUID identity status.
- `Linked to hosts` summary wording now explicitly means templates linked to one or more hosts.
- Upstream index generation now merges repeated UUID definitions when identity metadata agrees instead of assuming every UUID occurs in only one YAML file.
- Repeated UUIDs with conflicting identity metadata continue to fail index generation.
- Upstream index generation now uses the canonical full Zabbix Git repository so historical maintenance tags remain resolvable.
- The template table now distinguishes installed vendor version, available upstream vendor version and version-comparison state.
- Upstream source-path validation now explicitly rejects `.` and `..` traversal segments.
- Official template names are links to the read-only content-comparison action for eligible Zabbix administrators and super administrators.
- Content differences are classified as local modifications only when the installed vendor version equals the current official upstream version or when the installed content is compared against a resolved historical official baseline of the same vendor version.
- Differences against a newer upstream version remain an update preview when no historical official baseline can be established safely.
- The comparison page now keeps current-upstream update changes separate from installed-content differences against the historical baseline.

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
