# Update review priority and impact

## Purpose

The update-risk stage is a conservative read-only review aid. It does not decide that an update is safe and it does not authorize or execute an import.

The stage combines three independent dimensions:

1. **Technical severity** — inferred from the concrete entities, operations and fields in the native Zabbix `configuration.importcompare` result.
2. **Three-way coverage** — whether BASE / LOCAL / UPSTREAM overlap could be established without unresolved identity or pivot state.
3. **Known impact breadth** — the exact number of hosts directly linked to the selected template.

These dimensions are intentionally kept separate. Host count does not inflate technical severity, and a low technical severity cannot hide missing three-way coverage.

## Input model

The current official update preview is normalized by `UpdatePreviewAnalyzer`:

```text
LOCAL installed template
        |
        | configuration.importcompare(current official source)
        v
native LOCAL -> UPSTREAM diff
        |
        v
UpdatePreviewAnalyzer
        |
        +-- added entities
        +-- removed entities
        +-- updated fields
        +-- unresolved identities
        +-- affected entity count
```

The analyzer reuses `ImportCompareEntityExtractor`, so UUID remains the preferred identity and known Zabbix uniqueness fields are fallback identities. It does not implement a second import parser.

## Technical severity

`UpdateRiskAnalyzer` assigns a technical review level from the proposed operation.

Initial policy:

- confirmed three-way conflict: `conflict`;
- local customization that current upstream would overwrite: at least `high`;
- unresolved normalized operation: `high` technically;
- removal of monitoring or alerting entities: `high`;
- item/item-prototype changes to key, type, value type, master item, SNMP OID, parameters or preprocessing: `high`;
- discovery-rule collection/filter changes: `high`;
- trigger/trigger-prototype expression, recovery-expression or dependency changes: `high`;
- host-prototype linkage/interface changes: `high`;
- additions of collection/alerting entities: normally `medium`;
- delay, timeout, history, trends, status, severity/priority, macros and tags: normally `medium`;
- clearly descriptive metadata such as name/description/vendor-only changes: `low`;
- unknown functional fields default to `medium`, never silently to cosmetic/low.

Visual entities are treated less aggressively than collection/alerting entities, but removals still require review.

## Overall review priority

Technical severity alone is insufficient for an update decision.

```text
if three-way analysis is unavailable:
    overall = unknown

else if conflict exists:
    overall = conflict

else if three-way state is unresolved:
    overall = unknown

else if a local customization would be overwritten:
    overall >= high

else:
    overall = technical severity
```

Therefore an apparently simple upstream change can still have an overall result of `unknown` when the project cannot prove local-overlap context safely.

## Impact breadth

The current impact value is:

```text
number of hosts directly linked to the template
```

No arbitrary thresholds such as "more than 50 hosts means high risk" are used.

The value is shown as operational context only. Indirect impact through nested template linkage or inheritance is not yet claimed and will require a separate impact graph before it can be presented as authoritative.

## UI

For an installed official template with an update available, the comparison page can show:

- overall review priority;
- technical severity;
- three-way coverage;
- directly linked host count;
- affected normalized entity count;
- total normalized changes;
- added entities;
- removed entities;
- updated fields;
- unresolved identities.

The existing three-way section remains the authoritative place for BASE / LOCAL / UPSTREAM conflict and overwrite details.

## Safety boundary

This stage is read-only.

It does not:

- call `configuration.import`;
- create/update/delete templates;
- merge conflicting values;
- change database records;
- expose an update button;
- label a template "safe to update".

A future write-enabled milestone must still require export/backup, explicit review, confirmation, controlled import, post-import validation and rollback.