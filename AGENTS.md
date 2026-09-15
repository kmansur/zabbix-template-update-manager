# AGENTS.md

## Project

Zabbix Template Update Manager is a native Zabbix frontend module for discovering, comparing and safely updating installed Zabbix templates.

Target Zabbix generations:

- Zabbix 7.x
- Zabbix 8.x

Current development version: `0.1.0-dev`.

## Non-negotiable rules

1. Do not modify Zabbix core files.
2. Preserve compatibility with both Zabbix 7.x and 8.x unless a documented compatibility layer is required.
3. Use native Zabbix frontend components, layout patterns, fonts, colors and controls whenever possible.
4. Do not introduce Bootstrap, Tailwind, Material UI or another CSS/UI framework.
5. Zabbix configuration writes are allowed only through the explicitly reviewed controlled-update boundary described below. Do not add another configuration-write path.
6. Do not add direct write operations to the Zabbix database.
7. Do not commit credentials, tokens, API keys, repository secrets or private keys.
8. Never pass unvalidated user input to shell commands, Git commands, repository URLs or refs.
9. Prefer official Zabbix APIs and supported frontend extension points over internal workarounds.
10. Fail closed when the Zabbix version, upstream template identity, source fingerprint, rollback artifact or preflight state cannot be proven safely.
11. Do not classify a template as official from `vendor_name` alone. Official identity requires an upstream UUID match.
12. Do not classify a template as current/outdated from UUID identity alone. Version/content comparison is a separate stage.
13. Do not treat the existence of a backup file as proof that rollback is ready. Stored artifacts must be revalidated against a fresh installed-template export before an update.
14. Do not trust a previously rendered UI state as authorization to write. The controlled update must rerun the full preflight server-side immediately before import.
15. Do not retry a failed or uncertain configuration import automatically.

## Architecture

- `manifest.json` registers the module and actions.
- `Module.php` integrates with the native Zabbix frontend.
- `actions/` contains controllers.
- `views/` contains native Zabbix views.
- `src/` contains reusable domain, repository and service code.
- `tools/` contains deterministic development/index-generation tools.
- `tests/` contains deterministic validation and unit tests.
- `.github/workflows/` contains CI and upstream-index automation.

Keep controllers thin. Put comparison, repository, inventory, backup, preflight, update and rollback logic in `src/` services/classes rather than in views or controllers.

## Zabbix compatibility

Use the frontend `ZABBIX_VERSION` constant as the runtime source for Zabbix version detection.

Supported major versions are explicit and currently limited to 7 and 8. Do not silently treat future major versions as supported.

Use the detected `major.minor` release line for upstream-index selection.

Avoid duplicating the entire codebase into Zabbix 7 and Zabbix 8 variants. Add version-specific compatibility classes only when a real incompatibility is proven.

## Upstream identity

The authoritative initial identity key is the Zabbix template UUID.

Upstream indexes are generated from the official `zabbix/zabbix` repository and must record:

- source line;
- source ref;
- exact source commit;
- commit date;
- YAML path;
- template UUID;
- technical/visible names;
- vendor metadata;
- source content SHA-256 fingerprints.

Index generation must fail on malformed UUIDs, invalid source hashes or conflicting identity metadata.

Runtime repository URLs are fixed project constants. Do not make arbitrary repository URLs user-controllable during the current milestone.

If the remote index cannot be validated, use only a previously validated stale cache. If no validated cache exists, mark upstream identity as unavailable rather than guessing.

A write candidate must be bound to all of the following before import:

- exact 40-character immutable upstream commit;
- validated `templates/.../*.yaml` path;
- one unambiguous validated upstream content SHA-256;
- normalized template UUID;
- visible and technical template names;
- vendor name and vendor version.

The immutable source is re-fetched immediately before import and its SHA-256 must exactly match the upstream index fingerprint.

## UI/UX

The module should look and behave like Zabbix itself.

- Reuse native Zabbix classes and components.
- Prefer existing tables, filters, forms, tabs, buttons, messages and dialogs.
- Keep custom CSS to an absolute minimum.
- Do not hard-code theme colors when a native class/component can provide them.
- Preserve light/dark theme behavior automatically.

