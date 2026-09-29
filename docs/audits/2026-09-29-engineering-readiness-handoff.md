# Engineering readiness handoff — 2026-09-29

Date: 2026-09-29  
Baseline branch: `main`  
Baseline commit: `38135ae8d30dbd951c9d55b6b013a5558698c8b0`  
Field-test artifact: `v0.1.0-beta.59`

## Scope of this handoff

This document records completion of the repository-side engineering scope before the dedicated RC validation cycle.

**Engineering readiness: 100%.**

This percentage means:

- the declared ZTUM core scope is implemented;
- deterministic repository-side validation, security, compatibility, packaging and browser/runtime smoke infrastructure exists;
- release engineering is implemented;
- documentation needed to execute the RC validation cycle exists;
- no known repository-side implementation blocker remains.

It does **not** mean RC, production-ready or stable 1.0. Real controlled-write field validation remains a separate gate and must be executed before RC promotion.

## Completed engineering scope

### Core product

- official Zabbix template discovery and UUID identity;
- official upstream indexes and immutable source fingerprints;
- vendor-version comparison;
- historical BASE resolution, including rename-aware history and initial release baselines;
- BASE / LOCAL / UPSTREAM three-way analysis;
- technical-risk, readiness, dependency and host-impact analysis;
- persistent verified rollback backups;
- fail-closed preflight evidence;
- controlled single-template and sequential bounded batch updates;
- explicit reviewed paths for higher-risk and local-overwrite cases;
- controlled installation of missing official templates;
- structural reference audit;
- controlled rollback with recovery backup and post-validation;
- persistent Never update / Allow updates policy;
- global controlled-operation serialization;
- verified offline-only bundle mode;
- bounded private operation history.

### Security and failure semantics

- Super-Admin-only write paths;
- administrator/read-only separation for catalog access;
- CSRF and explicit confirmation gates;
- stale/tampered evidence rejection;
- backup integrity verification;
- one approved Zabbix configuration-write boundary;
- no automatic retry after ambiguous write state;
- no automatic rollback after ambiguous write state;
- fail-closed behavior for unsupported/unknown Zabbix generations;
- runtime directory hardening and private default paths;
- deterministic repository security guards and dependency audits.

### Compatibility and UI

- one source tree for Zabbix 7.x and 8.x;
- PHP 8.2 / 8.3 / 8.4 validation matrix;
- native frontend symbol checks against Zabbix 7 and 8;
- native Zabbix filter, pagination, forms, tables and status presentation;
- light/dark theme inheritance;
- browser regression for batch assets;
- disposable official Zabbix 7/8 full-frontend Chromium smoke;
- module registration, login, catalog rendering, Name filtering and Operation history smoke;
- accessibility contract guards.

### Release engineering

- semantic beta version in `VERSION` and `manifest.json`;
- formal Git tag / GitHub prerelease process;
- release runtime smoke for Zabbix 7/8;
- release-shaped `.tar.gz` and `.zip` packages;
- `SHA256SUMS`;
- immutable beta.59 laboratory artifact;
- AGPL-3.0-only license and NOTICE;
- quick-install and runtime setup helpers;
- privacy-conscious field-evidence collector;
- documented release, compatibility, laboratory and production-readiness policies.

## Repository hygiene completed

- stale Dependabot PRs #65, #66 and #67 were closed because the corresponding first-party Actions majors are already present on `main`;
- no TODO/FIXME/HACK markers were found by the final repository search performed during this handoff;
- no internal NetTech/MPC test identifiers were found by the same final search;
- the merged beta.59 field-evidence work is present on `main`;
- `upstream-index` remains an intentional data branch;
- the merged `test/beta59-field-evidence` branch is no longer required for engineering work and may be deleted as repository housekeeping.

## Deliberately deferred to the RC validation cycle

The following are **validation gates**, not missing implementation:

1. complete real Zabbix 7.x controlled-write matrix;
2. complete real Zabbix 8.x controlled-write matrix;
3. validate standard and reviewed update paths;
4. validate local-overwrite acknowledgement;
5. validate batch stop-on-first-failure semantics;
6. validate missing-template install and controlled failure behavior;
7. validate rollback/recovery backup;
8. validate offline-only and serialization behavior;
9. validate permission, CSRF, stale/tampered evidence and backup-tamper negative paths;
10. record final light/dark operator workflow on both supported generations;
11. validate fresh module install and in-place upgrade from the previous supported build;
12. perform final independent security/code review before production recommendation.

These items remain tracked in GitHub issue #64 and the laboratory test plan.

## Promotion rule

Do not create an RC solely because engineering readiness is 100%.

Promotion to RC requires the validation matrix to be executed against an immutable artifact and the resulting evidence reviewed. If validation exposes a defect, return to implementation, fix it, add a regression guard where practical, publish a new immutable beta artifact and repeat the affected validation.

## Current state

**Repository-side engineering: 100% complete.**  
**RC validation: pending by design.**  
**RC status: not yet declared.**
