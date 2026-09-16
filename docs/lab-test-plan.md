# Laboratory test plan — 0.1.0-beta.4

## Release state

This is a laboratory test release, not a production recommendation.

Status at publication:

- implementation: ready for end-to-end laboratory validation;
- automation validation: must be green on the release snapshot commit;
- field validation: in progress;
- target Zabbix generations: 7.x and 8.x.

The fixed test snapshot branch is `release/0.1.0-beta.4`. A formal Git tag/GitHub release remains a later publication step.

Beta.4 builds on the successful Zabbix 7.0.30 beta.3 inventory/upstream pass and adds bounded multi-template preparation plus controlled sequential execution. The batch path must never bypass the existing per-template analysis, rollback, fresh preflight, immutable source/hash validation, explicit confirmation and single `configuration.import` boundary.

## Safety assumptions

Use a disposable or otherwise non-production Zabbix environment.

For the first write-path pass, select only a small number (2–3) of official templates with updates available. Prefer templates with no detected local modifications, complete historical/three-way analysis and `none`/`low` technical risk.

Do not begin with a business-critical template or host. Medium/high-risk, conflict, local-overwrite and unresolved templates must not enter the automatic batch execution set.

## 1. Confirm frontend module and backup directory

Locate the actual modules directory:

```bash
find /usr/share/zabbix /usr/local/share/zabbix /var/www \
  -type d -name modules 2>/dev/null
```

Identify the PHP/web runtime user:

```bash
ps -eo user,group,comm,args | egrep 'php-fpm|apache2|httpd' | grep -v grep
```

Create persistent private backup storage only after confirming that account:

```bash
sudo install -d -o www-data -g www-data -m 0700 \
  /var/lib/zabbix-template-update-manager/backups

sudo stat -c '%U %G %a %n' \
  /var/lib/zabbix-template-update-manager/backups
```

Expected artifact permissions: template directories `0700`, YAML/JSON files `0600`.

## 2. Install the exact beta snapshot

```bash
git clone https://github.com/kmansur/zabbix-template-update-manager.git
cd zabbix-template-update-manager
git fetch origin release/0.1.0-beta.4
git checkout -B release/0.1.0-beta.4 origin/release/0.1.0-beta.4
cat VERSION
git rev-parse HEAD
```

Expected project version:

```text
0.1.0-beta.4
```

Record the exact commit SHA. Install the complete module directory below the Zabbix frontend `modules` directory, then run:

```text
Administration → General → Modules → Scan directory
```

Confirm `0.1.0-beta.4`, enable the module and open:

```text
Data collection → Template updates
```

## 3. Inventory/upstream regression smoke test

Record:

- module version;
- detected Zabbix version;
- visible templates;
- official UUID matches;
- current/update counts;
- repository/cache state;
- upstream ref and commit.

For the current Zabbix 7.0.30 laboratory baseline, the previous pass observed:

```text
Visible templates:       303
Official UUID match:     298
Repository unavailable:    0
Current:                    8
Updates available:        290
```

The exact update count may change when the official upstream index moves, but repository-unavailable should remain zero in a healthy run and UUID matching should remain plausible.

If upstream loading fails, capture the complete **Upstream diagnostics** table and stop before write-path tests.

## 4. Native checkbox/subset selection

Confirm:

- row checkboxes appear only for `Official UUID match` + `Update available` rows;
- current/unresolved/custom rows are not selectable through the normal update selection workflow;
- the header checkbox uses native Zabbix selection behavior;
- select only 2–3 candidates for the first test;
- **Review selected updates** shows only those explicitly selected templates.

Beta.4 intentionally bounds one selected batch to **25 templates**. Larger batch submissions must fail closed rather than silently truncate.

## 5. Selected review → batch preparation

On the selected review page confirm the chosen IDs/names/versions are correct.

As Super Admin click:

```text
Prepare selected updates
```

Expected behavior:

1. each selected template runs the full authoritative update analysis;
2. historical baseline and BASE/LOCAL/UPSTREAM analysis are reused;
3. conflict/local-overwrite/unresolved states remain blocked;
4. medium/high risk becomes Manual review;
5. low/none-risk candidates that reached `candidate_for_backup` receive/refresh a persistent rollback artifact;
6. analysis is rerun after backup creation;
7. `backup_verified` candidates run fresh preflight;
8. only a valid fresh preflight evidence SHA-256 can classify the template as `Ready`;
9. no `configuration.import` occurs during preparation.

The page must summarize:

```text
Selected
Ready
Manual review
Conflict
Blocked
```

## 6. Batch classification sanity checks

Before any write, inspect at least one item from each category that naturally occurs:

- **Ready**: eligible for controlled sequential execution;
- **Manual review**: medium/high technical review state;
- **Conflict / local overwrite**: must never be automatically executed;
- **Blocked**: incomplete/unresolved/not-applicable evidence.

If every selected item becomes blocked unexpectedly, stop and inspect the individual **Review update** page for one template before changing code or filesystem data.

## 7. Controlled sequential batch update

Proceed only when the batch preparation page has one or more `Ready` items.

Check the explicit confirmation:

```text
I reviewed the batch plan and want to update the N Ready template(s) sequentially.
```

