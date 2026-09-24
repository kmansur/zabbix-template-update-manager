# Pre-RC engineering audit — 0.1.0-beta.59

Date: 2026-09-24

This report records the result of the autonomous pre-RC hardening cycle completed for Zabbix Template Update Manager (ZTUM). It separates implemented code, reproducible automation evidence, real field evidence and release/governance state. It is not a production certification.

## Executive summary

**Estimated completion toward stable 1.0 core scope: 95%.**

The core product is feature-complete for the declared official-Zabbix-repository scope. The remaining blockers are primarily real controlled-write field evidence on the supported Zabbix generations and an independent external security/code review before any production recommendation.

| Area | Completion | Evidence / remaining work |
|---|---:|---|
| Discovery / catalog / upstream identity | 97% | UUID identity, official indexes, immutable source fingerprints, initial-release baselines and offline mode implemented |
| Comparison / risk / readiness | 98% | importcompare, historical BASE, rename-aware history, three-way analysis, risk/readiness, dependency and inherited host-impact analysis implemented |
| Update / install / rollback write paths | 98% | single configuration-import boundary, fresh preflight, evidence binding, backups, post-validation, reviewed paths, stop-on-failure and rollback implemented |
| Native Zabbix UI/UX | 98% | native catalog/filter/pagination, light/dark inheritance, operation history and native browser smoke implemented |
| Automated validation / CI | 99% | PHP 8.2/8.3/8.4, Zabbix 7/8 symbol checks, full-stack Chromium smoke, security guards, accessibility and coverage floors |
| Runtime resilience | 96% | request-bounded preparation, immutable caches, offline fail-closed behavior, persistent locks and bounded operation history |
| Real field validation | 70% | substantial Zabbix 7 evidence; partial Zabbix 8 evidence; complete controlled-write matrix remains open |
| Release engineering | 100% | license/notice, release workflow, runtime gates, archives, checksums and formal prerelease published |

Weighted result: **95%**.

## Autonomous hardening completed

The pre-RC cycle closed the implementation/process items identified by the previous engineering review:

- persistent controlled-operation lock defaults now use the private runtime root at `/var/lib/zabbix-template-update-manager/locks`;
- catalog/read-only module access is limited to Zabbix Administrators and Super Admins; configuration writes and update-policy mutation remain Super-Admin-only;
- non-official templates show update policy as **Not applicable** rather than **Managed**;
- documentation and roadmap drift were synchronized with implemented behavior;
- the repository now has a single consolidated 1.0 field-readiness issue instead of stale beta-specific issue clutter;
- AGPL-3.0-only licensing and explicit attribution/independence notices are present;
- inherited/indirect host impact analysis is implemented;
- bounded private operation history is implemented and exposed through a native administrator-only page;
- the large update/install batch JavaScript implementations were extracted into registered module assets;
- PHP 8.2, 8.3 and 8.4 runtime matrices are enforced;
- native frontend symbols are checked against Zabbix 7 and 8 source trees;
- disposable official Zabbix 7 and 8 full-stack browser smoke is enforced;
- the rolling Zabbix 8 smoke image must report major version 8 or the test fails;
- Chromium validates real module registration, Super Admin login, catalog rendering, Name filtering, Operation history and both dark/light themes;
- accessibility contracts are enforced;
- coverage is a regression gate rather than report-only telemetry;
- release-shaped archives are validated in CI;
- merged pre-RC topic branches were removed; only `main` and the intentional `upstream-index` data branch remain.

## Formal prerelease

The first formal laboratory prerelease is:

`v0.1.0-beta.59`

The annotated tag points to commit:

`3694ab0ce4a687399e186ec9cde2774d0209c434`

Release assets:

| Asset | SHA-256 |
|---|---|
| `zabbix-template-update-manager-0.1.0-beta.59.tar.gz` | `4eb075bc70d666ccb226f6c949ae96a732494566ea3b23a2396e3ae8ca7f6d6d` |
| `zabbix-template-update-manager-0.1.0-beta.59.zip` | `889d96bc5cc88d3b35585f68a214e7c454a754d24fbe1f186ed326f7b24a9748` |
| `SHA256SUMS` | `415fc0d48fbfc8bdbbcc51fbaeab678dbce577f7d9bb304a088b11da2a46f336` |

The release pipeline checked out the immutable tag, reran the real disposable frontend smoke on both supported generations and only then published the assets.

## Automated compatibility evidence

The beta.59 release/runtime gates recorded:

- disposable Zabbix **7.0.31** full frontend smoke: passed;
- disposable Zabbix **8.0.0** full frontend smoke: passed;
- dark-theme catalog render: passed;
- light-theme catalog render: passed;
- native Name filter interaction: passed;
- Operation history render: passed;
- module-resource HTTP/browser fatal-error guard: passed;
- PHP 8.2 unit/syntax matrix: passed;
- PHP 8.3 unit/syntax matrix: passed;
- PHP 8.4 unit/syntax matrix: passed;
- native frontend symbol check / Zabbix 7: passed;
- native frontend symbol check / Zabbix 8: passed;
- security workflow: passed;
- release-package smoke: passed.

Current Xdebug metrics at the beta.59 hardening point:

- runtime PHP files observed by standalone tests: **49 / 72 (68.1%)**;
- executable lines observed: **5240**;
- observed executable lines executed: **3484 (66.5%)**;
- enforced floors: **65% file reachability** and **64% observed executable-line coverage**.

These percentages intentionally do not claim full application coverage; views and real frontend interactions are additionally protected by contract, Chromium and field validation.

## Security posture

The repository preserves one approved Zabbix configuration-write boundary:

`src/Service/TemplateConfigurationImportService.php`

Automated guards reject additional known Zabbix configuration write paths, direct database writes, unsafe shell execution patterns, unsafe deserialization, world-writable runtime permissions and common credential/private-key patterns.

A final repository search found no matches for the reviewed internal-network/private-key/credential patterns. Public authorship and project attribution remain intentionally visible.

No automatic retry or rollback is performed after an ambiguous import state. Update/install/rollback execution remains fail-closed and requires fresh server-side evidence.

## Remaining blockers before RC / stable 1.0

The remaining high-value work cannot be truthfully replaced by repository-only automation:

1. Complete the real Zabbix 7.x and 8.x controlled-write field matrix using the immutable beta.59 release artifact.
2. Record standard update, reviewed update, local-overwrite acknowledgement, batch stop-on-failure, controlled installation/failure handling, rollback/recovery backup, offline-only behavior and serialization on real supported instances.
3. Record the remaining negative-path evidence: permission denial, CSRF rejection, stale/tampered evidence rejection and backup-tamper rejection.
4. Confirm the complete operator workflow in both themes on the real target environments; disposable automation already proves basic light/dark rendering.
5. Arrange an independent external security/code review before recommending production use.

The following remain non-blocking post-RC/1.0 enhancements unless field evidence exposes a need:

- drill-down/filtering for very large three-way comparisons;
- deterministic disposable controlled-write fixtures that do not weaken the production safety model;
- post-1.0 repository/provider expansion.

## Release recommendation

Beta.59 is suitable as the immutable **laboratory/community field-test artifact**.

It should **not** be labeled RC or production-ready until the real controlled-write matrix above is closed. No unresolved implementation blocker was identified in this audit; the gating work is now predominantly validation/evidence rather than missing core functionality.
