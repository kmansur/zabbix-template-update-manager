# Pre-RC engineering audit — 0.1.0-beta.58

Date: 2026-09-24

This audit separates implemented code, automated evidence, real Zabbix field evidence and release/governance readiness. It is not a production certification.

## Executive status

**Estimated completion toward stable 1.0: 94%.**

The declared 1.0 core scope is functionally near complete. Remaining blockers are predominantly real-environment validation and release evidence, not missing core update/install/rollback functionality.

| Area | Weight | Completion | Evidence / remaining gap |
|---|---:|---:|---|
| Discovery/catalog/upstream identity | 10% | 97% | UUID-first official identity, validated immutable indexes, offline source mode and native catalog implemented |
| Comparison/risk/readiness | 20% | 98% | BASE/LOCAL/UPSTREAM, rename-aware history, risk/readiness, Never update policy and inherited host-impact analysis implemented |
| Update/install/rollback write paths | 20% | 98% | Single controlled import boundary, evidence, fresh preflight, serialization and post-write validation implemented |
| Native Zabbix UI/UX | 10% | 98% | Native filter/pager, dark/light inheritance, operation history and extracted JS assets implemented; full cross-version field UI pass remains |
| Automated validation/CI | 10% | 99% | PHP 8.2/8.3/8.4, Zabbix 7/8 native-symbol checks, Chromium asset smoke, accessibility/security/coverage/package gates |
| Runtime resilience | 10% | 96% | Bounded requests, caches, rename handling, offline mode, persistent lock default and private operation history |
| Field validation | 10% | 70% | Strong Zabbix 7 evidence and partial Zabbix 8 read-only/UI evidence; exact beta.58 write/rollback/offline matrix remains |
| Release engineering | 10% | 92% | AGPL license/notice, release workflow, checksums and release-package smoke implemented; first field-validated public prerelease remains |

Weighted planning result: **94%**.

## Completed pre-RC hardening

- Catalog access is restricted to Zabbix Administrators and Super Admins.
- Configuration-changing actions and update-policy mutation remain Super-Admin-only.
- The default global operation lock is private persistent storage under `/var/lib/zabbix-template-update-manager/locks`.
- AGPL-3.0-only license and attribution/notice are published.
- Non-official templates expose update policy as Not applicable rather than Managed.
- Direct and inherited/indirect unique host impact is analyzed through the visible template inheritance graph.
- Private bounded operation history is available to administrators and remains supplemental rather than authoritative evidence.
- Update/install batch JavaScript is delivered as registered module assets instead of large inline view bodies.
- PHP syntax/unit coverage runs on 8.2, 8.3 and 8.4.
- Native frontend symbols are checked against Zabbix 7.0 and the current Zabbix 8.0 source line.
- Chromium smoke tests exercise extracted update/install batch orchestration assets.
- Accessibility contracts protect navigation labels, native filter labels and confirmation-control labels.
- Release-shaped tar/zip archives, checksums and required file presence are tested in CI.
- Public repository scans found no known customer names/domains/IP ranges or credential/key patterns from the maintainer's operational environments.
- Legacy beta-specific field issues were superseded by the single 1.0 readiness matrix.

## Current real field evidence

### Zabbix 7.0.31

Confirmed on the laboratory frontend:

- module/catalog rendering;
- healthy official Zabbix 7.0 upstream catalog;
- dark-theme rendering of the catalog/filter;
- native Name + Status filter;
- partial case-insensitive Name search using `Adv`, returning both Advanced ICMP Ping templates.

Earlier 7.x field work also contains controlled-update/install evidence. The consolidated exact beta.58 write-path matrix is still intentionally open.

### Zabbix 8.0.0beta2 / PHP 8.4.24

Confirmed in the laboratory:

- module/catalog loading;
- the original removed-pager-constant failure was reproduced and diagnosed;
- the compatibility fix restored the catalog;
- native pagination replaced the custom pager wrapper.

The full beta.58 controlled update/install/rollback/offline matrix is not yet recorded.

## Remaining blockers before RC

1. Exercise the exact beta.58 build on supported real Zabbix 7.x and 8.x environments.
2. Record standard update, reviewed update, local-overwrite acknowledgement, batch stop-on-failure, installation/failure diagnostics, rollback/recovery backup, Never update, offline-only and serialization behavior.
3. Complete light/dark operator-flow evidence on both supported generations.
4. Install and validate a generated release artifact on a real Zabbix frontend.
5. Publish the first formal GitHub prerelease only after the relevant beta.58 field regression passes.
6. Confirm no unresolved high-severity safety/data-loss issue after the field pass.

## Non-blocking before/through RC

- Expand browser automation from isolated assets to disposable full-Zabbix page rendering when practical.
- Add drill-down/filtering for very large three-way comparison detail sets.
- Arrange an independent external security/code review before a production recommendation.

## RC decision

**Do not label the current build RC yet.** The implementation and automated gates are at pre-RC quality, but real Zabbix 8 write-path validation and the exact beta.58 cross-version field matrix remain release gates.

Once the real field matrix and generated-artifact installation pass, the expected next version is `1.0.0-rc.1`.
