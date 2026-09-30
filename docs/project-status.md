# Project status — engineering handoff before RC validation

Date: 2026-09-30

This document separates repository-side engineering completion from field validation and RC promotion.

## Current state

**Repository-side engineering readiness: 100%.**

The declared ZTUM core scope is implemented and the repository contains the deterministic validation, security, compatibility, packaging, browser/runtime smoke and release infrastructure required to enter the dedicated RC validation cycle.

This is **not** an RC or production-readiness declaration. Real controlled-write validation remains a separate gate.

| State | Status | Notes |
|---|---|---|
| Implementation ready | **Complete** | No known repository-side implementation blocker remains for the declared official-Zabbix-repository scope. |
| Automation infrastructure | **Complete** | CI, security guards, PHP matrix, Zabbix 7/8 frontend checks, Chromium/full-stack read-only smoke, accessibility, coverage floors and release-package gates are implemented. |
| Release engineering | **Complete** | Formal prerelease/tag workflow, runtime smoke, archives and SHA256SUMS are implemented; `v0.1.0-beta.61` is the next immutable laboratory artifact for the remaining RC matrix. |
| Field validation | **In progress** | Zabbix 7.0.31 has recorded read-only/UI, policy/history, BASE/three-way analysis, reviewed update, install, rollback/recovery backup, post-write validation and successful reviewed multi-template batch execution. Stop-on-first-failure, resilience/offline/serialization and negative-security paths remain open, followed by the corresponding Zabbix 8 matrix. |
| RC status | **Not declared** | Promotion occurs only after the validation matrix is reviewed. |
| Production recommendation | **Not declared** | Requires successful RC validation and final security/release review. |

## What 100% means here

The 100% figure applies to engineering work that can be completed and reviewed in the repository without pretending that real field evidence already exists.

Completed areas include:

- discovery/catalog/upstream identity;
- historical BASE and three-way analysis;
- risk/readiness/dependency/host-impact analysis;
- controlled update/install/rollback flows;
- backup integrity and fresh preflight evidence;
- bounded batch behavior and stop-on-failure semantics;
- Never update policy;
- offline-only mode and global operation serialization;
- private operation history;
- native Zabbix UI integration;
- Zabbix 7.x/8.x compatibility infrastructure;
- PHP 8.2/8.3/8.4 validation;
- browser/accessibility/runtime smoke;
- security guards and dependency audits;
- release packaging/checksums;
- documentation and field-evidence tooling.

## RC validation gate

The following must now be validated against the immutable field-test artifact and recorded before RC promotion:

1. real Zabbix 7.x controlled update/install/rollback matrix;
2. real Zabbix 8.x controlled update/install/rollback matrix;
3. standard, reviewed and local-overwrite update paths;
4. request-bounded batch stop-on-first-failure behavior;
5. install failure diagnostics and uncertain-state stop behavior;
6. recovery backup and controlled rollback;
7. offline-only bundle behavior;
8. operation serialization;
9. permission, CSRF, stale/tampered evidence and tampered-backup negative paths;
10. real operator light/dark workflow on Zabbix 8.x (Zabbix 7.0.31 is already recorded on beta.60);
11. fresh module installation and in-place upgrade;
12. final independent security/code review before any production recommendation.

The authoritative execution checklist remains GitHub issue #64 and `docs/lab-test-plan.md`.

## Change-control rule during validation

The engineering scope is now in feature freeze.

If validation exposes a defect:

1. stop the affected write scenario;
2. classify the failure and preserve sanitized evidence;
3. return to implementation;
4. add a deterministic regression guard where practical;
5. publish a new immutable beta artifact;
6. repeat the affected validation;
7. only then reconsider RC promotion.

No new feature should enter the RC candidate path unless it is required to correct a validation blocker.

## References

- [Engineering readiness handoff — 2026-09-29](audits/2026-09-29-engineering-readiness-handoff.md)
- [Pre-RC engineering audit — beta.59](audits/2026-09-24-beta59-pre-rc-audit.md)
- [Production readiness](production-readiness.md)
- [Compatibility](compatibility.md)
- [Laboratory test plan](lab-test-plan.md)
- [Release policy](release-policy.md)
