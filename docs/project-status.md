# Project status and engineering audit — 0.1.0-beta.58

Date: 2026-09-23

This document is an engineering snapshot, not a release certification. Percentages are planning estimates based on implemented scope, automated validation and remaining field/release work.

## Overall estimate toward stable 1.0 core scope

**94% complete**

The estimate deliberately separates implementation from automation and field validation. Provider expansion (GitHub/GitLab/community/private/custom repositories) remains post-1.0 scope.

| Area | Weight | Estimated completion | Notes |
|---|---:|---:|---|
| Discovery/catalog/upstream identity | 10% | 97% | Official catalog/index identity, immutable initial-release baselines and verified offline source mode implemented |
| Comparison/risk/readiness | 20% | 98% | Historical/initial-release BASE, rename-aware history, three-way analysis, persistent Never update policy, dependency-aware comparison and inherited host-impact analysis implemented |
| Update/install/rollback write paths | 20% | 98% | Controlled writes, evidence, post-write validation, reviewed overrides, persistent serialization and supplemental operation-history instrumentation implemented |
| Native Zabbix UI/UX | 10% | 98% | Native presentation/copy pass, Never update controls, CPagerHelper-only pagination and Templates-page-style Name + Status filtering implemented; cross-version light/dark field validation remains |
| Automated validation/CI | 10% | 99% | CI/UI/security/workflow/offline/runtime/coverage gates plus PHP 8.2/8.3/8.4, Zabbix 7/8 frontend-symbol and Chromium/accessibility regressions implemented; independent external review remains |
| Runtime resilience | 10% | 96% | Request-bounded flows, immutable history caching, rename-aware paths, initial-release BASE, offline mode, persistent private lock defaults and bounded operation history implemented |
| Field validation | 10% | 70% | Zabbix 7.x has substantial write-path evidence and beta.57 catalog/filter dark-theme evidence; Zabbix 8.x catalog/pager loading is confirmed after the compatibility fix, while the complete beta.58 write-path and cross-theme matrix remains open |
| Release engineering | 10% | 92% | Quick install, release workflow/assets/checksums/policy, AGPL-3.0-only license/notice and release-package smoke gates implemented; first validated public prerelease remains |

Weighted result: **94%**.

## Major improvements through beta.58

The external review's principal technical/process findings were converted into implementation work:

- native catalog filtering: beta.57 fixes partial Name search across the complete displayed template name (`name` + `technical_name`) and switches the filter to the same `CFormGrid`/medium-width pattern used by the native Zabbix Templates page;

- native cross-version pagination: beta.55 removed the custom All/Pages wrapper and delegates catalog paging entirely to Zabbix `CPagerHelper`, preserving one codebase while letting each supported frontend generation render its own native pager;

- persistent update protection: per-template **Never update** policy with UUID-based private storage, catalog filter, explicit reversal and fail-closed enforcement at preparation/preflight/import gates;

- air-gapped operation: implemented through a verified local bundle with offline-only fail-closed mode;
- architecture/documentation drift: synchronized to controlled-write reality;
- test transparency: dedicated quality-metrics workflow with Xdebug coverage/reachability reporting;
- backup/runtime setup friction: safe setup/check helper added;
- missing concurrency semantics: global operation serialization added and documented;
- release engineering: formal tag/release workflow with archives/checksums added;
- security automation: runtime guard + dependency audit workflow added;
- community intake: structured issue/field-validation/PR templates added;
- beta cadence: release policy now separates development commits from field-test snapshots.

## Remaining high-priority blockers

1. **Zabbix 8.x field-validation gap**
   - The code supports major versions 7 and 8, but the complete real workflow matrix still needs to be recorded on Zabbix 8.x.

2. **Cross-version UI field validation**
   - Beta.57 includes the native presentation/copy pass, **Never update / Allow updates** controls, native Templates-page-style Name + Status filtering and `CPagerHelper`-only pagination.
   - Light/dark rendering and all operator flows still need field evidence on supported Zabbix 7.x and 8.x instances before community UI validation is considered complete.

## Medium-priority work

- expand browser regression from isolated batch assets to a full disposable Zabbix page/render smoke when a stable automated environment is available;
- add drill-down/filtering for very large three-way comparisons;
- independent security/code review before any production recommendation;
- first validated public prerelease tag/GitHub Release after the beta.58 real field pass.

## Stable 1.0 blockers

- complete/record Zabbix 7.x and 8.x field-validation matrix;
- validate fresh install, standard update, reviewed update, local-overwrite acknowledgement, batch stop-on-failure, offline mode, concurrency and rollback on real supported instances;
- complete the beta.58 cross-version native-UI field pass and record any remaining browser/runtime limitations;
- complete compatibility/homologation notes;
- confirm no unresolved high-severity safety/data-loss issue;
- publish at least one validated formal prerelease using the new release pipeline.

## Completion interpretation

A green CI/Security/browser compatibility run means automation validation passed. It does not make beta.58 production-ready.

Track these states independently:

- implementation ready;
- automation validated;
- field validated;
- release/governance ready.
