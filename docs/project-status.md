# Project status and engineering audit — 0.1.0-beta.39

Date: 2026-09-22

This document is an engineering snapshot, not a release certification. Percentages are planning estimates based on implemented scope, automated validation and remaining field/release work.

## Overall estimate toward stable 1.0 core scope

**84% complete**

The estimate deliberately separates implementation from automation and field validation. Provider expansion (GitHub/GitLab/community/private/custom repositories) is treated as post-1.0 scope and is not included in the 84% calculation.

| Area | Weight | Estimated completion | Notes |
|---|---:|---:|---|
| Discovery/catalog/upstream identity | 10% | 95% | Core official catalog/inventory/version path implemented |
| Comparison/risk/readiness | 20% | 92% | Three-way, risk, readiness, dependency-aware comparison implemented |
| Update/install/rollback write paths | 20% | 92% | Controlled writes, evidence, validation, reviewed overrides implemented |
| Native Zabbix UI/UX | 10% | 90% | beta.39 code refactor complete; cross-version visual field pass still required |
| Automated validation/CI | 10% | 92% | Strong PHP/contract/index/UI guards; browser/E2E/security scan still missing |
| Runtime resilience | 10% | 75% | Request-bounded flows work; repeated upstream/request timeouts remain |
| Field validation | 10% | 65% | Stronger Zabbix 7.x evidence than Zabbix 8.x; full matrix incomplete |
| Release engineering | 10% | 55% | License/security/release workflow/assets still incomplete |

Weighted result: **84%**.

## UI/UX audit

### Corrected in beta.39

- Removed dynamically created raw reviewed-update checkbox inputs.
- Reviewed row selection is server-rendered with native Zabbix `CCheckBox`.
- Page navigation moved into native `CHtmlPage::setControls()` where applicable.
- Batch scripts initialize through `CScriptTag::setOnDocumentReady()`.
- Semantic states use native Zabbix `ZBX_STYLE_*` classes.
- Catalog/module metadata uses native Zabbix list/table primitives.
- Removed raw text navigation separator from update result.
- Removed empty unused UI/action/asset stubs.
- Added automated native-UI guard to CI.
- No custom color palette or third-party UI framework is used.

### Still requiring field validation

- Light theme visual pass on supported Zabbix 7.x.
- Dark theme visual pass on supported Zabbix 7.x.
- Equivalent light/dark pass on supported Zabbix 8.x.
- Dense/wide comparison tables at common desktop widths.
- Large batch tables with long reason/diagnostic text.
- Keyboard/focus/accessibility behavior for batch selection and confirmation controls.

## Known bugs / engineering risks

### High priority

1. **Repeated request/upstream timeouts around 30 seconds**
   - Field runs have produced repeated HTTP 504/502 failures for AWS/Azure and other candidates.
   - Current request-bounded architecture prevents one large batch request from timing out, but one individual candidate can still hit the proxy/backend deadline.
   - Needed: timing instrumentation by stage, transport timeout review, caching/reuse of expensive upstream/historical work, and a controlled retry policy that never retries ambiguous writes.

2. **Zabbix 8.x field-validation gap**
   - Code targets major versions 7 and 8, but stable-release confidence requires the same real workflow matrix on Zabbix 8.x.

3. **Release/security gates incomplete**
   - No dedicated repository security-scan workflow.
   - No stable release workflow/assets/checksum pipeline.
   - Project license has not yet been selected.

### Medium priority

4. **Historical baseline edge cases**
   - Rename-aware historical raw-path resolution remains incomplete.
   - Some templates can still end in historical-baseline-unavailable states and need diagnostic/coverage work.

5. **Large batch JavaScript maintainability**
   - Update and installation batch behavior remains large inline JavaScript inside PHP views.
   - It is now native-lifecycle compliant, but moving behavior into dedicated frontend JS assets would improve maintenance and browser-level testing.

6. **No browser/E2E UI regression suite**
   - Current UI guard catches repository-level regressions, not real rendered DOM/theme/layout regressions.

7. **No persistent operation-history UI**
   - Result pages and rollback artifacts exist, but there is no dedicated auditable update/install/rollback history view.

8. **Impact analysis is direct-host only**
   - Inherited/indirect template impact is not yet modeled comprehensively.

### Lower priority / post-1.0

9. **Repository-provider expansion**
   - GitHub/GitLab/community/private/custom repository providers remain future scope.

10. **Advanced comparison UX**
    - Very large three-way detail sets still benefit from richer filter/drill-down behavior.

## Release blockers before stable 1.0

- Choose and publish project license.
- Add/green a suitable security-scan gate.
- Add stable release workflow with assets/checksums.
- Record full Zabbix 7.x and 8.x field-validation matrix.
- Validate fresh installation, standard update, reviewed update, local-overwrite acknowledgement, batch stop-on-failure and rollback on both supported generations.
- Resolve or explicitly document the repeated ~30 second timeout class.
- Complete compatibility matrix and release/homologation notes.
- Confirm no unresolved high-severity safety/data-loss issue.

## Completion interpretation

A green CI run means automated validation passed. It does not by itself make the release field-validated or production-ready.

The project should continue tracking three states independently:

- implementation ready;
- automation validated;
- field validated.