## Comparison, backup and preflight

Inventory, comparison and preflight remain non-write operations with respect to Zabbix configuration. Persistent local rollback-backup files are intentional local writes.

Allowed non-configuration-write operations include:

- inventory;
- `configuration.export`;
- repository metadata/source retrieval;
- `configuration.importcompare`;
- historical-baseline lookup;
- BASE / LOCAL / UPSTREAM analysis;
- risk and impact analysis;
- readiness evaluation;
- persistent local rollback-backup creation;
- rollback-artifact integrity verification;
- fresh server-side update preflight.

The local backup action must remain:

- HTTP POST;
- protected by native Zabbix CSRF validation;
- restricted to Zabbix administrators/super administrators;
- limited to private persistent storage;
- separate from Zabbix configuration import.

Preflight must remain a non-write evidence gate. A passing preflight never sets `write_enabled = true`; instead it permits only an explicit controlled-update confirmation step.

## Controlled update milestone

The only approved Zabbix configuration write boundary is:

```text
src/Service/TemplateConfigurationImportService.php
```

It may contain exactly one `API::Configuration()->import()` call and must reuse `TemplateImportCompareService::rules()` so preview and import use the same reviewed rule profile.

The controlled update flow must include all of the following:

1. official template identity proven by UUID;
2. installed version is older than the official candidate;
3. historical baseline resolved;
4. complete three-way analysis with no unresolved identities;
5. no confirmed conflict;
6. no known local-customization overwrite risk;
7. risk/readiness reaches the backup path; initial automatic update eligibility is limited to `none`/`low` technical risk;
8. persistent rollback backup created;
9. newest rollback backup revalidated against a fresh installed-template export (`backup_verified`);
10. fresh server-side preflight recomputed after the confirmation page is requested;
11. immutable upstream commit/path/identity/content hash bound into preflight evidence;
12. explicit super-administrator confirmation through HTTP POST with native CSRF validation;
13. complete preflight rerun immediately before import;
14. posted evidence fingerprint must exactly match the freshly recomputed fingerprint;
15. exact immutable upstream source re-fetched and content SHA-256 revalidated against the index;
16. candidate identity revalidated and isolated to one template;
17. `configuration.import` executed only through `TemplateConfigurationImportService`;
18. fresh post-import analysis must prove the template is current and content matches current upstream with zero remaining comparison differences.

If evidence changes between confirmation and write, the update must be refused with no configuration write.

If import returns successfully but post-import validation fails or errors, report that a write occurred and require manual inspection. Do not retry automatically.

The rollback artifact used as the update prerequisite must not be deleted automatically after update.

## Disallowed write patterns

The following remain prohibited:

- `template.create`, `template.update` or `template.delete` API calls;
- direct database inserts/updates/deletes;
- additional `configuration.import` call sites outside the approved import service;
- automatic template replacement without explicit confirmation;
- user-controlled repository URLs or arbitrary Git refs;
- update retries after ambiguous failures.

The CI controlled-write guard must remain green and enforce the single write boundary.

## Rollback milestone

Rollback must be a separate explicit super-administrator operation, not an automatic reaction to validation failure.

A rollback implementation must at minimum:

1. revalidate the selected stored artifact immediately before use;
2. create a fresh backup of the current post-update state before restoring the older artifact;
3. use the same single controlled configuration-import boundary;
4. require POST, CSRF protection and explicit confirmation;
5. validate the restored template after import;
6. retain all involved backup artifacts;
7. never silently fall back from an invalid newest artifact to an older one.

## Testing before commit

At minimum run:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/read_only_guard.php
for test in tests/unit/*Test.php; do php "$test"; done
python -m py_compile tools/build_upstream_index.py
python tests/test_build_upstream_index.py
```

`tests/read_only_guard.php` is retained as the historical filename, but during the controlled-update milestone it enforces the single approved configuration-write boundary rather than a globally read-only repository.

A meaningful bug fix or safety-boundary change should add or strengthen an automated regression check whenever practical.

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
