# Zabbix Template Update Manager

Zabbix Template Update Manager (ZTUM) is an independent native Zabbix frontend module for discovering, matching, comparing and safely managing updates of installed Zabbix templates.

It does not modify Zabbix core files and is not an official Zabbix LLC product.

## Status

Current version: **0.1.0-beta.19**

This version is intended for **laboratory testing**.

- Implementation: ready for end-to-end laboratory validation.
- Automation validation: must be green for the beta snapshot commit.
- Field validation: in progress on real Zabbix 7.x and 8.x lab instances.
- Production use: not yet recommended.

Beta.19 adds an official template catalog and controlled installation for official templates that are present upstream but absent locally. Missing templates appear as `Not installed`; installation uses immutable source identity, dependency/collision checks, creation-only `configuration.importcompare`, explicit super-administrator confirmation, the existing single configuration-import boundary and fresh post-install validation. The expanded catalog also uses native Zabbix pagination.

A fixed laboratory snapshot is published as branch `release/0.1.0-beta.19` after the beta.17 changes are merged and validated. A formal Git tag/GitHub Release remains intentionally deferred until runtime validation is sufficiently complete.

See [`docs/lab-test-plan.md`](docs/lab-test-plan.md) before installing the beta.

## Supported Zabbix generations

- Zabbix 7.x
- Zabbix 8.x

The module detects the frontend `ZABBIX_VERSION` at runtime and fails closed for unsupported/unknown major versions.

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
- read-only selected-template review that rebuilds authoritative inventory/upstream/version state for the chosen subset;
- bounded batch safety preparation for up to 25 explicitly selected templates, executed one candidate per HTTP request with visible progress;
- automatic creation/refresh of rollback artifacts for standard-path candidates (none/low plus narrowly recognized bounded-medium changes) and explicitly reviewed manual-update candidates;
- batch classification into Ready, Manual review, Conflict and Blocked; reviewed override candidates never become unattended batch Ready;
- controlled sequential update of Ready templates only;
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
- administrator-only diagnostics for upstream index endpoint and PHP HTTP transport capabilities.

Batch execution does not create a second write path. Each Ready template is executed through `TemplateControlledUpdateService`, which reruns fresh preflight, verifies the page evidence has not changed, rebuilds the immutable upstream candidate and then uses the same single configuration-import service already used by individual update/rollback flows.

## Safety model

Official identity is based on template UUID, never on vendor metadata alone. Version comparison, content comparison and update eligibility are separate stages.

For an outdated official template, the standard controlled update path requires a proven historical baseline, complete three-way analysis, no conflict, no local-overwrite risk, and a persistent rollback artifact that exactly matches a fresh export of the installed template. `none`/`low` technical risk is standard-path eligible. Medium impact remains manual by default, except for narrowly recognized bounded changes explicitly marked `standard_path_eligible` by the risk analyzer; the current allowlist is limited to discard-only preprocessing maintenance. High risk and any local overwrite use the separate explicit reviewed path, while conflict/unresolved evidence remains blocked.

Immediately before an update, ZTUM reruns the authoritative preflight, compares the explicit confirmation evidence with fresh server-side evidence, re-fetches the official template from the exact immutable upstream commit/path, verifies the raw YAML SHA-256 against the path-specific upstream index fingerprint, preserves the separate canonical template-content fingerprint in the evidence, and revalidates template identity. The selected source is then isolated with its required template/host groups plus top-level graphs and triggers that are exclusively owned by that template; unsafe cross-template dependencies are rejected.

The repository contains exactly one approved Zabbix configuration-write boundary:

```text
src/Service/TemplateConfigurationImportService.php
```

Individual update, sequential batch update and rollback all reuse that service. CI rejects additional known Zabbix API write paths and direct database writes.

Batch execution is deliberately bounded to 25 selected templates and stops immediately when one template does not return a successful validated update. Remaining templates are reported as **Not attempted**. Automatic rollback is never attempted because a failed post-write state may require operator inspection before choosing the correct recovery artifact.

