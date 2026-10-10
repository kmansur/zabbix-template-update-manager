# Project status — community beta validation

Updated: 2026-10-10

This page separates implemented functionality, automated checks, operator-observed field results and outstanding release gates. It does not declare RC or production readiness.

## Current release

- Published laboratory prerelease: `v0.1.0-beta.61`.
- Proposed next community-testing milestone: `v0.2.0-beta.1` (**not yet tagged or published**).
- Main development branch: `main`. Generated official catalog indexes are published through `upstream-index`.
- Feature freeze: prioritize test evidence, defect fixes and documentation over new features.
- Production use: **not recommended**.

## 2026-10-10 PDCA — manual ZTUM release check

- **Plan:** Provide Super Admin-only manual visibility into published GitHub releases (including betas and RCs), without granting PHP filesystem write privileges or introducing auto-update.
- **Do:** Added a dedicated read-only route, an explicit header link, strict HTTPS GitHub API URL, timeout, TLS peer verification, no redirects, bounded JSON response, release tag/version comparison and GitHub-host allowlist for release links.
- **Check:** Added `ModuleReleaseCheckServiceTest.php` fixtures for newer beta, current version, stable version, drafts, external URL and empty releases. CI executes all `tests/unit/*Test.php` and PHP lint. Additional injected-transport negative tests cover HTTP 403/429/500, connection failure, malformed/oversized JSON and empty releases. **New tests and CI must be verified against the final commit.** The user has validated the normal UI case on Zabbix 7 and 8, and verified the new route denies Admin on Zabbix 8.
- **Act:** Keep operation manual and read-only. Validate the button with GitHub reachable, inaccessible and no releases; verify Super Admin-only access, PHP cURL and compatibility. Update production-readiness evidence only after field tests. No new release tag published.

## PDCA reassessment — 2026-10-10, commit a97aae7

### Plan
Keep ZTUM restricted to Super Admin on every registered action; maintain the manual, read-only release checker; verify safe update/rollback and release gates in both supported frontend generations. Continue the feature freeze.

### Do
Authorization was restricted across 24 registered routes. Legacy contract tests were updated to match the Super Admin-only policy. A read-only GitHub release checker and bounded negative-transport tests were added. Production implementation of template import, backup and rollback was not changed in the contract-fix cycle.

### Check — confirmed evidence
- On the Zabbix 8 test machine, **88/88 PHP unit/contract tests passed** at commit `a97aae7`.
- The operator confirmed all three main-branch GitHub Actions workflows green on the same commit: **CI** run `38076317676`, **Security** run `38076317614`, and **Quality Metrics** run `38076317646`.
- The release-checker normal path was observed in the Zabbix 7 and Zabbix 8 UI; its negative HTTP/transport cases passed via injected test transport, *not* by blocking the real GitHub service.
- Zabbix 8 Admin was denied navigation and direct access to the catalog/release checker; Super Admin remained functional. Static contracts cover all 24 actions; they are not equivalent to HTTP tests of every action.
- Controlled positive batch update and Acronis rollback/re-update were observed in Zabbix 8 with zero linked hosts. Preparation stop, persistent failure-blocking and isolated JavaScript failure cases passed.
- Missing/invalid CSRF requests were rejected. **The valid-CSRF-token + malformed input differential HTTP test remains outstanding**; no claim of full CSRF certification is made.

### Act — gate decisions
- **Automated regression gate: PASS for `a97aae7`.** Recheck after any subsequent code change.
- **Normal release-checker feature: accepted for lab use**, without auto-update or web-process code-writing privileges.
- **Broader laboratory field/safety gate: OPEN.** Priority checks: (1) valid-token CSRF differential; (2) interruption/uncertain-write recovery through HTTP; (3) tampered evidence/backup negatives; (4) offline serialization; (5) post-merge index publication; (6) clean install/upgrade and independent reproducibility on immutable artifacts.
- **Community beta `v0.2.0-beta.1`: HOLD until its documented candidate gates are met.**
- **RC / production: NOT APPROVED.** No release, tag or version change was made as part of this PDCA.

## 2026-10-10 PDCA — installer consolidation

- **Plan:** Keep one canonical and safe local-source installer for Zabbix 7.x/8.x with automatic frontend/PHP-FPM detection and minimal runtime file installation.
- **Do:** Retired `tools/quickinstall.sh` and `tools/quickinstall-pt-br.sh` on `main`. Kept the dedicated `tools/ztum-runtime-setup.sh` helper. Aligned the CI shell syntax check with root-level `install.sh`; revised the English and Brazilian Portuguese quick-install documents to reference it.
- **Check:** Operator previously validated clean installation of the earlier installer revision on Zabbix 8, including 0700 runtime directories and root-owned minimal module contents; verified `--check` and Zabbix 7/8 detection. **The consolidated final commit CI/security/quality workflows and clean install must be revalidated.**
- **Act:** The three-command `install.sh` workflow is the single installation documentation path. Existing installs are never overwritten; no `--upgrade` implementation yet. Immutable historical releases are not rewritten. Production remains unapproved.

## Engineering and evidence status

| Workstream | Status | Evidence and limits |
| --- | --- | --- |
| Declared core functionality | Implemented | Catalog, official identity, comparison, risk analysis, controlled install/update, policies, backups and rollback, batch orchestration |
| CI and release tooling | Implemented | PHP 8.2/8.3/8.4, cross-frontend checks, security contracts and packaging workflows; green results must be confirmed on the precise candidate commit |
| Zabbix 7 laboratory | Partially validated | Real catalog, native UI, policies, analysis, reviewed updates, installation, rollback/recovery backup and batch execution recorded; fault injection and negative-security matrix remain incomplete |
| Zabbix 8 laboratory | Partially validated | Four reviewed templates updated and validated, with zero linked hosts; later Acronis rollback to 8.0-1 and re-update to 8.0-2 validated with recovery backup. Batch preparation stopped safely in UI. Persisted fault and JS orchestration checks passed in isolation; HTTP interruption and host-linked safety remain open |
| Index publication | Pending post-merge verification | PR #114 was squash-merged to main on 2026-10-10 to tolerate history lookup HTTP 429; a fresh successful publication must still be verified |
| Public beta packaging | Pending | Do not change public install commands or claim a new tag until artifacts and checksums are published |
| RC / production | Not approved | Requires field and independent validation |

See [October 10 Zabbix 8 evidence](lab-validation-2026-10-10.md), [lab test plan](lab-test-plan.md) and [production readiness](production-readiness.md).

## PDCA priorities before the next community beta

### P1 — mandatory safety gates

1. **Partially satisfied:** Acronis Zabbix 8 rollback with recovery backup and re-update validated. Expand across different templates and verify linked-host/customization impact separately.
2. **Partially satisfied:** preparation stop observed, persistent state and JS stop-on-first-failure simulated. Complete HTTP failure/in-flight interruption, recovery of uncertain operations; never blindly retry an uncertain write.
3. **Partially satisfied:** Zabbix 8 Admin denied catalog/release-check URLs; all 24 actions pass static Super Admin contract. Missing/invalid CSRF rejected, but valid-token differential and further tampered-evidence/backup negative HTTP tests remain open.
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
