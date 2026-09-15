# Update preflight

## Purpose

`TemplateUpdatePreflightService` is the final read-only gate before any future milestone is allowed to design a Zabbix configuration write.

Its purpose is deliberately narrow:

> Recompute the authoritative update analysis now, verify that rollback still matches the currently installed template, and bind the result to immutable candidate/rollback identity.

A passing preflight is **not** an update authorization and is **not** a reusable approval token.

Every result currently contains:

```text
write_enabled = false
```

## Why the preflight recomputes analysis

The comparison page may have been rendered seconds or minutes earlier. During that time:

- the installed template may have changed;
- the rollback artifact may have become stale or invalid;
- the upstream index may have advanced;
- historical/risk/readiness evidence may no longer match the current state.

A future update action must therefore never trust:

- a visible button;
- a hidden form field claiming readiness;
- an earlier `backup_verified` page state;
- a previously computed preflight fingerprint.

`TemplateUpdatePreflightService` calls the reusable `TemplateUpdateAnalysisService` again from the numeric template ID, so all authoritative evidence is rebuilt server-side from current inputs.

## Passing prerequisites

The current preflight passes only when all of the following are true:

1. the complete update analysis can be rebuilt successfully;
2. the selected template remains an authoritative official UUID match with an actual update available through the normal analysis pipeline;
3. update readiness is freshly `backup_verified`;
4. readiness itself still reports `write_enabled = false`;
5. the upstream source is bound to an exact 40-character immutable Git commit;
6. the selected source path is a validated `templates/.../*.yaml` path without traversal segments;
7. template UUID and available vendor version are valid/present;
8. rollback verification is freshly `current_match`;
9. the newest rollback SHA-256 exactly equals the fresh current `configuration.export` SHA-256;
10. stored and freshly exported byte counts match.

Anything else fails closed.

## Result states

### `blocked_analysis`

The complete analysis could not be established safely, or the selected template is not available in the normalized result.

### `blocked_readiness`

The freshly recomputed readiness state is anything other than `backup_verified`.

Examples include missing historical baseline, unresolved comparison identities, conflict, local overwrite risk, manual review state, or rollback backup still needing creation/verification.

### `blocked_candidate`

The immutable upstream candidate identity cannot be proven from exact commit + validated path + template UUID + upstream vendor version.

### `blocked_backup`

Rollback verification does not prove an exact current match, or the stored/current export fingerprints or byte counts disagree.

### `passed`

All current read-only prerequisites are proven.

Even in this state:

```text
write_enabled = false
next_step = await_write_enabled_milestone
```

No `configuration.import` operation is attached to the result.

## Evidence fingerprint

A passing result produces a deterministic SHA-256 over a canonical evidence structure containing:

- schema version;
- template ID;
- normalized template UUID;
- installed vendor version;
- available vendor version;
- exact upstream commit;
- exact upstream YAML path;
- verified rollback SHA-256;
- fresh current-export SHA-256;
- directly linked host count.

The fingerprint is useful for audit/review diagnostics: unchanged evidence yields the same fingerprint, while a meaningful bound input changes it.

It is **not** a bearer token, authorization secret or substitute for revalidation.

## Future write-enabled action

When the project eventually introduces `configuration.import`, the write controller must still perform a fresh server-side preflight immediately before the write and use only server-derived candidate data.

The intended boundary is:

```text
explicit administrator action
        |
        v
fresh TemplateUpdatePreflightService::run(templateid)
        |
        +-- anything except passed --> stop
        |
        v
passed + write_enabled still false in current milestone
        |
        v
future separately reviewed write gate
        |
        v
controlled configuration.import
        |
        v
post-import validation
        |
        v
rollback remains retained
```

The future write milestone must not merely flip the current `write_enabled` field to true. It requires a separate reviewed controller/service boundary, explicit confirmation semantics, candidate-source handling, post-write validation and rollback execution design.
