# Critical-analysis remediation acceptance ledger

Source: independent document `ZTUM-analise-critica.docx` (review of beta.38). This checklist tracks **evidence**, not implementation percentages. Last updated: 2026-10-10. Changes in draft PR #115 are not released; a passing developer CI is not an approval for production.

| Original finding | Engineering state | Required evidence for closure |
| --- | --- | --- |
| HIGH: no open-source license | Addressed: AGPL-3.0-only LICENSE and repository metadata | Verify license in tagged archive and release page |
| HIGH: frontend requires outbound internet | PARTIAL: `OfflineBundleRepository` supports local bundle/index/source/history reads with SHA-256 checks, but its manifest is not authenticated by a released pinned signing key; complete offline operation needs controlled field proof | On both Zabbix generations, deny outbound egress and demonstrate read-only inventory, verified offline source import, safe update, backup and rollback; document allowed network policy |
| HIGH: architecture documentation stale | Updated architecture describes controlled writes | Independent audit of README/architecture/current actions against the exact release commit |
| MEDIUM: insufficient independent adoption | OPEN | Third-party tester provides repeatable reports and negative failure cases; repo popularity is not a substitute |
| MEDIUM: too-frequent prereleases | OPEN process gate | Freeze immutable RC, run defined soak period and publish regressions/change approvals; do not count rapid beta iterations as validation |
| MEDIUM: rename-aware historical baseline missing | OPEN | Real historical corpus including merges, renames, same vendor.version with differing YAML, multi-template YAML; no guessed BASE; fail-closed negative tests |
| MEDIUM: unofficial Bitbucket history dependency | OPEN | Verified signed and complete alternative history OR explicit offline supported path, with canonical/mirror disparity tests and bounded timeouts |
| MEDIUM: unreported formal test coverage | PARTIAL: comprehensive CI, PHP and browser smoke exist | Publish reproducible code/branch coverage report with scope and exclusions; independent field tests remain separate |
| MEDIUM: manual backup runtime setup | Addressed in draft installer and Zabbix 8 field check | Prove fresh install and upgrade, ownership/modes and safe failure on both Zabbix generations |
| LOW: lock/serialization visibility | Partly addressed: locks and runtime directory validated | Document and field-test simultaneous Super Admin write/rollback contention (both versions) |
| LOW: lack of formal GitHub Releases | Addressed: tagged checksummed beta artifacts | Reconcile current README/status/readiness against release API; verify immutable archive checksums |
| Security: exposure via undocumented upstream APIs / broad egress | OPEN risk | Independent threat review, SSRF and redirection/size/timeout negative tests, firewall-limited egress and offline behavior |
| Security: documentation may overstate maturity | Addressed in status docs: production not recommended | Clear UI notice plus third-party validation before lifting warning |

## Nonnegotiable release gates

- [ ] Exact final candidate CI, Security, Quality Metrics and release-artifact hashes pass
- [ ] Signed index key pinned to trusted released code; signature **and** immutable Git evidence verified
- [ ] Proven complete upstream historical coverage, including merge/rename and version ambiguity
- [ ] Real Zabbix 7 and 8 field acceptance: linked hosts, reviewed writes, offline, failure injection, rollback and concurrency
- [ ] Measured and published test coverage and independent security assessment
- [ ] Freeze/soak and independent tester acceptance recorded
- [ ] No unresolved high-severity or uncertain-write safety defect

## Implementation status vs evidence

The draft offline scripts (`build_historical_candidate_diagnostic.py`, `verify_signed_historical_diagnostic.py`, and ancestry auditors) intentionally emit **non-authoritative** diagnostics; neither signed provenance nor successful hashes imply an eligible update. Do not remove their `authoritative: false` contract merely to make an acceptance checkbox green.

**Production recommendation remains NO** until every mandatory gate above is verified. External test/soak evidence cannot be fulfilled solely by committing source code.

## Offline bundle authenticity caveat

`src/Repository/OfflineBundleRepository.php` validates entries with SHA-256 from its local manifest; this provides corruption detection **only if the manifest is already trusted**. Replacing both a bundle and its unsigned manifest can evade hash checks. Local source/history bundle verification must never be described as cryptographic publisher authentication. The offline historical Ed25519 tools currently require explicitly supplied trust material and are not wired into the ZTUM module. Final closure requires a signed manifest anchored by a public key shipped with a trusted module release, plus negative field tests with a forged manifest and restricted egress.
