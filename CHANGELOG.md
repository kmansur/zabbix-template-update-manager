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

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
