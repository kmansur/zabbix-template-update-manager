# Official Zabbix 7 and 8 appliance acceptance matrix

Use **separate, disposable official appliances** for Zabbix 7 and Zabbix 8.
Do not assume that a laboratory RC behaves identically to a GA release.
Record the exact appliance build, Zabbix server/frontend versions, PHP
version, database type and ZTUM commit before beginning.

## Setup and non-destructive smoke

1. Snapshot each appliance before installing ZTUM; keep network access to the
   upstream official repository controlled and HTTPS-enabled.
2. Install the same ZTUM commit on both, enable the module and sign in with an
   authorized super administrator.
3. Run `sh tools/test-assisted-review.sh` on each appliance. This suite uses
   synthetic fixtures and must not import configuration.
4. Verify the dashboard, filters, native UI, light/dark themes and operation
   history on both versions. Confirm that names resolve but immutable historical
   subject identifiers remain available.
5. For each candidate, inspect official UUID/source fingerprints and the
   complete native diff. Unknown provenance must remain explicit.

## Update and reviewed batch

For one installed **disposable, unlinked** official template in each appliance:

1. Create an isolated baseline state and capture UUID, vendor version, and
   fresh export SHA-256.
2. Execute **Prepare and review update**; confirm backup is automatically
   created if needed and verified against the current export.
3. Confirm that preparation alone yields `write_performed=false` and that
   its operation-history record is `update_prepare`.
4. Confirm the page's administrator acknowledgement; import only on an
   explicit confirmation after inspecting the entire preview.
5. Verify the post-update vendor version, UUID, official content and
   operation-history `Updated` event.
6. Repeat in a reviewed batch with at least two **disposable** templates when
   the appliance provides eligible official updates. Confirm sequential
   execution, preflight per template, and stop on first failure. Never force
   updates merely to satisfy this test.

## Rollback

1. Identify the rollback artifact for the **same disposable template** and
   inspect its manifest/YAML integrity and target version.
2. Review rollback comparison without writing configuration.
3. Save an independent snapshot. Confirm the rollback explicitly.
4. Verify that the system creates a *recovery backup* for the state being
   replaced and matches its export hash before restoring.
5. Confirm `rolled_back`, old vendor version, same UUID, and expected export/
   compare results. A successful status alone does not prove behavioral
   recovery: inspect item/trigger definitions and any bound test hosts.
6. Verify that the recovery artifact can in turn restore the newer state,
   only if the lab snapshot and test plan make this safe.

## Required failures

- Missing, altered, corrupt or mismatched backup: no import.
- Unknown template UUID or stale current export: no import.
- Changed upstream source/evidence after review: no import.
- Incomplete preview/unknown technical impact: no unattended authorization.
- Selected reviewed rows changed: administrator confirmation resets.
- Unexpected interruption or uncertain post-import state: stop and reconcile;
  never automatically retry or silently roll back.

## Evidence collection

Capture the appliance metadata, relevant sanitized screenshots, operation
history, backup manifest identifiers/hashes and results for **each** major
operation. Do not publish template secrets, macro credentials or full private
backup files. Record failures and deviations in a version-specific note.

**Completion gate:** both version matrices pass the safe checks; one disposable
rollback drill succeeds per supported version; no outstanding critical safety
defects; release notes correctly state any unsupported variants.
