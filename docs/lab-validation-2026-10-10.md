# Laboratory field validation — 2026-10-10 (Zabbix 8.0 RC1)

## Evidence and scope

Source: operator-supplied ZTUM batch completion screenshot, 2026-10-10.
This is a **single observed successful laboratory batch**, not certification of
all versions, environments, local-customization preservation, or rollback.

Environment shown in the preceding laboratory session: Zabbix 8.0 RC1; ZTUM
native frontend, laboratory beta line. All four targets showed **0 linked hosts**.

| Template | Installed before | Available | UI execution result |
| --- | --- | --- | --- |
| GitHub repository by HTTP | 8.0-3 | 8.0-5 | Updated and validated (8.0-5) |
| Microsoft Hyper-V Failover Cluster by SSH | 8.0-0 | 8.0-1 | Updated and validated (8.0-1) |
| Microsoft Hyper-V Standalone by SSH | 8.0-0 | 8.0-1 | Updated and validated (8.0-1) |
| VeloCloud SD-WAN Edge by HTTP | 8.0-0 | 8.0-1 | Updated and validated (8.0-1) |

The screenshot reports: selected **4**, completed preparation **4**, manual
review **4**, Ready **0**, conflict **0**, blocked **0**, execution **Completed**,
updated **4**, failed **0**, not attempted **0**, configuration write **Yes**.

Every item was classified *review backup verified*, with *unverified historical
baseline* and an explicit potential overwrite/loss warning. Operator confirmation
was selected, and the UI reported each import as validated.

### Claims established

- The per-template reviewed batch execution path completed four imports according
  to the UI, including the application's post-import validation status.
- The native batch page presented the historical-baseline limitation and possible
  local-overwrite loss prominently.
- The action was not unattended: explicit selection and administrator confirmation
  were involved.

### Claims **not** established by this evidence

- Actual restoration of the rollback YAML/manifest in a disposable environment.
- That local user customizations were preserved (origin is unverified).
- Complete audit-history content and artifacts for these four individual writes.
- Host/item behavior, since the four templates had **no linked hosts**.
- Zabbix 7.x compatibility, production suitability, and all other template types.
- Failure handling under process interruption, stale sources, partial imports,
  network failure, or concurrent administrators.

## Next no-write validation

1. Review **Operation history**: check each template has an update record and
   distinguish imported/validated from blocked/no-write attempts.
2. Review **Rollback backup history**: verify pre-update vendor version,
   artifact integrity, and filename/manifest availability for each template.
3. Verify **Current** status and expected official UUID/vendor version in the
   main inventory, and compare content against the official upstream.
4. Capture sanitized evidence, excluding secrets from template macros and values.

## Controlled rollback drill (separate, disposable test)

1. Use a dedicated **disposable Zabbix lab** and an isolated test template with
   zero production-linked hosts. Do not perform this drill on monitored templates.
2. Record template ID, UUID, version and export SHA-256, then create and verify a
   persistent backup; separately preserve a known-good restore point.
3. Perform a controlled update only after reviewing the exact changes and
   explicit confirmation.
4. Open **Review rollback** for the known backup. Verify target identity,
   source backup integrity and required confirmation before import.
5. Restore and verify version, UUID, effective content and absence of unexpected
   configuration differences. Record any import/validation failure without
   automatic retries.
6. Confirm a fresh subsequent analysis detects the restored state. Only then
   repeat the drill on a Zabbix 7.x disposable environment.

## Negative checks before release

- Missing/invalid/stale backup must block confirmed update.
- Altered upstream source/identity or stale preflight fingerprint must block.
- Incomplete or truncated preview and unknown technical risk must block.
- Unknown baseline must never be promoted to unattended Ready.
- Explicit acknowledgement must reset when selected rows/evidence changes.
- Any uncertain write must stop the batch for reconciliation and never trigger
  automatic rollback or blind retry.

**Status:** batch update workflow field-validated in one Zabbix 8 RC1 laboratory
scenario; rollback, failure injection and Zabbix 7 runtime validation **pending**.
