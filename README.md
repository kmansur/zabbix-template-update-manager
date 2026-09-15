# Zabbix Template Update Manager

Zabbix Template Update Manager is a frontend module for discovering, comparing and safely managing updates for Zabbix templates.

## Project status

Early development.

Current version:

`0.1.0-dev`

## Target versions

- Zabbix 7.x
- Zabbix 8.x

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

## Architecture

The project is implemented as a native Zabbix frontend module and does not modify Zabbix core files.

## License

License to be defined.
