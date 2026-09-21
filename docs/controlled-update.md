# Controlled template update

## Scope

The initial write-enabled milestone updates only an installed **official Zabbix template** whose identity, historical state, current local state, update candidate and rollback prerequisite can all be proven by the existing analysis pipeline.

It is intentionally conservative. The presence of a newer vendor version alone is never sufficient.

## Eligibility

A template reaches the controlled update path only after all of these gates succeed:

1. authoritative upstream UUID match;
2. installed `vendor.version` is older than the official candidate;
3. current upstream source is resolved by immutable Git commit;
4. historical official baseline matching the installed vendor version is resolved;
5. `configuration.importcompare` provides a normalized LOCAL -> UPSTREAM preview;
6. LOCAL -> BASE comparison is available;
7. BASE / LOCAL / UPSTREAM three-way identities are fully resolved;
8. no three-way conflict exists;
9. a persistent rollback artifact has been created;
10. the newest rollback artifact exactly matches a fresh `configuration.export` of LOCAL;
11. fresh update preflight passes.

The standard path still requires no local-overwrite condition and `none`/`low` technical risk.

Beta.11 retains the separate **explicit reviewed path** introduced in beta.10 when all identities and baseline evidence are authoritative and no three-way conflict exists, but a known LOCAL-only difference would be overwritten/removed and/or technical risk is `medium`/`high`. The reviewed reasons are bound into the preflight evidence and require a second explicit super-administrator checkbox immediately before import. This path is never promoted to unattended batch `Ready`.

## Permission and request boundary

The configuration-write action is:

```text
ztum.template.update
```

Requirements:

- HTTP POST;
- native Zabbix CSRF validation enabled;
- `USER_TYPE_SUPER_ADMIN` only;
- numeric `templateid`;
- explicit confirmation checkbox;
- for reviewed override mode, a second explicit acknowledgement checkbox accepting the bound local-overwrite/technical-risk reasons;
- evidence SHA-256 from the immediately preceding reviewed preflight page.

The frontend controller does not call the Zabbix import API directly. It delegates to `TemplateControlledUpdateService`.

## TOCTOU protection

The evidence fingerprint shown on the preflight page is not trusted by itself.

After the administrator confirms, `TemplateControlledUpdateService` reruns `TemplateUpdatePreflightService` from the numeric template ID. The freshly recomputed evidence SHA-256 must exactly match the fingerprint posted from the reviewed page.

If any bound state changed, the result is:

```text
blocked_evidence_changed
write_performed = false
```

No import is attempted.

Bound evidence includes:

- template ID and UUID;
- installed and available versions;
- exact upstream commit and YAML path;
- path-specific SHA-256 of the exact raw upstream YAML bytes;
- canonical per-template content SHA-256 from the validated index;
- upstream visible/technical names and vendor name;
- verified rollback SHA-256;
- fresh current-export SHA-256;
- direct linked-host count;
- whether reviewed override mode is active;
- the exact reviewed override reasons.

## Candidate reconstruction

After fresh preflight passes, the candidate is rebuilt server-side. No upstream source content is accepted from the browser.

`TemplateUpdateCandidateService`:

1. accepts only the freshly recomputed preflight result;
2. re-fetches the exact official `templates/.../*.yaml` path at the exact 40-character commit;
3. computes SHA-256 over the exact returned raw YAML bytes;
4. requires an exact match with the path-specific raw source fingerprint recorded by the validated upstream index;
5. preserves the separate canonical per-template content fingerprint bound by preflight;
6. parses the source with Zabbix's native YAML reader;
7. validates UUID, visible/technical names, vendor name and vendor version;
8. isolates only the selected template and required group definitions;
9. emits the same minimal JSON import source used by the comparison pipeline;
10. fingerprints the isolated import source for result/audit output.

Any mismatch fails before the write boundary.

## Single configuration-write boundary

The only approved Zabbix configuration write call is in:

```text
src/Service/TemplateConfigurationImportService.php
```

That file contains exactly one:

```text
API::Configuration()->import(...)
```

The import reuses `TemplateImportCompareService::rules()`, keeping the rule profile aligned with the reviewed `configuration.importcompare` preview.

The CI controlled-write guard fails if another Zabbix API write call or direct database write appears elsewhere.

## Post-import validation

A successful return from `configuration.import` is not treated as the final success condition.

`TemplatePostUpdateValidationService` rebuilds the authoritative analysis and requires:

- same template ID;
- same template UUID;
- authoritative official upstream identity;
- installed vendor version equals the candidate version;
- version state is `current`;
- content state is `matches_current_upstream`;
- `configuration.importcompare` reports zero remaining changes.

Only then does the workflow return:

```text
status = updated
write_performed = true
```

If import returned successfully but validation fails or raises an exception, the workflow returns `validation_failed` with `write_performed = true`. It does not retry automatically.

## Ambiguous import failures

An exception from `configuration.import` can occur before, during or after the underlying import transaction boundary. The controller therefore does not claim that no write occurred when the importer itself throws.

The UI instructs the administrator to inspect the current template state before taking further action. Automatic retry is forbidden.

## Rollback artifact retention

The verified rollback artifact used as an update prerequisite is retained after the update. It is not deleted or replaced automatically.

Rollback is a separate explicitly confirmed super-administrator workflow. The rollback implementation must revalidate the artifact immediately before use and create a backup of the current post-update state before restoring an older artifact.

## Initial test strategy

The controlled update feature must first be tested on a disposable/lab Zabbix instance with an official outdated template that:

- has no local customization;
- reaches `none` or `low` technical risk;
- has a verified rollback backup;
- is linked to zero or a small known number of lab hosts.

The first test should capture before/after exports, screenshots, frontend/PHP logs and the resulting comparison state before considering any production use.
