# Update readiness gate

## Purpose

The readiness gate converts the read-only evidence already produced by the module into an explicit workflow state. Its job is to answer:

> Is there enough proven information to advance to the next review step?

It does **not** answer:

> Is this template safe to update automatically?

During the current milestone `write_enabled` is always `false`. The strongest positive state is only `candidate_for_backup`, meaning the analysis gate is complete enough to create and verify a rollback artifact before any future write-enabled workflow.

## Required evidence

For an official template with `Update available`, the gate considers:

1. current LOCAL -> UPSTREAM `configuration.importcompare` normalization;
2. historical official baseline matching the installed `vendor.version`;
3. BASE / LOCAL / UPSTREAM three-way analysis;
4. unresolved entity identities;
5. confirmed conflicts;
6. known local customizations that current upstream would overwrite;
7. conservative technical risk classification;
8. directly linked host count as context only.

The gate fails closed when any authoritative prerequisite is missing.

## States

### `blocked_baseline`

A matching historical official baseline has not been established. This includes an unavailable baseline, a complete `not_found` result or `history_limit_reached`.

The module cannot prove which differences are local customization versus normal upstream evolution, so the update path remains blocked.

### `blocked_unresolved`

At least one required analysis stage is missing or unresolved, including:

- update-preview entity identity;
- three-way identity/pivot state;
- incomplete risk coverage;
- unavailable risk analysis.

No optimistic fallback is used.

### `blocked_conflict`

At least one normalized field/entity has a confirmed BASE / LOCAL / UPSTREAM conflict. Conflict resolution is an explicit human-review task and is outside automatic update flow.

### `blocked_local_overwrite`

The installed template contains a known local customization for which current upstream still has the historical BASE value. A normal import would therefore tend to replace that customization.

The customization must first be preserved, reconciled or explicitly retired.

### `review_high`

Comparison coverage is complete and no conflict/local-overwrite blocker exists, but the technical change set is classified as high review priority.

Manual high-risk review is required before backup creation becomes the next workflow step.

### `review_medium`

Comparison coverage is complete and no blocker exists, but the change set still requires normal manual review.

### `candidate_for_backup`

All of the following are true:

- authoritative official UUID match;
- an actual update is available;
- current update preview is normalized without unresolved identities;
- historical official baseline is proven;
- three-way analysis is complete;
- no conflict exists;
- no known local customization would be overwritten;
- risk coverage is complete;
- overall technical review priority is `none` or `low`.

This means only that the next allowed workflow step is:

```text
create and verify rollback backup
```

It is deliberately **not** called `safe`, `approved`, `ready_to_import` or similar.

## Workflow

```text
official UUID match
        |
update available
        |
current update preview
        |
historical baseline
        |
three-way analysis
        |
risk + impact
        |
        v
UpdateReadinessEvaluator
        |
        +-- blocked_baseline
        +-- blocked_unresolved
        +-- blocked_conflict
        +-- blocked_local_overwrite
        +-- review_high
        +-- review_medium
        +-- candidate_for_backup
```

Even `candidate_for_backup` remains inside the read-only milestone. The backend backup foundation exists, but no backup/update button or `configuration.import` action is enabled yet.

## Host impact

The gate carries the exact count of hosts directly linked to the template so an administrator retains impact context. It does not use arbitrary host-count thresholds to promote or demote readiness.

Indirect/inherited impact remains a separate future analysis problem.

## Safety invariant

Every result includes:

```text
write_enabled = false
```

That invariant is intentional and should remain until the project explicitly enters a separately reviewed write-enabled milestone with backup verification, confirmation, controlled import, post-import validation and rollback.