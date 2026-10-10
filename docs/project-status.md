# Project status — community beta validation

Updated: 2026-10-10

This page separates implemented functionality, automated checks, operator-observed field results and outstanding release gates. It does not declare RC or production readiness.

## Current release

- Published laboratory prerelease: `v0.1.0-beta.61`.
- Proposed next community-testing milestone: `v0.2.0-beta.1` (**not yet tagged or published**).
- Main development branch: `main`. Generated official catalog indexes are published through `upstream-index`.
- Feature freeze: prioritize test evidence, defect fixes and documentation over new features.
- Production use: **not recommended**.

## Engineering and evidence status

| Workstream | Status | Evidence and limits |
| --- | --- | --- |
| Declared core functionality | Implemented | Catalog, official identity, comparison, risk analysis, controlled install/update, policies, backups and rollback, batch orchestration |
| CI and release tooling | Implemented | PHP 8.2/8.3/8.4, cross-frontend checks, security contracts and packaging workflows; green results must be confirmed on the precise candidate commit |
| Zabbix 7 laboratory | Partially validated | Real catalog, native UI, policies, analysis, reviewed updates, installation, rollback/recovery backup and batch execution recorded; fault injection and negative-security matrix remain incomplete |
| Zabbix 8 laboratory | Partially validated | A 2026-10-10 Zabbix 8.0 RC1 screenshot recorded four reviewed templates updated and validated, with zero linked hosts; this does not prove rollback or interruption safety |
| Index publication | Pending post-merge verification | PR #114 was squash-merged to main on 2026-10-10 to tolerate history lookup HTTP 429; a fresh successful publication must still be verified |
| Public beta packaging | Pending | Do not change public install commands or claim a new tag until artifacts and checksums are published |
| RC / production | Not approved | Requires field and independent validation |

See [October 10 Zabbix 8 evidence](lab-validation-2026-10-10.md), [lab test plan](lab-test-plan.md) and [production readiness](production-readiness.md).

## PDCA priorities before the next community beta

### P1 — mandatory safety gates

1. Demonstrate a controlled backup, recovery backup and rollback on a disposable Zabbix 8 installation, including identity, version and content validation.
2. Exercise batch stop-on-first-failure and interrupted/resumed batch behavior. Never blindly retry an uncertain write.
3. Confirm negative access and data-integrity paths: privileges, CSRF, stale/tampered evidence, modified/missing backup, source identity and hashes.
4. Validate offline-only behavior, operation serialization and service/network failure diagnostics.
5. Confirm successful index publication after PR #114, without disabling hash or transport verification.

### P2 — reproducibility and compatibility

1. Complete the Zabbix 7.x/8.x field matrix, recording precise frontend, PHP, ZTUM version/commit and test results.
2. Verify a fresh install from a published, checksummed archive and an in-place upgrade in disposable environments.
3. Align README, installation instructions, changelog, compatibility and readiness documents with actual evidence.

### P3 — community publication

1. Choose and consistently apply the version only after P1 gates are approved; `v0.2.0-beta.1` is a proposal, not the current release.
2. Build and verify ZIP/TAR.GZ and SHA256SUMS from an immutable tag.
3. Invite independent laboratory testers with sanitized, reproducible feedback instructions.

## Release decision rule

A successful UI operation is evidence only for the scenario observed. It is not proof of all supported versions or configurations. Failed or ambiguous writes must be inspected, not automatically rolled back or retried.

Do not promote to RC or production until the required field gates and release review are independently recorded.

## References

- [Production readiness](production-readiness.md)
- [Compatibility](compatibility.md)
- [Laboratory test plan](lab-test-plan.md)
- [Zabbix 8 laboratory evidence (2026-10-10)](lab-validation-2026-10-10.md)
- [Engineering readiness handoff (2026-09-29)](audits/2026-09-29-engineering-readiness-handoff.md)
