# Production readiness

ZTUM tracks four independent states:

1. **Implementation readiness** — required functionality exists.
2. **Automation validation** — deterministic CI/security/quality gates pass.
3. **Field validation** — the exact build has been exercised on real supported Zabbix environments.
4. **Release readiness** — licensing, compatibility notes, release assets and final gates are complete.

A green workflow does not by itself make the module production-ready.

## Current posture

- Core implementation: advanced / near feature-complete.
- Automated validation: strong, including PHP 8.2/8.3/8.4, Zabbix 7/8 frontend-symbol compatibility, disposable official Zabbix 7/8 Chromium frontend smoke, batch-asset smoke, accessibility contracts and minimum coverage/reachability floors.
- Zabbix 7.x field evidence: substantial but the final consolidated 1.0 matrix is not closed.
- Zabbix 8.x field evidence: partial; full controlled write/rollback/offline validation remains open.
- License: AGPL-3.0-only.
- Formal laboratory prerelease: `v0.1.0-beta.59` published with `.tar.gz`, `.zip` and `SHA256SUMS` after Zabbix 7/8 release-runtime smoke.
- Production recommendation: not yet.

## Stable 1.0 gates

Before stable 1.0:

- CI, security, quality and runtime smoke gates green;
- no unresolved high-severity safety/data-loss issue;
- Zabbix 7.x and 8.x field matrix recorded;
- fresh install and in-place module upgrade validated;
- standard update, reviewed update, local-overwrite acknowledgement and batch stop-on-failure validated;
- controlled installation and failure handling validated;
- rollback + recovery backup validated;
- offline-only and serialization behavior validated;
- light/dark UI pass recorded for both supported generations;
- compatibility documentation current;
- license/notice present;
- a formal prerelease built and installed from its generated release artifacts;
- checksums verified.

## Non-blocking enhancements

The following improve maintainability/operations but do not independently prove release safety:

- deepen disposable full-Zabbix smoke beyond read-only catalog/history rendering when a safe deterministic write fixture is available;
- drill-down/filtering for very large three-way comparisons;
- independent external security/code review.

Operation history, inherited host-impact presentation and batch JavaScript extraction are implemented. Write-path safety and real field evidence still take precedence over additional UI automation.
