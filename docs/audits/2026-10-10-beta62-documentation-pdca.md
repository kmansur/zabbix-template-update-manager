# Beta.62 documentation and release consistency PDCA — 2026-10-10

## Scope
Review of published beta.62 state against current `main` documentation, source installer, release workflow, compatibility claims and operator-observed Zabbix 7/8 evidence. **The immutable `v0.1.0-beta.62` tag and release archives are not modified by this follow-up documentation audit.**

## Plan
Ensure public docs do not incorrectly label a released build as unpublished, separate normal installer behavior from explicit code-only upgrade/rollback, and keep unverified operational/security scenarios explicit.

## Do — corrected on development branch after beta.62 publication
- README: changed candidate/unpublished language to the published beta.62, updated earlier installed-version examples, pointed manual testing at the immutable release, and replaced full-source web module copy instructions with `install.sh`.
- Installer guide: clarified fresh-install default versus explicit `--upgrade` / `--rollback` and recorded real Zabbix 8 normal upgrade/rollback and checksum-negative evidence.
- Production readiness and project status: separated published beta.62 from production approval and from older beta.61 evidence.
- Laboratory metadata guard: accepts a published beta explicitly identified in project status instead of requiring inaccurate candidate-only language.

## Check — evidence and limitations
- Release tag `v0.1.0-beta.62`: commit `54fb6a3e994629801f62ddfcdc31c562ede70b69`; GitHub Actions Release `38082038614` completed successfully, including Zabbix 7/8 full frontend smoke, static checks and archive publication.
- Operator downloaded release ZIP/TAR.GZ and checked both against published `SHA256SUMS` (OK); exact digest strings should be copied from that release into a separate evidence record, not invented.
- Zabbix 8 lab: installed beta.62, rolled back module code to beta.61, upgraded back to beta.62; all three code-backup SHA-256 inventories passed. Two deliberate backup-copy integrity violations (modified file and extra file) were rejected before code replacement.
- Zabbix 7 lab: beta.62 catalogue, filters and operation history rendered; beta.61 code-backup SHA-256 passed. This does not establish a full Zabbix 7 code rollback.
- Changes in this audit are **post-tag documentation and test changes on main**. Re-run CI/Security/Quality Metrics on the new HEAD; do not interpret old green jobs as validation of this follow-up.

## Findings still open, prioritized
1. **High — installer activation recovery:** no disposable fault-injection E2E for failure of first/second rename, integrity check after activation, signal/kill/power loss, and manual-recovery fallback. Rollback only restores ZTUM code, not Zabbix configuration imports.
2. **High — authenticated mutation security:** valid-session CSRF positive-token/invalid-payload HTTP differential still needs negative validation; unresolved write-state/interrupt tests remain open. Do not promote to RC or production.
3. **Medium — backup trust boundary:** `SHA256SUMS` inventory verifies files, not empty directories or independent origin/signature. Backups are not externally signed, and the installer does not quiesce active web requests.
4. **Medium — source version pinning:** short three-command quick-start clones a moving `main` branch. For reproducible community trials use `git checkout v0.1.0-beta.62` and validate release artifacts against the published SHA-256 inventory.
5. **Medium — packaging/docs:** GitHub-generated release notes omit important installer/rollback changes; improve the public release description without modifying the immutable tag/archive. Historical `docs/lab-test-plan.md` intentionally remains beta.61; a separate beta.62 field plan is needed.
6. **Medium — compatibility:** only Debian 13 labs were field exercised, with Zabbix 7.0.31 and Zabbix 8.0. CI disposable frontend smoke is not proof of distro portability, SELinux correctness, PHP-FPM deployment variants or crash recovery.
7. **Low — operator workflow:** `install.sh --check` on an existing module only reports its presence, and should not be portrayed as full runtime/backup verification.

## Act
Keep `v0.1.0-beta.62` publicly labeled laboratory prerelease. Request final CI on post-release main changes, validate the installer in a disposable environment before altering the release process, refresh the GitHub release notes and publish a beta.62-specific lab guide. No silent mutation of tagged sources, Zabbix databases or existing lab backups.
