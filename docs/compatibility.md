# Compatibility and field-validation status

Updated: 2026-10-10

The supported runtime policy is separate from **observed laboratory results**. A successful integration smoke or individual field operation does not establish full compatibility.

| Zabbix generation | Runtime policy | Observed field evidence | Still required |
| --- | --- | --- | --- |
| 7.x | Supported code path, pending full field matrix | Native catalog, pagination/filter, official identity and comparison; controlled update, installation, recovery backup/rollback and reviewed batch recorded on Zabbix 7.0.31 | Interruption and fault injection, security-negative cases, offline-only, serialization, installation/upgrade reproducibility |
| 8.x | Supported code path, pending full field matrix | Earlier Zabbix 8.0.0beta2/PHP 8.4.24 exposed a pager-symbol mismatch fixed in beta.53. On 2026-10-10 a Zabbix 8.0 RC1 laboratory UI reported four manually reviewed batch updates validated (zero linked hosts) | Controlled rollback/recovery backup, negative/failure paths, wider template and host-impact coverage, full installation and upgrade tests |

See [observed Zabbix 8 field evidence](lab-validation-2026-10-10.md). The snapshot does not prove local customization preservation or host/item behavior.

## Runtime behavior

The module reads `ZABBIX_VERSION` and fails closed for unsupported or unknown major generations. One package targets Zabbix 7.x and 8.x with native frontend conventions and compatibility checks.

PHP syntax/unit checks run on PHP 8.2, 8.3 and 8.4. The final supported combinations depend on the PHP versions supported by each target Zabbix frontend, and exact tested pairs must be recorded.

## Automated checks are not field certification

Repository automation includes native frontend-symbol verification for Zabbix 7 and 8, browser and accessibility smoke, controlled-write boundary/security guards and release package checks. Full-stack browser smoke is read-only; it does not replace real configuration-write/rollback testing.

## Minimum real-world matrix

- Clean module discovery/enabling and in-place upgrade
- Catalog, upstream access, native filtering, themes and pagination
- Official UUID matching and BASE / LOCAL / UPSTREAM comparison
- Standard and explicit reviewed updates, local-overwrite warnings
- Missing-template installation and bounded batch installation
- Persistent Never update policy and operation history
- Verified backups, recovery backup, explicit rollback and final consistency checks
- Batch first-failure stop, interruption and durable resume
- Offline-only bundles and global write serialization
- Admin access, CSRF, tampered backup/source and stale evidence rejection

## Known limitations

- Production use is not recommended.
- Missing historical BASE does not prove an absence of local changes; explicitly reviewed writes can still overwrite customizations.
- No automatic rollback after ambiguous writes.
- Multi-frontend deployments require a shared private lock location with reliable cross-node `flock()`.
- Host-impact completeness depends on the runtime API's ability to resolve the inheritance graph.

For promotion criteria see [production readiness](production-readiness.md) and [lab test plan](lab-test-plan.md).
