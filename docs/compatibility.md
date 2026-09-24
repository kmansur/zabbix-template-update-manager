# Compatibility

This matrix separates declared support from recorded field evidence. A supported code path is not automatically considered fully field-validated.

## Supported generations

| Zabbix generation | Runtime policy | Current field status | Notes |
|---|---|---|---|
| 7.x | Supported | Partially field-validated | Catalog, upstream identity, comparison, controlled update and multiple install/batch paths have real laboratory evidence. Current UI pass includes native pagination and Name + Status filtering. |
| 8.x | Supported | Partial / validation in progress | Real Zabbix 8.0.0beta2 + PHP 8.4.24 exposed the pager-constant incompatibility fixed in beta.53. Catalog loading and native pagination were then confirmed. Full write-path/rollback/offline matrix remains open. |

ZTUM detects the running frontend through `ZABBIX_VERSION` and fails closed for unsupported/unknown major generations.

## One package

ZTUM intentionally ships one source tree and one module package for Zabbix 7.x and 8.x. Native Zabbix APIs/helpers are preferred so version-specific frontend implementation details remain inside Zabbix wherever possible.

## Field-validation dimensions

The 1.0 readiness matrix records these dimensions independently:

- module discovery and enablement;
- catalog and upstream-index retrieval;
- Name + Status filtering and native pagination;
- read-only comparison and historical BASE resolution;
- three-way analysis and risk/readiness;
- rollback-backup creation and integrity verification;
- standard controlled update;
- reviewed update;
- local-overwrite acknowledgement;
- request-bounded batch update and stop-on-failure;
- controlled missing-template installation and batch installation;
- Never update / Allow updates policy;
- rollback review, recovery backup and controlled rollback;
- offline-only bundle mode;
- controlled-operation serialization;
- light/dark theme presentation;
- permission, CSRF, evidence-tamper and backup-tamper negative checks.

See [lab-test-plan.md](lab-test-plan.md) and the current GitHub 1.0 readiness issue for evidence capture.

## PHP

ZTUM runtime code is expected to work with PHP versions supported by the target Zabbix frontend. Repository CI executes syntax and unit tests on PHP 8.2, 8.3 and 8.4. It also checks ZTUM native frontend classes/constants against the Zabbix 7.0 source tree and the current Zabbix 8.0 source line. Real field evidence remains tied to the exact Zabbix/PHP pair recorded during validation.

## Automated compatibility evidence

The cross-Zabbix frontend-symbol gate was added after real Zabbix 8 testing exposed a renamed pager constant that repository-only tests had missed. Guarded optional constants are permitted, while unguarded native class/constant references must exist in both supported frontend source trees.

Chromium smoke tests exercise both the extracted update/install batch orchestration assets and disposable official Zabbix 7.x/8.x frontend stacks. The full-stack smoke registers the module through the Zabbix API, logs into the real frontend, renders the catalog in dark and light themes, applies a native Name filter, and opens operation history. It remains read-only and does not replace controlled-write field validation.

## Known limitations


- Full Zabbix 8.x write-path field validation is not yet complete.
- Disposable full-stack browser smoke proves module registration/rendering against official container images, but it intentionally does not execute configuration writes.
- Multi-node serialization requires `ZTUM_LOCK_DIR` to point to shared private storage with reliable cross-node `flock()` semantics.
- Operation impact distinguishes direct and inherited template-to-host reach only when the runtime API can resolve the inheritance graph authoritatively.
- Production recommendation remains gated by the documented release/field criteria, not by CI alone.
