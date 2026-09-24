# Template Update Manager

[![CI](https://github.com/kmansur/zabbix-template-update-manager/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/kmansur/zabbix-template-update-manager/actions/workflows/ci.yml)
[![Security](https://github.com/kmansur/zabbix-template-update-manager/actions/workflows/security.yml/badge.svg?branch=main)](https://github.com/kmansur/zabbix-template-update-manager/actions/workflows/security.yml)
[![Quality metrics](https://github.com/kmansur/zabbix-template-update-manager/actions/workflows/quality-metrics.yml/badge.svg?branch=main)](https://github.com/kmansur/zabbix-template-update-manager/actions/workflows/quality-metrics.yml)

Template Update Manager (ZTUM) is an independent native Zabbix frontend module for discovering, installing, matching, comparing and safely managing official Zabbix templates.

It does not modify Zabbix core files and is not an official Zabbix LLC product.

## Status

Current version: **0.1.0-beta.57**

This version is intended for **laboratory testing**.

- Implementation: ready for end-to-end laboratory validation.
- Automation validation: must be green for the beta snapshot commit.
- Field validation: in progress on real Zabbix 7.x and 8.x lab instances.
- Production use: not yet recommended.
- Community testing: feedback and reproducible field-validation reports are welcome.

Beta.57 fixes catalog Name search to match the complete visible template name (`name` plus `technical_name` when displayed) using case-insensitive partial matching, and aligns the filter layout with the native Zabbix Templates page using `CFormGrid`, `CLabel`, `CFormField` and the medium native text-field width.

Formal tag/GitHub Release automation exists, but a community prerelease remains gated by this cross-version UI field pass and the still-unselected project license.

For a new laboratory installation, use the quick installer below. For validation work, always record the exact installed version and commit/source ref.

See [`docs/lab-test-plan.md`](docs/lab-test-plan.md) before testing the beta.

## Supported Zabbix generations

- Zabbix 7.x
- Zabbix 8.x

The module detects the frontend `ZABBIX_VERSION` at runtime and fails closed for unsupported/unknown major versions.

The module keeps one codebase for both supported major generations and prefers native Zabbix abstractions so each frontend generation can apply its own internal UI implementation.

The catalog and read-only review surfaces are available to Zabbix Administrators and Super Admins. Configuration-changing operations and update-policy changes remain Super-Admin-only.

The catalog filter follows the native Zabbix list pattern with **Name** and **Status** fields, **Apply / Reset** actions and automatic return to page 1 whenever filtering changes.

## What the beta can do

ZTUM currently provides:

- installed-template inventory through the native Zabbix API;
- merged official upstream catalog showing upstream-only templates as `Not installed`;
- native Zabbix pagination for the expanded catalog;
- individual controlled installation of missing official templates after dependency/collision/import-preview review;
- official-template identification by UUID;
- installed/upstream vendor-version comparison;
- official upstream source indexing by Zabbix release line;
- native Zabbix checkbox/select-all selection of specific update candidates;
- persistent **Never update** protection for selected installed official templates, with a dedicated catalog filter and explicit **Allow updates** reversal;
- direct catalog-to-preparation bulk update flow for explicitly selected official update candidates, without a redundant scope-only review page;
- request-bounded update-batch safety preparation for the full selected update set (up to the existing 500-template selection safety ceiling), executed one candidate per HTTP request with visible progress;
- automatic creation/refresh of rollback artifacts for standard-path candidates (none/low plus narrowly recognized bounded-medium changes) and explicitly reviewed manual-update candidates;
- batch classification into Ready, Manual review, Conflict and Blocked; reviewed candidates use native leading per-row checkboxes plus explicit Select all eligible / Clear selection controls, but never become unattended Ready;
- controlled sequential update of Ready plus explicitly selected reviewed templates, with one HTTP request per template;
- stop-on-first-failure/evidence-change/ambiguous-state behavior with explicit not-attempted reporting;
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
- post-rollback validation;
- runtime module-version reporting from the repository `VERSION` file;
- administrator-only diagnostics for upstream index endpoint, PHP HTTP transport capabilities and configured offline mode;
- verified air-gapped/offline upstream bundles with fail-closed `ZTUM_OFFLINE_ONLY=1`;
- global filesystem serialization for update/install/rollback write workflows;
- explicit individual local-overwrite confirmation in addition to reviewed-risk confirmation;
- safe runtime-directory setup/check helper;
- dedicated security, release and quality-metrics workflows.

Batch execution does not create a second write path. Each executable template is processed through `TemplateControlledUpdateService`, which reruns fresh preflight in the bound standard/reviewed mode, verifies the page evidence has not changed, rebuilds the immutable upstream candidate and then uses the same single configuration-import service already used by individual update/rollback flows.

## Safety model

Official identity is based on template UUID, never on vendor metadata alone. Version comparison, content comparison and update eligibility are separate stages.

For an outdated official template, the standard unattended path still requires a proven historical baseline, complete three-way analysis, no conflict, no local-overwrite risk, and a persistent rollback artifact that exactly matches a fresh export of the installed template. `none`/`low` technical risk is standard-path eligible. Medium impact remains manual by default, except for narrowly recognized bounded changes explicitly marked `standard_path_eligible` by the risk analyzer. Manual-review candidates can enter the reviewed batch only after verified rollback evidence and a passing manual-mode preflight. A `local_customization_overwrite` reason additionally requires a second explicit acknowledgement before the write; conflict/unresolved evidence remains blocked.

Immediately before an update, ZTUM reruns the authoritative preflight, compares the explicit confirmation evidence with fresh server-side evidence, re-fetches the official template from the exact immutable upstream commit/path, verifies the raw YAML SHA-256 against the path-specific upstream index fingerprint, preserves the separate canonical template-content fingerprint in the evidence, and revalidates template identity. The selected source is isolated to one official template plus required group definitions. Cross-template trigger/graph/dashboard references may remain only as references to already installed templates; their external-template name set is bound into preflight evidence and must match again when the immutable candidate is rebuilt. Sibling templates are never imported implicitly.

The repository contains exactly one approved Zabbix configuration-write boundary:

```text
src/Service/TemplateConfigurationImportService.php
```

Individual update, sequential batch update and rollback all reuse that service. CI rejects additional known Zabbix API write paths and direct database writes.

The former 25-template update-batch ceiling has been removed. Update selection retains the existing 500-template sanity ceiling, while preparation and Ready execution remain request-bounded one template at a time. Execution stops immediately when one template does not return a successful validated update; remaining templates are reported as **Not attempted**. Automatic rollback is never attempted because a failed post-write state may require operator inspection before choosing the correct recovery artifact.

Rollback is never automatic. A super administrator must explicitly select a valid stored artifact, review a fresh `configuration.importcompare` preview and confirm the operation. The preview and post-rollback validation use the stored artifact format explicitly; current backup artifacts are private YAML exports. Before restoring the older artifact, ZTUM creates a fresh recovery backup of the current state and verifies that it exactly matches the current export participating in rollback preflight.

If an import has occurred but final validation cannot prove the expected state, the module reports that a write occurred and does not retry automatically.

## Installing multiple missing official templates

In the `Not installed` status filter, Super Admins can use row checkboxes or the header select-all control and choose **Prepare selected installations**. Missing-template selection remains bounded to 500 catalog entries.

The preparation page runs one bounded request per UUID and classifies every candidate:

- `Ready`: the existing install preflight passed and produced bound SHA-256 evidence;
- `Blocked`: dependency/collision/source/import-preview/evidence prerequisites did not pass.

Only Ready candidates are executed by **Install ready templates**. Execution is browser-driven and request-bounded: each Ready UUID gets its own CSRF-protected HTTP request, reruns the full controlled install preflight immediately before its write, completes post-install validation, and only then advances to the next template. Execution stops on the first non-success.

Batch installation deliberately does not recursively install dependencies. If a selected template requires another template that is still missing, it remains Blocked even if that dependency is also selected. Install the dependency first, then prepare the dependent template again.

No automatic uninstall is performed after any ambiguous/failed install. Successful candidates remain installed and validated; candidates after the first failure are reported as Not attempted.

## Protecting an installed template from ZTUM updates

Super Admins can place an installed official template under a persistent **Never update** policy without removing it from inventory or read-only comparison.

1. Open **Data collection → Template updates**.
2. Use **All**, **Current** or **Update available** and select one or more installed official templates.
3. Choose **Never update** and confirm the policy change.
4. Use the dedicated **Never update** filter to review protected templates.
5. To reverse the policy, select the protected template and choose **Allow updates**.

Protected templates remain visible and comparable, but they are excluded from actionable update counts, normal update preparation, fresh update preflight and controlled import. The policy is keyed by official template UUID and stored privately in:

```text
/var/lib/zabbix-template-update-manager/update-policy.json
```

Policy changes are Super-Admin-only and CSRF-protected. If the policy store is malformed or unreadable, ZTUM fails closed for update writes rather than silently ignoring the protection.

## Installing an official template that is not local

Upstream-only catalog entries use a separate installation workflow. They are never treated as updates and never enter the update batch.

Installation review verifies:

- the official UUID exists in the validated index;
- the immutable commit/path/raw-source fingerprint matches the index;
- the isolated template identity matches the catalog record;
- no local template already owns the UUID or technical name;
- every linked template dependency is already installed;
- a read-only structural audit can resolve all statically verifiable value-map, master-item, dashboard-item, trigger-host and graph-host references;
- `configuration.importcompare` is creation-only (no update/remove/unresolved operation against existing configuration).

Only a super administrator can confirm the write. The install action reruns the entire preflight and rejects stale evidence before using the same `TemplateConfigurationImportService` that powers update and rollback.

After import, ZTUM resolves the new template by UUID and performs a fresh current-upstream validation. Because the template did not exist before the operation, there is no prior local rollback artifact. ZTUM therefore does not automatically uninstall a newly imported template if validation fails.

Request-bounded controlled batch installation does **not** recursively install missing dependencies. Install required dependencies first, then prepare dependent templates again.

## Persistent storage

ZTUM persistent runtime state uses `/var/lib/zabbix-template-update-manager`. The controlled-operation lock defaults to the private `locks/` subdirectory, rollback artifacts use `backups/`, and the Never update policy uses `update-policy.json`.

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

A fail-closed helper can validate or create the private runtime directories after resolving the PHP-FPM account:

```bash
sudo tools/ztum-runtime-setup.sh --check
sudo tools/ztum-runtime-setup.sh --apply
sudo tools/ztum-runtime-setup.sh --check
```

See [`docs/runtime-setup.md`](docs/runtime-setup.md).

## Quick install

For a new laboratory installation, use the beginner-friendly installer:

- [Quick Install — English](QUICK_INSTALL.md)
- [Instalação Rápida — Português do Brasil](QUICK_INSTALL_PT-BR.md)

English:

```bash
curl -fsSL \
  https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/main/tools/quickinstall.sh \
  -o /tmp/ztum-quickinstall.sh

sudo bash /tmp/ztum-quickinstall.sh
```

Português do Brasil:

```bash
curl -fsSL \
  https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/main/tools/quickinstall-pt-br.sh \
  -o /tmp/ztum-quickinstall-pt-br.sh

sudo bash /tmp/ztum-quickinstall-pt-br.sh
```

The quick installer is intentionally **new-install only**. It will not overwrite an existing ZTUM installation and it does not modify Nginx, Apache, PHP-FPM configuration, the Zabbix database or Zabbix templates.

## Manual installation for laboratory testing

Clone the current laboratory branch and record the exact commit used:

```bash
git clone https://github.com/kmansur/zabbix-template-update-manager.git
cd zabbix-template-update-manager
git checkout main
cat VERSION
git rev-parse HEAD
```

Expected `VERSION` for the current laboratory build:

```text
0.1.0-beta.57
```

Zabbix frontend modules are installed as one directory under the frontend `modules` directory. The package-specific path can vary, so locate it first rather than assuming a path:

```bash
find /usr/share/zabbix /usr/local/share/zabbix /var/www \
  -type d -name modules 2>/dev/null
```

Install the complete ZTUM directory below the correct `modules` directory. Then use:

```text
Administration → General → Modules → Scan directory
```

Confirm version **0.1.0-beta.57**, enable the module and open:

```text
Data collection → Template updates
```

If the upstream index cannot be loaded, an administrator/super administrator sees an **Upstream diagnostics** table showing the requested index URL, cURL availability, `allow_url_fopen`, OpenSSL availability and a bounded failure detail. The module still fails closed and does not guess official identity when the repository cannot be validated.

When upstream identity and version comparison succeed, update checkboxes are actionable only for a super administrator and only on official templates whose upstream vendor version is newer. **Prepare selected updates** sends the explicitly selected template IDs directly to the request-bounded preparation queue. Preparation performs the full safety analysis and prepares rollback evidence but does not import configuration. Only rows classified **Ready**, plus explicitly selected eligible reviewed overrides, can reach the later confirmed execution step.

For the exact beta test sequence, including update, batch stop behavior and rollback validation, follow [`docs/lab-test-plan.md`](docs/lab-test-plan.md).

## High-level workflow

```text
installed templates
      |
      v
UUID official identity + vendor-version comparison
      |
      +--> policy = Never update -> read-only inventory/comparison only
      |
      +--> managed -> checkbox/select update candidates (selection safety ceiling 500)
                    |
                    v
           batch safety preparation
                    |
       +------------+-------------+--------------+
       |            |             |              |
     Ready      Manual review   Conflict       Blocked
       |
       v
persistent rollback backup + verification
       |
       v
fresh per-template preflight evidence
       |
       v
explicit SUPER_ADMIN batch confirmation
       |
       v
sequential TemplateControlledUpdateService
       |
       +--> rerun fresh preflight for template N
       +--> evidence unchanged?
       +--> immutable source/hash/identity valid?
       +--> single configuration.import boundary
       +--> post-update validation
       |
       +--> success: continue to N+1
       |
       +--> any non-success: STOP
                  |
                  v
       updated / failed / not attempted report

individual comparison path
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

```text
official upstream catalog
      |
      +--> installed locally? yes -> update/comparison workflows above
      |
      +--> no -> Not installed
                 |
                 v
        Review installation
                 |
                 +--> UUID/name collision? -> BLOCK
                 +--> missing linked templates? -> BLOCK
                 +--> unresolved structural references? -> BLOCK
                 +--> importcompare updates/removes existing config? -> BLOCK
                 |
                 v
        creation-only preflight evidence
                 |
                 v
        explicit SUPER_ADMIN confirmation
                 |
                 v
        single configuration.import boundary
                 |
                 v
        post-install UUID/version/content validation
```

## Upstream source model

Compact indexes are generated from the canonical Zabbix source repository and record official template UUIDs, source paths, vendor metadata, exact source commit, canonical template-content fingerprints and per-path SHA-256 fingerprints of the exact raw YAML bytes. Runtime source retrieval is constrained to validated immutable commits and `templates/.../*.yaml` paths.

The runtime path validator uses an explicit allow-list suitable for current official source names, including the literal `+` used by some MikroTik model paths. `.` and `..` path segments remain forbidden. The upstream-index workflow feeds every generated index through the same PHP runtime decoder before publication, so generator/runtime path-policy drift fails CI instead of reaching the lab.

The source acquisition workflow prefers an official GitHub mirror when the required ref is available there and falls back to the canonical `git.zabbix.com` repository for historical refs. Checkout retries and HTTP/1.1 are used to reduce transient source-fetch failures. The generated index still records the exact official commit/ref used for runtime verification.

The module may use a previously validated stale local index when refresh fails. If no validated cache exists, upstream identity becomes unavailable instead of being guessed.

For isolated environments, the same runtime repositories can read a manifest/hash-verified local bundle instead of contacting the public endpoints. `ZTUM_OFFLINE_ONLY=1` forbids network fallback when a required offline artifact is missing. See [`docs/offline-mode.md`](docs/offline-mode.md).

Configuration-changing update, install and rollback controllers also acquire one global non-blocking ZTUM operation lock before their fresh authoritative preflight and hold it through post-write validation. This prevents two operators on the same lock filesystem from racing controlled imports. See [`docs/concurrency.md`](docs/concurrency.md).

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
- [`docs/runtime-validation-notes.md`](docs/runtime-validation-notes.md)
- [`docs/lab-test-plan.md`](docs/lab-test-plan.md)
- [`docs/ui-style.md`](docs/ui-style.md)
- [`docs/roadmap.md`](docs/roadmap.md)
- [`docs/project-status.md`](docs/project-status.md)
- [`docs/offline-mode.md`](docs/offline-mode.md)
- [`docs/concurrency.md`](docs/concurrency.md)
- [`docs/runtime-setup.md`](docs/runtime-setup.md)
- [`docs/test-metrics.md`](docs/test-metrics.md)
- [`docs/release-policy.md`](docs/release-policy.md)

## Development validation

The CI pipeline currently checks:

```text
PHP syntax
manifest/action contract
VERSION ↔ manifest version consistency
controlled-write boundary
PHP unit/contract tests
native Zabbix UI guard
runtime security guard
runtime setup helper validation
upstream-index generator validation
offline-bundle generator validation
GitHub workflow structure/action-pin validation
runtime decoder validation of generated upstream indexes before publication
```

Local equivalent:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/validate_version.php
php tests/read_only_guard.php
php tests/ui_native_guard.php
for test in tests/unit/*Test.php; do php "$test"; done
php tests/security_guard.php
tests/test_runtime_setup.sh
python -m py_compile tools/build_upstream_index.py tools/build_offline_bundle.py tools/aggregate_coverage.py
python tests/test_build_upstream_index.py
python tests/test_build_offline_bundle.py
python tests/test_workflows.py
```

A green CI run proves automated checks only. It does not replace runtime field validation on Zabbix 7.x and 8.x.

## License

A project license has not yet been selected. This remains a release/publication item before a stable production release.