Rollback is never automatic. A super administrator must explicitly select a valid stored artifact, review a fresh `configuration.importcompare` preview and confirm the operation. The preview and post-rollback validation use the stored artifact format explicitly; current backup artifacts are private YAML exports. Before restoring the older artifact, ZTUM creates a fresh recovery backup of the current state and verifies that it exactly matches the current export participating in rollback preflight.

If an import has occurred but final validation cannot prove the expected state, the module reports that a write occurred and does not retry automatically.

## Installing an official template that is not local

Upstream-only catalog entries use a separate installation workflow. They are never treated as updates and never enter the update batch.

Installation review verifies:

- the official UUID exists in the validated index;
- the immutable commit/path/raw-source fingerprint matches the index;
- the isolated template identity matches the catalog record;
- no local template already owns the UUID or technical name;
- every linked template dependency is already installed;
- `configuration.importcompare` is creation-only (no update/remove/unresolved operation against existing configuration).

Only a super administrator can confirm the write. The install action reruns the entire preflight and rejects stale evidence before using the same `TemplateConfigurationImportService` that powers update and rollback.

After import, ZTUM resolves the new template by UUID and performs a fresh current-upstream validation. Because the template did not exist before the operation, there is no prior local rollback artifact. ZTUM therefore does not automatically uninstall a newly imported template if validation fails.

Beta.19 intentionally does **not** recursively install missing dependencies or batch-install catalog entries. Install required dependencies individually first.

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

Use the fixed beta snapshot rather than the moving development branch:

```bash
git clone https://github.com/kmansur/zabbix-template-update-manager.git
cd zabbix-template-update-manager
git fetch origin release/0.1.0-beta.19
git checkout -B release/0.1.0-beta.19 origin/release/0.1.0-beta.19
cat VERSION
git rev-parse HEAD
```

Expected `VERSION`:

```text
0.1.0-beta.19
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

Confirm version **0.1.0-beta.19**, enable the module and open:

```text
Data collection → Template updates
```

If the upstream index cannot be loaded, an administrator/super administrator sees an **Upstream diagnostics** table showing the requested index URL, cURL availability, `allow_url_fopen`, OpenSSL availability and a bounded failure detail. The module still fails closed and does not guess official identity when the repository cannot be validated.

When upstream identity and version comparison succeed, checkboxes are shown only on official templates whose upstream vendor version is newer. **Review selected updates** sends only those selected template IDs to the bounded review action. A super administrator may then choose **Prepare selected updates**, which performs the full safety analysis and prepares rollback evidence. Only rows classified **Ready** can be submitted to **Update ready templates**.

For the exact beta test sequence, including update, batch stop behavior and rollback validation, follow [`docs/lab-test-plan.md`](docs/lab-test-plan.md).

## High-level workflow

```text
installed templates
      |
      v
UUID official identity + vendor-version comparison
      |
      +--> checkbox/select update candidates (max 25 per batch)
                    |
                    v
          selected-template review
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

## Upstream source model

Compact indexes are generated from the canonical Zabbix source repository and record official template UUIDs, source paths, vendor metadata, exact source commit, canonical template-content fingerprints and per-path SHA-256 fingerprints of the exact raw YAML bytes. Runtime source retrieval is constrained to validated immutable commits and `templates/.../*.yaml` paths.

The runtime path validator uses an explicit allow-list suitable for current official source names, including the literal `+` used by some MikroTik model paths. `.` and `..` path segments remain forbidden. The upstream-index workflow feeds every generated index through the same PHP runtime decoder before publication, so generator/runtime path-policy drift fails CI instead of reaching the lab.

The source acquisition workflow prefers an official GitHub mirror when the required ref is available there and falls back to the canonical `git.zabbix.com` repository for historical refs. Checkout retries and HTTP/1.1 are used to reduce transient source-fetch failures. The generated index still records the exact official commit/ref used for runtime verification.

The module may use a previously validated stale local index when refresh fails. If no validated cache exists, upstream identity becomes unavailable instead of being guessed.

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

## Development validation

The CI pipeline currently checks:

```text
PHP syntax
manifest/action contract
VERSION ↔ manifest version consistency
controlled-write boundary
PHP unit/contract tests
upstream-index generator validation
runtime decoder validation of generated upstream indexes before publication
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
