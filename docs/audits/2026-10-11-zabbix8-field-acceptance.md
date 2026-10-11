# Zabbix 8 laboratory field evidence — 2026-10-11

This record captures operator-supplied evidence from the disposable Debian 13
laboratory VM. It does not authorize production use, release publication or
closure of the offline and Zabbix 7 acceptance gates.

## Environment evidence

- Zabbix frontend/server: `8.0.0rc1`
- Operating system: Debian GNU/Linux 13 (trixie)
- Kernel: `6.12.111+deb13-amd64`
- PHP CLI: `8.4.26`
- Database: PostgreSQL
- ZTUM: `0.1.0-beta.63`, commit `268ece9`
- Runtime account: `www-data:www-data`
- Runtime and backup directories: mode `700`
- Ed25519 verification support: available through PHP Sodium
- Field collector privacy mode: safe metadata only

The read-only installer check, runtime check and laboratory diagnostic completed
without changing Zabbix configuration or data.

## Observed UI behavior

The frontend displayed 390 installed official templates and a fresh Zabbix 8.0
catalog. The following behaviors were exercised in the disposable laboratory:

1. **Never update policy:** marking a template changed the policy and catalog
   count; the review page reported the update as blocked until updates were
   explicitly allowed again.
2. **Reviewed update preparation:** the page classified selected templates as
   manual review when the historical baseline was unverified and displayed the
   possible local-overwrite warning before confirmation.
3. **Stale evidence guard:** one batch stopped after
   `blocked_evidence_changed`; the remaining template was not attempted. This
   demonstrates the fail-closed behavior when a preflight fingerprint changes.
4. **Successful controlled writes:** a subsequent reviewed batch updated two
   templates, with post-import validation reporting zero failures.
5. **Rollback:** the rollback review required explicit confirmation, created a
   recovery backup, restored the selected target version and reported zero
   remaining differences after validation.

The tested templates had no linked hosts. The screenshots and operator output
are the source evidence for these observations; no secrets, configuration
contents or database credentials were collected.

## Remaining acceptance gates

- Signed offline bundle installation and operation with outbound egress denied.
- Negative offline tests for forged signatures, altered sources and missing
  artifacts.
- Real Zabbix 7 field validation.
- Historical merge/rename corpus validation, concurrency and interruption
  drills.
- Independent security review, soak period and third-party acceptance.

**Status:** Zabbix 8 online update, safety guards and rollback are field
validated in one disposable laboratory. Mandatory offline, Zabbix 7 and
independent acceptance gates remain open.
