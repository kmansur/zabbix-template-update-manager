# Zabbix Template Update Manager

Zabbix Template Update Manager is a frontend module for discovering, comparing and safely managing updates for Zabbix templates.

## Project status

Early development.

Current version:

`0.1.0-dev`

The current milestone is strictly read-only. The module can evolve through discovery and comparison functionality, but it must not modify templates or other Zabbix configuration yet.

## Target versions

- Zabbix 7.x
- Zabbix 8.x

Runtime compatibility detection uses the native Zabbix `ZABBIX_VERSION` frontend constant. Unsupported or unknown major versions fail closed instead of being assumed compatible.

## Current functionality

The module can inventory templates visible to the current Zabbix user through the native `template.get` API service. No direct database access is used.

The inventory currently displays:

- visible/technical template name;
- UUID;
- vendor name;
- vendor version;
- template groups;
- number of directly linked hosts;
- vendor metadata classification.

Vendor metadata is not treated as proof that a template is official. A template with `vendor_name = Zabbix` is classified only as `Vendor: Zabbix`; official/upstream identity will later be verified against the Zabbix repository by UUID.

## Initial goals

- Discover installed templates
- Identify official Zabbix templates
- Match templates using UUID
- Detect installed vendor/version
- Compare installed templates with upstream templates
- Detect local modifications
- Detect available updates
- Display granular differences
- Estimate update risk
- Show affected hosts
- Preserve the native Zabbix frontend experience

## Safety

The first development phase is read-only.

No template will be modified or imported automatically.

CI includes a read-only guard that rejects known Zabbix API write methods, template write operations and direct database write calls during this milestone.

## Architecture

The project is implemented as a native Zabbix frontend module and does not modify Zabbix core files.

The frontend should reuse native Zabbix components and styling so that menus, tables, filters, controls, fonts, colors and themes remain consistent with the installed Zabbix version.

Current inventory flow:

```text
TemplateList controller
        |
        v
TemplateRepository
        |
        v
API::Template()->get()
        |
        v
TemplateInventoryService
        |
        v
Native Zabbix CTableInfo view
```

## Development validation

CI validates:

- PHP syntax;
- `manifest.json` structure and action registration;
- read-only constraints;
- Zabbix 7/8 runtime version detection;
- template repository query contract;
- template inventory normalization and summary logic.

Local checks:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/read_only_guard.php
for test in tests/unit/*Test.php; do php "$test"; done
```

## License

License to be defined.
