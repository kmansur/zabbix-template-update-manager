# Zabbix Template Update Manager

Zabbix Template Update Manager (ZTUM) is an independent native Zabbix frontend module for discovering, matching, comparing and safely managing updates of installed Zabbix templates.

It does not modify Zabbix core files and is not an official Zabbix LLC product.

## Status

Current version: **0.1.0-beta.1**

This version is intended for **laboratory testing**.

- Implementation: ready for laboratory testing.
- Automation validation: required CI suite must be green for the tagged commit.
- Field validation: pending on real Zabbix 7.x and 8.x lab instances.
- Production use: not yet recommended.

See [`docs/lab-test-plan.md`](docs/lab-test-plan.md) before installing the beta.

## Supported Zabbix generations

- Zabbix 7.x
- Zabbix 8.x

The module detects the frontend `ZABBIX_VERSION` at runtime and fails closed for unsupported/unknown major versions.

## What the beta can do

ZTUM currently provides:

- installed-template inventory through the native Zabbix API;
- official-template identification by UUID;
- installed/upstream vendor-version comparison;
- official upstream source indexing by Zabbix release line;
- current-upstream comparison through `configuration.importcompare`;
- historical official baseline resolution;
- BASE / LOCAL / UPSTREAM three-way analysis;
- conflict and local-customization overwrite detection;
- technical update-risk/review-priority classification;
- directly linked host impact context;
- persistent private rollback backups;
- rollback-artifact integrity verification against fresh installed-template exports;
- bounded backup-history inspection;
- fail-closed controlled update preflight;
- explicit super-administrator controlled update;
- post-update validation;
- explicit super-administrator rollback review;
- fresh recovery backup before rollback;
- explicit controlled rollback;
- post-rollback validation.

## Safety model

Official identity is based on template UUID, never on vendor metadata alone. Version comparison, content comparison and update eligibility are separate stages.

For an outdated official template, the controlled update path is offered only when the current implementation can prove all required evidence, including a historical baseline, complete three-way analysis, no conflict, no local-overwrite risk, `none`/`low` technical risk, and a persistent rollback artifact that exactly matches a fresh export of the installed template.

Immediately before an update, ZTUM reruns the authoritative preflight, compares the explicit confirmation evidence with fresh server-side evidence, re-fetches the official template from the exact immutable upstream commit/path, validates its SHA-256 against the upstream index and revalidates template identity.

The repository contains exactly one approved Zabbix configuration-write boundary:

```text
src/Service/TemplateConfigurationImportService.php
```

Both controlled update and rollback reuse that service. CI rejects additional known Zabbix API write paths and direct database writes.

Rollback is never automatic. A super administrator must explicitly select a valid stored artifact, review a fresh `configuration.importcompare` preview and confirm the operation. Before restoring the older artifact, ZTUM creates a fresh recovery backup of the current state and verifies that it exactly matches the current export participating in rollback preflight.

If an import has occurred but final validation cannot prove the expected state, the module reports that a write occurred and does not retry automatically.

## Persistent storage

Rollback artifacts are stored by default under:

```text
/var/lib/zabbix-template-update-manager/backups
```

The PHP/web runtime account must be able to create/read private files there. On Debian/Ubuntu the runtime user is often `www-data`, but verify the actual account before applying permissions.

Example only after confirming the runtime user:

```bash
sudo install -d -o www-data -g www-data -m 0700 \
  /var/lib/zabbix-template-update-manager/backups
```

The module intentionally does not fall back to a world-writable or temporary backup location.

## Installation for laboratory testing

Zabbix frontend modules are installed as one directory under the frontend `modules` directory. The package-specific path can vary, so locate it first rather than assuming a path:

```bash
find /usr/share/zabbix /usr/local/share/zabbix /var/www \
  -type d -name modules 2>/dev/null
```

Install the complete ZTUM directory below the correct `modules` directory. Then use:

```text
Administration → General → Modules → Scan directory
```

Confirm version **0.1.0-beta.1**, enable the module and open:

```text
Data collection → Template updates
```

For the exact beta test sequence, including update and rollback validation, follow [`docs/lab-test-plan.md`](docs/lab-test-plan.md).

## High-level workflow

```text
installed template
      |
      v
UUID official identity
      |
      v
vendor-version comparison
      |
      v
current upstream import preview
      |
      v
historical BASE resolution
      |
      v
BASE / LOCAL / UPSTREAM analysis
      |
      v
risk + readiness gate
      |
      v
persistent rollback backup
      |
      v
fresh backup verification
      |
      v
controlled update preflight
      |
      v
explicit SUPER_ADMIN confirmation
      |
      v
fresh preflight + immutable source/hash verification
      |
      v
single configuration.import boundary
      |
      v
post-update validation

rollback history
      |
      v
select valid artifact
      |
      v
read-only rollback review/importcompare
      |
      v
explicit SUPER_ADMIN confirmation
      |
      v
fresh preflight + recovery backup
      |
      v
single configuration.import boundary
      |
      v
post-rollback validation
```

## Upstream source model

Compact indexes are generated from the canonical Zabbix source repository and record official template UUIDs, source paths, vendor metadata, exact source commit and content fingerprints. Runtime source retrieval is constrained to validated immutable commits and `templates/.../*.yaml` paths.

The module may use a previously validated stale local index when refresh fails. If no validated index is available, upstream identity becomes unavailable instead of being guessed.

## Documentation

Detailed design and safety documentation is available in:

- [`docs/architecture.md`](docs/architecture.md)
- [`docs/three-way-analysis.md`](docs/three-way-analysis.md)
- [`docs/update-risk.md`](docs/update-risk.md)
- [`docs/update-readiness.md`](docs/update-readiness.md)
- [`docs/update-preflight.md`](docs/update-preflight.md)
- [`docs/controlled-update.md`](docs/controlled-update.md)
- [`docs/backup-and-rollback.md`](docs/backup-and-rollback.md)
- [`docs/rollback.md`](docs/rollback.md)
- [`docs/lab-test-plan.md`](docs/lab-test-plan.md)

## Development validation

The CI pipeline currently checks:

```text
PHP syntax
manifest/action contract
VERSION ↔ manifest version consistency
controlled-write boundary
PHP unit/contract tests
upstream-index generator validation
```

Local equivalent:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/validate_version.php
php tests/read_only_guard.php
for test in tests/unit/*Test.php; do php "$test"; done
python -m py_compile tools/build_upstream_index.py
python tests/test_build_upstream_index.py
```

A green CI run proves automated checks only. It does not replace runtime field validation on Zabbix 7.x and 8.x.

## License

A project license has not yet been selected. This remains a release/publication item before a stable production release.
