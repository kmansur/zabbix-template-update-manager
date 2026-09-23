# Roadmap

The project tracks implementation, automated validation and real field validation separately. A feature may be implemented and covered by tests without being field-validated on every supported Zabbix release.

## Implemented

### Discovery and official catalog

- Detect supported Zabbix 7.x / 8.x runtime line.
- Inventory installed templates through Zabbix APIs.
- Match official templates by UUID.
- Load validated official upstream indexes.
- Compare installed and available vendor versions.
- Browse not-installed official templates.
- Request-bounded multi-selection for updates and installations.

### Comparison and safety analysis

- Native `configuration.importcompare` preview.
- Historical official baseline lookup.
- BASE / LOCAL / UPSTREAM three-way analysis.
- Conflict, unresolved and local-overwrite classification.
- Technical-risk analysis.
- Direct linked-host impact count.
- Readiness gate.
- Persistent rollback backup creation and verification.
- Fresh preflight evidence bound to immutable upstream source identity/hashes.
- Dependency-aware cross-template reference handling.
- Diagnostic reporting for comparison/preparation failures.

### Controlled update

- Standard Ready path.
- Explicit reviewed override for medium/high technical risk.
- Explicit reviewed local-customization-overwrite acknowledgement.
- Request-bounded sequential batch execution.
- Stop on first non-success.
- Fresh preflight immediately before import.
- Immutable candidate reconstruction.
- Single approved `configuration.import` boundary.
- Post-update validation.
- No automatic retry or rollback after ambiguous write state.

### Controlled installation

- Official candidate identity validation.
- Collision detection.
- Template dependency analysis.
- Structural reference audit.
- Creation-only import preview gate.
- Request-bounded sequential batch installation.
- Fresh preflight immediately before import.
- Post-install validation.
- No recursive dependency installation and no automatic uninstall.

### Rollback

- Bounded rollback artifact history.
- Artifact integrity verification.
- Read-only rollback review/import comparison.
- Fresh recovery backup before restore.
- Explicit Super Admin confirmation.
- Single controlled import boundary.
- Post-rollback validation.

### Native frontend

- Native Zabbix page/table/filter/form/checkbox/action components.
- Native Zabbix status/style constants only.
- Light/dark theme inheritance.
- Native page controls.
- Request-bounded progress UI.
- Automated native-UI repository guard.
- Visible laboratory-use warning.

### Production-readiness infrastructure

- Verified air-gapped/offline upstream bundle mode.
- Fail-closed offline-only policy.
- Global serialized controlled-operation lock.
- Safe runtime-directory setup/check helper.
- Dedicated runtime security guard + dependency audit.
- Formal tag/release packaging workflow with checksums.
- Transparent Xdebug coverage/reachability metrics.
- Structured external bug/field-validation/feature-request/PR templates.
- Release policy separating development commits from field-test beta snapshots.

## In progress before 1.0

### Runtime resilience

- Investigate repeated per-template upstream/preparation HTTP timeouts (field examples near 30 seconds).
- Improve diagnostics and transport resilience without hiding uncertain states.
- Resolve remaining historical-baseline gaps, including rename-aware historical raw-path resolution.

### Field validation

- Complete regression matrix on supported Zabbix 7.x.
- Complete the same matrix on supported Zabbix 8.x.
- Validate light and dark themes on both supported major generations.
- Validate fresh installation, controlled upgrade, reviewed override and rollback on real lab instances.

### Maintainability

- Move the two large batch JavaScript bodies into dedicated frontend JS assets while preserving native lifecycle and server-authoritative security gates.
- Add browser-level UI/accessibility regression tests.
- Add persistent operation-history/audit presentation.
- Improve inherited/indirect host impact analysis.
- Add drill-down/filtering for very large three-way comparisons.

### Release engineering

- Select and publish a project license.
- Exercise the new release workflow with the first field-validated prerelease tag.
- Produce the final compatibility matrix.
- Finish publication/homologation documentation.
- Arrange independent security/code review before production recommendation.

## Post-1.0 / provider expansion

The current runtime is intentionally focused on the official Zabbix repository. Provider expansion remains a later milestone:

- GitHub repositories;
- GitLab repositories;
- community template repositories;
- private repositories;
- custom repository/provider configuration.

Provider support must not weaken immutable-source, identity, evidence or controlled-write guarantees.

## 1.0 definition

A stable 1.0 requires more than implemented code. At minimum:

- implementation complete for the declared scope;
- automated CI/validators/security gates green;
- field validation recorded on every declared Zabbix generation;
- fresh install/update/rollback regression green;
- release documentation/license complete;
- no unresolved high-severity safety or data-loss issue.
