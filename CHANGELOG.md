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

### Changed

- Formatted the initial PHP frontend files and added final newlines.
- The initial page now displays the detected Zabbix version and compatibility state.
- The template page now renders an inventory summary and installed-template table using native Zabbix components.
- The read-only guard now rejects generic Zabbix API write-method calls and `DBexecute()`.
- CI now executes every `tests/unit/*Test.php` test automatically.

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
