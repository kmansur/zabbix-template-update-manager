# Project status and engineering audit — 0.1.0-beta.40

Date: 2026-09-23

This document is an engineering snapshot, not a release certification. Percentages are planning estimates based on implemented scope, automated validation and remaining field/release work.

## Overall estimate toward stable 1.0 core scope

**89% complete**

The estimate deliberately separates implementation from automation and field validation. Provider expansion (GitHub/GitLab/community/private/custom repositories) remains post-1.0 scope.

| Area | Weight | Estimated completion | Notes |
|---|---:|---:|---|
| Discovery/catalog/upstream identity | 10% | 96% | Core official catalog/index identity plus verified offline source mode implemented |
| Comparison/risk/readiness | 20% | 93% | Three-way, risk, readiness and dependency-aware comparison implemented |
| Update/install/rollback write paths | 20% | 95% | Controlled writes, evidence, validation, reviewed overrides and serialization implemented |
| Native Zabbix UI/UX | 10% | 91% | Native refactor + lab warning complete; cross-version visual field pass still required |
| Automated validation/CI | 10% | 97% | CI/UI/security/workflow/offline/runtime/coverage gates implemented; browser E2E and independent security review remain |
| Runtime resilience | 10% | 87% | Request-bounded flows, offline mode, runtime helper and operation lock implemented; per-template timeout/rename edge cases remain |
| Field validation | 10% | 65% | Stronger Zabbix 7.x evidence than Zabbix 8.x; full matrix incomplete |
| Release engineering | 10% | 78% | Release workflow/assets/checksums/policy implemented; license and first validated formal tag/release remain |

Weighted result: **89%**.

## Major beta.40 improvements

The external review's principal technical/process findings were converted into implementation work:

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

1. **Project license**
   - No license has been selected yet.
   - This remains a governance/legal blocker for stable public adoption and should be an explicit maintainer decision rather than guessed by automation.

2. **Zabbix 8.x field-validation gap**
   - The code supports major versions 7 and 8, but the complete real workflow matrix still needs to be recorded on Zabbix 8.x.

3. **Repeated per-template timeout class**
   - Request-bounded architecture prevents cumulative batch timeouts, but an individual preparation/source/history operation can still hit proxy/runtime limits.
   - Next work: stage timing, cache/reuse review and safe preparation-only retry improvements. Never retry an ambiguous configuration write.

4. **Historical rename edge case**
   - `followRenames=true` makes history enumeration rename-aware, but historical raw-source retrieval still uses the current path.
   - Offline bundle generation intentionally preserves the same fail-closed boundary rather than guessing old paths.

## Medium-priority work

- browser/E2E visual/accessibility regression suite;
- dedicated operation-history/audit UI;
- inherited/indirect host impact analysis;
- move large batch JavaScript to dedicated assets without weakening server-authoritative controls;
- independent security/code review before any production recommendation;
- first formal prerelease tag/GitHub Release after beta.40 field evidence and license decision.

## Stable 1.0 blockers

- select and publish project license;
- complete/record Zabbix 7.x and 8.x field-validation matrix;
- validate fresh install, standard update, reviewed update, local-overwrite acknowledgement, batch stop-on-failure, offline mode, concurrency and rollback on real supported instances;
- resolve or explicitly accept/document remaining timeout and historical-rename limitations;
- complete compatibility/homologation notes;
- confirm no unresolved high-severity safety/data-loss issue;
- publish at least one validated formal prerelease using the new release pipeline.

## Completion interpretation

A green CI/Security run means automation validation passed. It does not make beta.40 production-ready.

Track these states independently:

- implementation ready;
- automation validated;
- field validated;
- release/governance ready.
