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

CI includes a read-only guard that rejects known template write operations and direct database write calls during this milestone.

## Architecture

The project is implemented as a native Zabbix frontend module and does not modify Zabbix core files.

The frontend should reuse native Zabbix components and styling so that menus, tables, filters, controls, fonts, colors and themes remain consistent with the installed Zabbix version.

## Development validation

The initial CI validates:

- PHP syntax;
- `manifest.json` structure and action registration;
- read-only constraints;
- Zabbix 7/8 runtime version detection.

Local checks:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/read_only_guard.php
php tests/unit/ZabbixVersionTest.php
```

## License

License to be defined.
