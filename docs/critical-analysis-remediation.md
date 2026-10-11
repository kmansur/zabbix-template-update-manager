# Critical-analysis remediation acceptance ledger

Source: independent document `ZTUM-analise-critica.docx` (review of beta.38). This checklist tracks **evidence**, not implementation percentages. Last updated: 2026-10-11. Changes in draft PR #115 are not released; a passing developer CI is not an approval for production.

| Original finding | Engineering state | Required evidence for closure |
| --- | --- | --- |
| HIGH: no open-source license | Addressed: AGPL-3.0-only LICENSE and repository metadata | Verify license in tagged archive and release page |
| HIGH: frontend requires outbound internet | PARTIAL: bundles now require an Ed25519-signed canonical manifest, trusted key ID/public key configuration, SHA-256 files and offline rename records; complete offline operation still needs a production key provisioning decision and controlled field proof | On both Zabbix generations, deny outbound egress and demonstrate read-only inventory, verified offline source import, safe update, backup and rollback; document allowed network policy |
| HIGH: architecture documentation stale | Updated architecture describes controlled writes | Independent audit of README/architecture/current actions against the exact release commit |
| MEDIUM: insufficient independent adoption | OPEN | Third-party tester provides repeatable reports and negative failure cases; repo popularity is not a substitute |
| MEDIUM: too-frequent prereleases | OPEN process gate | Freeze immutable RC, run defined soak period and publish regressions/change approvals; do not count rapid beta iterations as validation |
| MEDIUM: rename-aware historical baseline missing | PARTIAL: offline bundle generation now records exact Git rename transitions and fails closed on ambiguous mappings; authoritative real-history corpus and semantic baseline proof remain open | Real historical corpus including merges, renames, same vendor.version with differing YAML, multi-template YAML; no guessed BASE; fail-closed negative tests |
| MEDIUM: unofficial Bitbucket history dependency | OPEN | Verified signed and complete alternative history OR explicit offline supported path, with canonical/mirror disparity tests and bounded timeouts |
| MEDIUM: unreported formal test coverage | ADDRESSED IN AUTOMATION: `quality-metrics.yml` publishes Xdebug PHP line/file coverage summary as a CI artifact with enforced minima (65% file, 64% line); branch coverage and external tests are separate | Verify published artifact and coverage scope at immutable RC candidate; identify excluded code explicitly |
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

## Draft diagnostic validation follow-up (2026-10-10)

Regression coverage now distinguishes a forged truncation flag from a genuinely truncated Git ancestry window. Malformed revision entries, candidate fingerprints, NUL paths and backslash paths must return validation errors. These checks do not establish merge/rename completeness, publisher trust, or Zabbix field acceptance. The project-synced `sources/` directory was empty in the development session; the original DOCX could not be independently reread, so this follow-up uses this ledger and the repository as its available references.

Validation for this follow-up: 209 PHP files passed syntax checks; manifest, version and controlled-write guards passed; all 90 PHP unit scripts passed on Debian; Python discovery passed 10 historical regression tests and the upstream-index/metadata checks. Standalone offline-bundle, coverage-aggregation, frontend-compatibility-checker and workflow tests also passed. Native Windows execution is not equivalent: its filesystem permissions and text newline conversion caused existing PHP cache and Python fixture failures. No real Zabbix field acceptance is claimed.

## Offline bundle authenticity caveat

`src/Repository/OfflineBundleRepository.php` now requires an Ed25519 detached signature over the canonical manifest whenever a bundle is read. The trusted raw public key and key ID are supplied through installation configuration (`ZTUM_OFFLINE_BUNDLE_PUBLIC_KEY` and `ZTUM_OFFLINE_BUNDLE_KEY_ID`); the private signing key remains outside the repository. The generator refuses to create unsigned bundles, and runtime tests reject forged signatures. Final closure still requires provisioning the production key in a trusted released installation and negative field tests with a forged manifest under restricted egress.
