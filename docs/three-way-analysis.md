# Three-way change analysis

## Purpose

Three-way analysis determines how local template customization relates to official upstream evolution without modifying Zabbix configuration.

It is available only when the module has resolved an authoritative historical official baseline for the installed template version.

## States

The model uses three states:

```text
BASE     = official historical template matching the installed vendor.version
LOCAL    = template currently installed in Zabbix
UPSTREAM = current official template for the same release line and UUID
```

The analysis does not infer BASE from a version string. BASE has already been proven through canonical Git history, immutable commit retrieval and stable template UUID matching.

## Why two native import comparisons are enough

The module does not build a second Zabbix import engine.

Instead, it uses two `configuration.importcompare` operations against the same installed LOCAL state:

```text
historical preview: LOCAL (before) -> BASE (after)
current preview:    LOCAL (before) -> UPSTREAM (after)
```

Zabbix therefore performs the import parsing, conversion, normalization and structured-entity matching for both sides. The module only correlates the normalized `before` and `after` snapshots returned by Zabbix.

For every structured entity, UUID is the preferred identity. When the native comparison lacks a UUID, the extractor uses entity-specific uniqueness fields compatible with the object type. If identity or the shared LOCAL pivot cannot be established consistently, the result is `unresolved` rather than guessed.

## Field classification

For each normalized field or entity-existence state, the analyzer evaluates BASE, LOCAL and UPSTREAM.

### Upstream only

```text
BASE == LOCAL
UPSTREAM != BASE
```

The installed value was not customized locally. The change comes from current official upstream.

### Local-only overwrite risk

```text
LOCAL != BASE
UPSTREAM == BASE
```

The local installation was customized, while current upstream still carries the historical/base value. A normal import of the official current template would tend to replace that local customization.

This is not called a merge conflict because upstream did not independently change the field. It is nevertheless an explicit review/overwrite risk.

### Converged

```text
LOCAL != BASE
UPSTREAM != BASE
LOCAL == UPSTREAM
```

Local customization and official upstream evolution arrived at the same final normalized value. This is overlap, but not a conflict.

### Conflict

```text
LOCAL != BASE
UPSTREAM != BASE
LOCAL != UPSTREAM
```

Both local and upstream changed the same normalized field from BASE and produced different final values.

This is the module's strict conflict condition.

### Unresolved

The module uses `unresolved` when it cannot prove a stable entity identity or when the LOCAL snapshots returned by the two native comparisons are inconsistent.

No automatic conclusion is made in that case.

## Entity additions and removals

Existence itself is analyzed as a three-way state. This covers cases such as:

- a locally removed official item that current upstream still contains;
- a locally added entity that current upstream would remove;
- an entity independently added by local and upstream states;
- local/upstream deletion convergence.

When an entity is absent in at least one of BASE, LOCAL or UPSTREAM, existence/snapshot classification is used instead of pretending that individual missing fields can be compared safely.

## Native comparison structure

Zabbix `CConfigurationImportcompare` compares structured entities by UUID first and then by object-type uniqueness fields. Updated entities expose normalized `before` and `after` snapshots while nested structured entities are represented recursively.

The module's `ImportCompareEntityExtractor` flattens that tree into stable hierarchical paths, for example:

```text
/templates:uuid-<template UUID>/items:uuid-<item UUID>
```

`ThreeWayChangeAnalyzer` then combines the historical and current extracted maps using LOCAL as the shared pivot.

## Output

The analyzer produces summary counters for:

- upstream-only changes;
- local-only overwrite risks;
- converged changes;
- conflicts;
- unresolved changes;
- total normalized changes;
- number of affected entities.

The native comparison view displays detailed rows with:

- entity type;
- entity label;
- field;
- classification;
- BASE value;
- LOCAL value;
- UPSTREAM value.

Detailed output is bounded to avoid rendering an excessive page. Summary counts still include all analyzed changes.

## Safety boundary

Three-way analysis is read-only.

It does not:

- call `configuration.import`;
- modify a template;
- automatically merge values;
- declare an update operationally safe;
- resolve a conflict automatically.

A conflict or overwrite-risk result is evidence for administrator review. Operational risk classification, affected-host impact and controlled update workflows remain separate stages.

## Current limitations

- Nested non-structured arrays such as macro/tag/preprocessing collections can appear as one normalized field value when Zabbix returns them inside an entity snapshot. More granular semantic decomposition can be added later without changing the three-way model.
- Historical raw retrieval currently uses the validated current source path. History follows renames, but an old raw path rename can still make historical retrieval fail closed.
- No automatic risk score is assigned yet.
- No update action is enabled yet.
