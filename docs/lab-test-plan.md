# Laboratory test plan — 0.1.0-beta.3

## Release state

This is a laboratory test release, not a production recommendation.

Status at publication:

- implementation: ready for continued laboratory testing;
- automation validation: must be green on the release snapshot commit;
- field validation: in progress;
- target Zabbix generations: 7.x and 8.x.

The repository uses the fixed test snapshot branch `release/0.1.0-beta.3` for this laboratory build. A formal Git tag/GitHub release remains a later publication step; do not infer a tag from the version string.

Beta.3 exists because the beta.2 Zabbix 7.0.30 field pass reached the upstream decoder and exposed an official MikroTik path containing `+`. Beta.3 fixes that narrow validation mismatch and adds native Zabbix checkbox/select-all selection for choosing only the update candidates the administrator wants to review.

The first field-validation pass should be performed on Zabbix 7.x. Repeat the same functional path on Zabbix 8.x only after the Zabbix 7.x pass is understood.

## Safety assumptions

Use a disposable or otherwise non-production Zabbix environment.

Do not begin testing with a business-critical template or host. Prefer one official Zabbix template that:

- has an update available according to the module;
- has no detected local modifications;
- has a resolved historical baseline;
- has complete three-way analysis;
- has no conflict or local-overwrite risk;
- is classified `none` or `low` technical risk by the current gate.

The controlled update UI intentionally does not offer the automatic path for medium/high review states or unresolved/conflicting templates.

## 1. Confirm the frontend module directory

Do not assume the package-specific frontend path. Locate it first, for example:

```bash
find /usr/share/zabbix /usr/local/share/zabbix /var/www \
  -type d -name modules 2>/dev/null
```

The module must be installed as one directory directly below the Zabbix frontend `modules` directory and that directory must contain `manifest.json`.

## 2. Identify the PHP/web runtime user

The module stores rollback artifacts under:

```text
/var/lib/zabbix-template-update-manager/backups
```

Determine the actual frontend runtime account before creating the directory. On Debian/Ubuntu it is commonly `www-data`, but this must be verified locally.

Examples:

```bash
ps -eo user,group,comm,args | egrep 'php-fpm|apache2|httpd' | grep -v grep
```

Then create the persistent directory with private permissions, replacing `www-data:www-data` when necessary:

```bash
sudo install -d -o www-data -g www-data -m 0700 \
  /var/lib/zabbix-template-update-manager/backups

sudo stat -c '%U %G %a %n' \
  /var/lib/zabbix-template-update-manager/backups
```

Do not make this directory world-writable.

## 3. Install the exact beta snapshot

For a Git checkout:

```bash
git clone https://github.com/kmansur/zabbix-template-update-manager.git
cd zabbix-template-update-manager
git fetch origin release/0.1.0-beta.3
git checkout -B release/0.1.0-beta.3 origin/release/0.1.0-beta.3
cat VERSION
git rev-parse HEAD
```

Expected project version:

```text
0.1.0-beta.3
```

Record the exact `git rev-parse HEAD` output with the laboratory evidence. Do not test a later moving development branch while reporting results for this beta snapshot.

Copy or extract the complete repository content into a dedicated directory below the Zabbix frontend `modules` directory. Do not copy the `.git` directory into the frontend module directory when packaging manually.

## 4. Scan and enable the module

In the Zabbix frontend:

```text
Administration → General → Modules → Scan directory
```

Locate **Zabbix Template Update Manager**, confirm version `0.1.0-beta.3`, then enable it.

Expected navigation:

```text
Data collection → Template updates
```

If the module does not appear, verify `manifest.json`, filesystem read/search permissions and the actual frontend modules directory before changing code.

## 5. Inventory and upstream smoke test

Open **Data collection → Template updates**.

Record:

- module version displayed by the page;
- detected Zabbix version;
- compatibility state;
- visible template count;
- official UUID matches;
- update-available count;
- repository/cache state.

Expected for the existing Zabbix 7.0.30 lab:

- module version shown as `0.1.0-beta.3`;
- visible-template count remains plausible compared with the previous 303-template passes;
- the 7.0 upstream index validates instead of failing on official paths containing `+`;
- official UUID matches become non-zero;
- version summary becomes populated;
- no PHP fatal error;
- no direct database requirement.

If the upstream index still cannot be loaded, capture the complete **Upstream diagnostics** table:

```text
Requested index:
cURL:
allow_url_fopen:
OpenSSL:
Failure detail:
```

The requested Zabbix 7.0 index is expected to be:

```text
https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/upstream-index/indexes/7.0.json
```

A repository failure must still leave templates in the fail-closed `Repository unavailable` state rather than guessing official identity. If the failure detail reports another source path, stop and report that exact path before write-path testing.

## 6. Native selected-template checkbox test

Proceed only after upstream identity and version comparison work.

Expected inventory behavior:

- the leftmost table header contains the standard Zabbix select-all checkbox;
- row checkboxes appear only for templates with `Official UUID match` + `Update available`;
- current, unresolved, custom or repository-unavailable rows are not selectable for the selected-update workflow;
- clicking the header checkbox selects/deselects the eligible rows using native Zabbix `checkAll()` behavior;
- selecting only two or three update candidates and pressing **Review selected updates** shows only those selected template IDs;
- the selected review page performs no `configuration.import` and states that each template still needs its own safety workflow.

For this beta, do not select more than 100 templates in one review operation. The controller intentionally fails closed above that bound.

