# Update preflight

## Purpose

`TemplateUpdatePreflightService` is the final non-write evidence gate before the controlled update action may ask a super administrator for explicit confirmation.

Its purpose is deliberately narrow:

> Recompute the authoritative update analysis now, verify that rollback still matches the currently installed template, and bind the result to immutable candidate/rollback identity.

A passing preflight is **not** an update authorization and is **not** a reusable approval token.

Every result still contains:

```text
write_enabled = false
```

The separate controlled update service must rerun preflight again immediately before `configuration.import`.

## Why the preflight recomputes analysis

The comparison page may have been rendered seconds or minutes earlier. During that time:

- the installed template may have changed;
- the rollback artifact may have become stale or invalid;
- the upstream index may have advanced;
- historical/risk/readiness evidence may no longer match the current state.

The update action must therefore never trust:

- a visible button;
- a hidden form field claiming readiness;
- an earlier `backup_verified` page state;
- a previously computed preflight fingerprint by itself.

`TemplateUpdatePreflightService` calls the reusable `TemplateUpdateAnalysisService` again from the numeric template ID, so all authoritative evidence is rebuilt server-side from current inputs.

## Passing prerequisites

The preflight passes only when all of the following are true:

1. the complete update analysis can be rebuilt successfully;
2. the selected template remains an authoritative official UUID match with an actual update available through the normal analysis pipeline;
3. update readiness is freshly `backup_verified`;
4. readiness itself still reports `write_enabled = false`;
5. the upstream source is bound to an exact 40-character immutable Git commit;
6. the selected source path is a validated `templates/.../*.yaml` path without traversal segments;
7. template UUID, visible/technical names and vendor identity/version are present and valid;
8. the selected source path has a validated 64-character SHA-256 for the exact raw YAML bytes;
9. the validated upstream index contains exactly one distinct canonical template-content SHA-256 for the candidate;
10. rollback verification is freshly `current_match`;
11. the newest rollback SHA-256 exactly equals the fresh current `configuration.export` SHA-256;
12. stored and freshly exported byte counts match.

Anything else fails closed.

## Result states

### `blocked_analysis`

The complete analysis could not be established safely, or the selected template identity does not match the requested template ID.

### `blocked_readiness`

The freshly recomputed readiness state is anything other than `backup_verified`.

Examples include missing historical baseline, unresolved comparison identities, conflict, local overwrite risk, manual review state, or rollback backup still needing creation/verification.

### `blocked_candidate`

The immutable upstream candidate identity cannot be proven from exact commit + validated path + path-specific raw source SHA-256 + one canonical template-content SHA-256 + template UUID + names + vendor identity/version.

### `blocked_backup`

Rollback verification does not prove an exact current match, or the stored/current export fingerprints or byte counts disagree.

### `passed`

All current preflight prerequisites are proven.

Even in this state:

```text
write_enabled = false
next_step = controlled_update_confirmation
```

Preflight itself never calls `configuration.import`.

## Evidence fingerprint

A passing result produces a deterministic SHA-256 over a canonical evidence structure containing:

- schema version;
- template ID;
- normalized template UUID;
- installed vendor version;
- available vendor version;
- exact upstream commit;
- exact upstream YAML path;
- validated raw upstream source SHA-256 for the exact path;
- canonical upstream template-content SHA-256;
- upstream visible/technical names;
- upstream vendor name;
- verified rollback SHA-256;
- fresh current-export SHA-256;
- directly linked host count.

The fingerprint is useful for review and TOCTOU protection: unchanged evidence yields the same fingerprint, while a meaningful bound input changes it.

It is **not** a bearer token or authorization secret. The controlled update action posts the reviewed fingerprint, but `TemplateControlledUpdateService` independently reruns preflight and requires an exact `hash_equals()` match before building or importing a candidate.

## Controlled write boundary

The current write path is deliberately separate from preflight:

```text
comparison / backup verification
        |
        v
fresh TemplateUpdatePreflightService::run(templateid)
        |
        +-- anything except passed --> stop
        |
        v
explicit super-administrator confirmation
        |
        v
TemplateControlledUpdateService
        |
        v
fresh TemplateUpdatePreflightService::run(templateid) again
        |
        +-- evidence changed / not passed --> stop, no write
        |
        v
re-fetch exact commit + path
        |
        v
verify raw source SHA-256 against the path-specific validated upstream index fingerprint
        |
        v
revalidate candidate identity + isolate one template
        |
        v
TemplateConfigurationImportService
        |
        v
single controlled configuration.import
        |
        v
fresh post-import analysis
        |
        v
exact current-upstream validation
```

The verified rollback artifact is retained after update. A failed or ambiguous import is never retried automatically.

See `docs/controlled-update.md` for the write-enabled portion of the workflow.
