# Security Policy

Template Update Manager executes inside the Zabbix frontend environment and can perform explicitly confirmed configuration imports. Security therefore remains a release gate rather than a documentation-only concern.

## Supported security posture

Current beta scope:

- Zabbix 7.x and 8.x;
- official Zabbix template sources only;
- explicit Super Admin confirmation for configuration-changing operations;
- one approved `configuration.import` boundary;
- no direct database writes;
- fail-closed identity, source, baseline, backup and evidence handling.

Production use is not yet recommended while field validation and licensing remain incomplete.

## Core invariants

- Discovery/comparison/preflight never mutate Zabbix configuration.
- Official identity is UUID-first; vendor metadata alone is not proof.
- Runtime repository URLs are fixed and input values are validated.
- TLS verification must remain enabled.
- Immutable source/path/content fingerprints are validated before a controlled write.
- Rollback backups are private, integrity checked and never treated as valid by filename/existence alone.
- Update/install/rollback write controllers acquire the global ZTUM operation lock before fresh authoritative preflight.
- Stale/changed evidence blocks writes.
- Ambiguous imports are never retried automatically.
- Rollback is explicit and never automatic.
- Air-gapped bundles are accepted only after manifest/hash verification; offline-only mode never falls back to network access.
- Credentials, tokens, private keys and customer-sensitive data must never be committed or included in public field reports.

## Automated security gates

CI includes:

- PHP syntax and contract tests;
- controlled-write-boundary guard;
- native-UI guard;
- deterministic runtime security guard;
- dependency audit for the pinned Python development dependency;
- workflow structure/action-pin validation;
- unit/regression tests for offline integrity and controlled-operation serialization.

Automated checks do not replace code review, penetration testing or real Zabbix field validation.

## Reporting a vulnerability

Please avoid opening a public issue for a vulnerability that could expose credentials, private template data or a configuration-write bypass.

Use GitHub's private vulnerability reporting/security-advisory mechanism when available for this repository. Include:

- affected ZTUM version/commit;
- exact Zabbix/PHP versions;
- whether a configuration write occurred;
- minimal reproduction;
- sanitized logs/evidence;
- impact and any known workaround.

Do not include production credentials or customer-identifying data.
