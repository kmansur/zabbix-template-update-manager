# Controlled rollback

## Purpose

The rollback path restores one explicitly selected, previously stored template backup while preserving the current state as a new recovery artifact first.

Rollback is intentionally separate from automatic update validation. A failed update validation never triggers rollback automatically.

## Eligibility

Rollback is available only to Zabbix super administrators and only for artifacts that are still present in the bounded backup-history view and pass repository integrity checks.

A selectable artifact must retain a valid:

- numeric template ID;
- 32-character normalized template UUID;
- private manifest/YAML relationship;
- byte count;
- SHA-256 fingerprint;
- stored YAML source.

The current installed template UUID must match the selected artifact UUID exactly.

## Review phase

`ztum.template.rollback.review` is read-only with respect to Zabbix configuration.

It:

1. revalidates the selected artifact from the persistent backup repository;
2. exports the currently installed template with `configuration.export`;
3. verifies current template identity against the artifact;
4. runs `configuration.importcompare` using the stored YAML as the target;
5. displays the resulting additions, updates and removals;
6. binds current-state and target evidence into a deterministic SHA-256 fingerprint.

If the selected artifact already matches the installed template, no rollback control is offered.

## Explicit confirmation

The write action requires:

- HTTP POST;
- native Zabbix CSRF validation;
- super-administrator role;
- explicit confirmation checkbox;
- numeric template ID;
- exact manifest filename;
- rollback-preflight evidence fingerprint.

The posted fingerprint is not trusted by itself. It is compared with a fresh server-side preflight immediately before any configuration import.

## Recovery backup before restore

Before importing the older artifact, the module creates a new persistent backup of the current installed template.

The newly created recovery artifact must have the exact same byte count and SHA-256 as the current export that participated in the rollback preflight. If it differs, rollback stops before `configuration.import`.

This creates the following chain:

```text
current installed state
        |
        +--> fresh recovery backup
        |
        v
selected historical backup
        |
        v
configuration.import
        |
        v
post-rollback validation
```

The recovery artifact and selected target are both retained.

## Second preflight

After the recovery backup is persisted, the rollback preflight is executed again.

The operation continues only if:

- the selected artifact is still valid;
- the installed template identity is unchanged;
- the current export fingerprint is unchanged;
- the import preview is unchanged;
- the deterministic evidence fingerprint still matches the explicitly confirmed fingerprint.

This limits time-of-check/time-of-use drift between the review page and the actual import.

## Single configuration-write boundary

Rollback does not introduce a second Zabbix write implementation.

It reuses:

```text
src/Service/TemplateConfigurationImportService.php
```

That service remains the repository's only approved `API::Configuration()->import()` call site and uses the same import rule profile as `configuration.importcompare`.

## Post-rollback validation

After a successful import call, the module:

1. reloads the installed template;
2. verifies template ID and UUID;
3. verifies the stored target vendor version;
4. runs a fresh `configuration.importcompare` against the selected artifact;
5. requires zero remaining differences.

A valid result is reported as `rolled_back`.

If import succeeded but validation fails or cannot complete, the operation reports that a configuration write occurred and requires manual inspection. It never retries automatically.

## Fail-closed states

Before import, rollback can stop as:

- `blocked_preflight`;
- `blocked_evidence_changed`;
- `blocked_current_changed`.

After import, it can report:

- `rolled_back`;
- `validation_failed`;
- `validation_error`.

Any state that cannot prove a safe prerequisite stops before configuration import whenever the write has not already occurred.

## Operational restriction for the first test release

The backup-history UI intentionally exposes only the newest 50 artifact manifests. Artifacts older than that remain on disk but are not selectable through the frontend until a future pagination/archive design is implemented.

This is a deliberate bounded-scan safety limit, not an artifact-retention limit.
