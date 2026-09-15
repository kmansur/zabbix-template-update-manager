# AGENTS.md

## Project

Zabbix Template Update Manager is a native Zabbix frontend module for discovering, comparing and eventually updating installed Zabbix templates safely.

Target Zabbix generations:

- Zabbix 7.x
- Zabbix 8.x

Current development version: `0.1.0-dev`.

## Non-negotiable rules

1. Do not modify Zabbix core files.
2. Preserve compatibility with both Zabbix 7.x and 8.x unless a documented compatibility layer is required.
3. Use native Zabbix frontend components, layout patterns, fonts, colors and controls whenever possible.
4. Do not introduce Bootstrap, Tailwind, Material UI or another CSS/UI framework.
5. Keep the current milestone strictly read-only. Do not create, update, import or delete Zabbix configuration.
6. Do not add write operations to the database.
7. Do not commit credentials, tokens, API keys, repository secrets or private keys.
8. Never pass unvalidated user input to shell commands, Git commands, repository URLs or refs.
9. Prefer official Zabbix APIs and supported frontend extension points over internal workarounds.
10. Fail closed when the Zabbix version or an upstream template identity cannot be determined safely.

## Architecture

- `manifest.json` registers the module and actions.
- `Module.php` integrates with the native Zabbix frontend.
- `actions/` contains controllers.
- `views/` contains native Zabbix views.
- `src/` contains reusable domain and service code.
- `tests/` contains deterministic validation and unit tests.
- `.github/workflows/` contains CI.

Keep controllers thin. Put comparison, repository, inventory and update logic in `src/` services/classes rather than in views or controllers.

## Zabbix compatibility

Use the frontend `ZABBIX_VERSION` constant as the runtime source for Zabbix version detection.

Supported major versions are explicit and currently limited to 7 and 8. Do not silently treat future major versions as supported.

Avoid duplicating the entire codebase into Zabbix 7 and Zabbix 8 variants. Add version-specific compatibility classes only when a real incompatibility is proven.

## UI/UX

The module should look and behave like Zabbix itself.

- Reuse native Zabbix classes and components.
- Prefer existing tables, filters, forms, tabs, buttons, messages and dialogs.
- Keep custom CSS to an absolute minimum.
- Do not hard-code theme colors when a native class/component can provide them.
- Preserve light/dark theme behavior automatically.

## Read-only milestone

Until the update milestone is explicitly enabled, allowed operations include:

- inventory;
- export/read operations;
- repository metadata retrieval;
- comparison;
- `configuration.importcompare`;
- risk analysis;
- impact analysis.

Disallowed operations include:

- `configuration.import`;
- template create/update/delete;
- direct database inserts/updates/deletes;
- automatic template replacement.

The CI read-only guard must remain green during this phase.

## Future update milestone

Write operations may only be introduced after the read-only discovery/comparison phase is validated. The future update flow must include, at minimum:

1. review of proposed changes;
2. `configuration.importcompare`;
3. backup/export of the current template;
4. explicit administrator confirmation;
5. controlled import;
6. post-import validation;
7. rollback capability.

## Testing before commit

At minimum run:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/read_only_guard.php
php tests/unit/ZabbixVersionTest.php
```

A meaningful bug fix should add or strengthen an automated regression check whenever practical.

## Git workflow

Preferred branches:

- `feature/<description>`
- `fix/<description>`
- `docs/<description>`
- `test/<description>`
- `ci/<description>`
- `refactor/<description>`
- `chore/<description>`

Use Conventional Commits:

- `feat:`
- `fix:`
- `docs:`
- `test:`
- `ci:`
- `refactor:`
- `chore:`

Update documentation and `CHANGELOG.md` when behavior visible to users or maintainers changes.