## 7. Comparison test

From the selected review page, choose a single official outdated template and open **Review update**.

Confirm that the page can show, when applicable:

- installed and available vendor versions;
- current-upstream import preview;
- historical official baseline;
- BASE / LOCAL / UPSTREAM analysis;
- risk/review priority;
- directly linked host count;
- update-readiness state.

For the first write-path test, continue only with a template whose evidence reaches `candidate_for_backup`.

## 8. Backup and verification test

Use **Create rollback backup**.

Expected filesystem result below the selected template directory:

```text
backup-<UTC timestamp>-<hash prefix>.yaml
backup-<UTC timestamp>-<hash prefix>.json
```

Expected permissions on Unix:

```text
0700  template directory
0600  YAML and JSON files
```

Re-open/reload the comparison. Expected readiness:

```text
backup_verified
```

The verification must prove that the newest intact stored artifact exactly matches a fresh `configuration.export` of the currently installed template.

## 9. Controlled update preflight

Run the controlled preflight.

Expected:

- preflight recomputes server-side evidence;
- exact upstream commit/path/content hash are bound;
- rollback/current-export fingerprints match;
- no configuration write occurs on the preflight page;
- only a super administrator receives the explicit controlled-update confirmation when all gates pass.

Record the preflight evidence fingerprint for the test evidence log.

## 10. Controlled update

Read the confirmation carefully and submit the explicit update confirmation.

The module must, immediately before import:

1. rerun authoritative preflight;
2. reject changed evidence;
3. re-fetch the immutable official candidate;
4. validate canonical source SHA-256;
5. revalidate candidate identity;
6. call the single controlled `configuration.import` boundary;
7. run fresh post-import validation.

Expected successful result:

```text
updated
write_performed = yes
post-update validation = passed
remaining differences = 0
```

Afterward, re-open the inventory/comparison and confirm the template is current and its content matches current upstream.

## 11. Rollback review

Open the template's **Rollback backups** history.

The old pre-update artifact should remain present. As a super administrator, select **Review rollback** for that valid artifact.

Expected review behavior:

- artifact integrity is revalidated;
- current template is freshly exported;
- template ID/UUID identity is checked;
- `configuration.importcompare` previews the restore;
- no configuration write occurs on the review page;
- a deterministic rollback evidence fingerprint is generated;
- an invalid artifact cannot be selected.

## 12. Controlled rollback

Submit the explicit rollback confirmation.

Before importing the older artifact, the module must create a **new recovery backup** of the current post-update state and prove its exact SHA-256/byte-count equality with the final preflight current export.

Expected successful result:

```text
rolled_back
write_performed = yes
post-rollback validation = passed
remaining differences = 0
```

Verify that both artifacts remain available:

- the older rollback target;
- the new recovery backup containing the state that existed immediately before rollback.

The rollback must never be triggered automatically from an update failure.

## 13. Negative/fail-closed tests

At minimum, exercise safe negative cases that do not require corrupting production data:

- attempt selected review with no selected checkbox: no write must occur;
- current/non-update rows must not become selectable through normal UI interaction;
- open history as a normal administrator: history may be visible, but rollback action must not be offered;
- open update/rollback write action as a non-super-admin: permission must be denied;
- leave the explicit confirmation unchecked: write action must be rejected by input validation;
- choose a template with unresolved/conflict/local-overwrite state: controlled automatic update must not be offered;
- choose an artifact already matching current state: rollback write control must not be offered.

Optional filesystem tamper tests should be performed only in the disposable lab. If a stored YAML or manifest is altered, the artifact must become invalid and must not be eligible for rollback.

## 14. Logs and evidence to capture

For each tested Zabbix generation, record:

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
Requested upstream index:
Upstream transport state:
Upstream failure detail (if any):
Test template name:
Template UUID:
Installed version before:
Available version:
Readiness state:
Update preflight fingerprint:
Update result:
Installed version after update:
Rollback target manifest:
Rollback preflight fingerprint:
Rollback result:
Installed version after rollback:
Backup directory permissions:
Any frontend/PHP errors:
```

Screenshots are useful for UI evidence, but CLI/log evidence should also be retained for failures.

## 15. Stop conditions

Stop the test and do not perform another configuration write if any of these occurs:

- PHP fatal/uncaught exception around update or rollback;
- an operation reports `write_performed = true` but final validation is not proven;
- the installed template UUID changes unexpectedly;
- duplicate template objects appear;
- backup artifact integrity fails unexpectedly;
- the recovery backup does not match the current export;
- the module offers a write action for conflict/unresolved/local-overwrite state;
- selected-template review includes IDs that were not selected;
- frontend logs indicate an ambiguous `configuration.import` result.

In a write-performed-but-unvalidated state, inspect the current Zabbix template manually before choosing any next operation. Do not retry automatically.

## 16. Exit criteria for the beta field-validation pass

The beta can advance beyond initial laboratory status only after both a Zabbix 7.x and a Zabbix 8.x lab have demonstrated, with evidence:

```text
module discovery/enable
inventory
upstream UUID match
native checkbox/select-all subset selection
selected-template review
comparison
historical baseline where applicable
backup creation + verification
controlled update + post-validation
rollback review
recovery backup
controlled rollback + post-validation
permission/CSRF negative checks
no unexpected PHP/frontend errors
```

A green GitHub CI run is necessary but is not a substitute for this runtime validation.
