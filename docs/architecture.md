# Architecture

## Zabbix Template Update Manager

The project is designed as a native Zabbix frontend module.

## Design principles

1. Do not modify Zabbix core files.
2. Preserve the native Zabbix UI and UX.
3. Prefer Zabbix native frontend components.
4. Keep the initial implementation read-only.
5. Never modify templates during discovery or comparison.
6. Identify templates primarily by UUID.
7. Detect local modifications before proposing updates.
8. Use Zabbix API capabilities whenever possible.
9. Support Zabbix 7.x and 8.x.
10. Design repository providers independently from the comparison engine.

## Components

### Frontend

Responsible for:

- template inventory
- filters
- status display
- detailed comparison
- risk display
- configuration

### Inventory service

Responsible for discovering templates installed in Zabbix.

### Repository provider

Responsible for retrieving upstream templates.

Initial provider:

- Official Zabbix repository

Future providers may include:

- GitHub
- GitLab
- local Git repositories
- private repositories
- community templates

### Comparison engine

Responsible for:

- version comparison
- UUID matching
- content comparison
- local modification detection
- upstream modification detection
- conflict detection

### Risk analyzer

Classifies changes as:

- low
- medium
- high
- conflict

### Update engine

Not enabled during the initial read-only phase.

Future responsibilities:

- backup
- import comparison
- template update
- validation
- rollback
