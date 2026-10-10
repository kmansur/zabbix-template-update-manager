# Production readiness

Updated: 2026-10-10

**Current decision: not production-ready.** The published laboratory prerelease is `v0.1.0-beta.61`. `v0.2.0-beta.1` is a proposed community-testing milestone only; it has not been validated, tagged or published.

Readiness comprises four independent gates: implementation, automated verification, real field validation, and immutable release artifacts/documentation. No percentage for implementation can substitute for a controlled failure-recovery test.

## Current evidence

- Core functionality is implemented for the declared engineering scope. Controlled configuration imports share a single write boundary.
- Automated CI includes PHP 8.2/8.3/8.4, security checks, cross-Zabbix frontend compatibility, Chromium/full-stack read-only smoke, and packaging checks. Results need checking against each intended release commit.
- Zabbix 7.0.31 laboratory evidence includes controlled updates, installation, rollback/recovery backup and successful reviewed multi-template batch execution. Full fault-injection and security-negative testing remains open.
- Zabbix 8.0 RC1 laboratory UI reported four manually reviewed batch updates completed and validated on 2026-10-10, each with zero linked hosts. A subsequent controlled Acronis rollback (8.0-2 to 8.0-1) was validated, including recovery backup and post-rollback version/content checks; subsequent re-update to 8.0-2 succeeded. This does **not** establish safety for linked hosts, all templates, customization preservation, or production suitability. See [field record](lab-validation-2026-10-10.md).
- Batch preparation with three zero-linked-host candidates completed under manual review; a separate preparation was stopped after one of two candidates, without configuration write. Unit-level persisted stop-on-first-failure and isolated JavaScript orchestration fault-injection passed; full HTTP execution interruption / uncertain-state integration still pending.
- Super Admin-only access was confirmed with Zabbix 8 Admin user denied menu and direct URL; static contract checks all 24 actions. Absent and invalid CSRF POSTs were denied; a valid-token malformed-payload differential HTTP test remains pending.
- Manual ZTUM GitHub release checking returned current published beta in both Zabbix 7 and 8 lab UIs; Zabbix 8 Admin direct URL was denied. Unit tests validated version matching and simulated GitHub HTTP errors, timeout, malformed/oversized response, and empty releases. This feature performs no installation or download.
- PR #114 (upstream index publication resilience) was merged into main; a post-merge successful index publication remains to be confirmed.
- License: AGPL-3.0-only.

## Mandatory gates before RC / production recommendation

- All relevant CI/security/quality/package gates green on the exact immutable candidate.
- No unresolved high-severity security, configuration corruption, or data-loss defect.
- Full supported Zabbix 7.x and 8.x controlled-write matrix documented.
- Standard and reviewed update; local-overwrite acceptance; batch stop-on-first-failure.
- Controlled installation, failure diagnostics and uncertain-write stop handling.
- Verified backup, recovery backup, and a successful controlled rollback on both generations.
- Offline-only bundle and global serialization checks.
- Permission, CSRF, stale/tampered evidence, tampered-backup and integrity failure tests.
- Interrupted/resumed batch without unsafe duplicate import.
- Clean install and in-place upgrade from checksummed public artifacts.
- Native light/dark UI passes on both Zabbix generations.
- Independent reproducible field validation by another tester.
- Final security and release review.

## Release staging

1. Maintain the immutable `v0.1.0-beta.61` laboratory release and its checksum evidence.
2. Complete priority-1 field and security gates before promoting a new community candidate.
3. After passing gates, update VERSION, manifest, changelog and guides consistently; validate an immutable `v0.2.0-beta.1` archive before advertising it.
4. Publish a community-testing beta with explicit laboratory-only warning. RC and production approval remain separate decisions.

## Non-blocking improvements

- More interactive diff inspection for very large three-way comparisons.
- Broader third-party code and penetration review beyond the minimum independent evidence gate.

Refer to [project status](project-status.md), [compatibility](compatibility.md) and [lab test plan](lab-test-plan.md).
