# Production readiness

ZTUM tracks four independent states:

1. **Implementation readiness** — required functionality exists.
2. **Automation validation** — deterministic CI/security/quality gates pass.
3. **Field validation** — the exact build has been exercised on real supported Zabbix environments.
4. **Release readiness** — licensing, compatibility notes, release assets and final gates are complete.

A green workflow does not by itself make the module production-ready.

## Current posture

- Core implementation: advanced / near feature-complete.
- Automated validation: strong, with runtime smoke expansion in progress.
- Zabbix 7.x field evidence: substantial but the final consolidated 1.0 matrix is not closed.
- Zabbix 8.x field evidence: partial; full controlled write/rollback/offline validation remains open.
- License: AGPL-3.0-only.
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

- browser-level visual/accessibility regression;
- persistent operation-history UI;
- richer indirect host-impact presentation;
- extraction of behavior-heavy inline JavaScript into dedicated assets.

These should be completed before or during the RC cycle where practical, but write-path safety and field evidence take precedence.
