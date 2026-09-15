# Architecture

## Zabbix Template Update Manager

The project is designed as a native Zabbix frontend module.

## Design principles

1. Do not modify Zabbix core files.
2. Preserve the native Zabbix UI and UX.
3. Prefer Zabbix native frontend components.
4. Keep the initial implementation read-only.
5. Never modify templates during discovery or comparison.
6. Identify templates primarily by UUID when upstream matching is introduced.
7. Detect local modifications before proposing updates.
8. Use Zabbix API capabilities whenever possible.
9. Support Zabbix 7.x and 8.x.
10. Design repository providers independently from the comparison engine.

## Current inventory architecture

```text
Frontend action (TemplateList)
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
Native Zabbix view (CTableInfo)
```

### TemplateRepository

Responsible only for retrieving the minimum read-only dataset required by the inventory:

- template ID;
- technical and visible name;
- UUID;
- vendor name/version;
- template groups;
- direct host-link count.

It does not access the database directly.

### TemplateInventoryService

Responsible for deterministic normalization and summary logic. It does not decide whether a template is actually official upstream.

`vendor_name = Zabbix` is treated only as vendor metadata. Official identity will later require an upstream UUID match.

### Frontend

Responsible for:

- template inventory;
- filters;
- status display;
- detailed comparison;
- risk display;
- configuration.

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