Then submit **Update ready templates**.

For each Ready template, immediately before its own write, the module must:

1. rerun fresh authoritative preflight;
2. compare the fresh evidence fingerprint with the reviewed evidence;
3. reject stale/changed evidence before import;
4. rebuild the immutable upstream candidate;
5. validate exact commit/path/content SHA-256 and template identity;
6. use the single approved `TemplateConfigurationImportService` boundary;
7. run fresh post-update validation.

A successful template must return `updated` before the next template begins.

## 8. Stop-on-first-failure semantics

The batch executor must stop immediately if a template returns any non-`updated` state or throws an exception.

Expected report groups:

```text
Updated successfully
Execution stopped / Failed
Not attempted
```

Templates after the stopping template must not receive `configuration.import` calls.

If the failed template reports `write_performed = yes`, do not retry the batch. Inspect that template and its stored rollback artifacts first.

Automatic rollback must **not** occur.

## 9. Individual comparison and preflight regression

For one selected template, also exercise the individual path:

```text
Review update
  → current-upstream import preview
  → historical baseline
  → BASE / LOCAL / UPSTREAM
  → risk/readiness
  → rollback verification
  → controlled preflight
```

Confirm batch support did not remove or weaken the existing individual workflow.

## 10. Backup/history verification

For every template that reached Ready, verify its rollback history contains an intact pre-update artifact.

Expected files:

```text
backup-<UTC timestamp>-<hash prefix>.yaml
backup-<UTC timestamp>-<hash prefix>.json
```

Reloading individual comparison should be able to prove that the newest artifact matched the fresh installed-template export at the time preparation occurred.

## 11. Rollback validation

After at least one successful controlled update, use **Rollback backups** for that template.

Expected rollback review:

- artifact integrity revalidated;
- current template freshly exported;
- template ID/UUID identity checked;
- `configuration.importcompare` previews restore;
- deterministic rollback preflight generated;
- no write on review page.

On explicit rollback confirmation, a new recovery backup must be created/verified before the older artifact is imported.

Expected success:

```text
rolled_back
write_performed = yes
post-rollback validation = passed
remaining differences = 0
```

Rollback remains an explicit per-template operation; beta.4 does not provide automatic batch rollback.

## 12. Permission/CSRF negative checks

Confirm:

- normal Admin can inspect supported read-only pages but cannot run batch preparation/execution;
- only Super Admin can prepare and execute selected updates;
- leaving batch confirmation unchecked is rejected;
- direct POST without the valid action-specific CSRF token is rejected by native Zabbix handling;
- tampering a reviewed evidence fingerprint prevents that template from being written;
- submitting more than 25 template IDs fails closed;
- conflict/manual-review/blocked items are absent from the hidden Ready execution set.

## 13. Filesystem tamper test (optional, disposable lab only)

If a stored backup YAML or JSON manifest is altered, the artifact must become invalid and must not be accepted as verified rollback evidence.

Do not perform this on a production backup store.

## 14. Evidence to capture

Record for each Zabbix generation:

```text
Zabbix version:
PHP version:
Frontend/web runtime user:
Module version:
Module commit/snapshot branch:
Visible templates:
Official UUID matches:
Updates available:
Selected template IDs/count:
Batch summary Ready/Review/Conflict/Blocked:
Ready template IDs:
Batch result status:
Updated template IDs:
Failed template ID/status/write_performed:
Not-attempted template IDs:
Requested upstream index:
Upstream ref/commit/cache state:
Individual test template name/UUID:
Installed version before:
Available version:
Readiness state:
Update preflight fingerprint:
Installed version after update:
Rollback target manifest:
Rollback result:
Installed version after rollback:
Backup directory permissions:
Any frontend/PHP errors:
```

Screenshots are useful, but retain logs/CLI evidence for failures.

## 15. Stop conditions

Stop all further writes if any occurs:

- PHP fatal/uncaught exception around update or rollback;
- batch continues after the first failed/ambiguous template;
- an operation reports `write_performed = true` but final validation is not proven;
- UUID changes unexpectedly;
- duplicate template objects appear;
- backup integrity fails unexpectedly;
- recovery backup does not match the current export;
- a conflict/manual-review/unresolved item is offered as Ready;
- selected review contains IDs that were not selected;
- frontend logs indicate an ambiguous `configuration.import` result.

In a write-performed-but-unvalidated state, inspect the current Zabbix template manually before choosing the next operation.

## 16. Exit criteria for beta.4 laboratory validation

A Zabbix generation passes beta.4 only after evidence demonstrates:

```text
module discovery/enable
inventory
upstream UUID/version matching
native subset selection
selected review
batch safety preparation
Ready/Review/Conflict/Blocked classification
persistent rollback creation + verification
controlled sequential update
stop-on-first-failure behavior (real or safely simulated)
post-update validation
individual comparison/preflight regression
rollback review
recovery backup
controlled rollback + post-validation
permission/CSRF negative checks
no unexpected PHP/frontend errors
```

Run Zabbix 7.x first. Repeat on Zabbix 8.x after the 7.x path is understood. A green GitHub CI run is necessary but does not replace runtime field validation.
