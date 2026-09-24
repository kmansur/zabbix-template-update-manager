# Laboratory test plan — 0.1.0-beta.57

## Release state

This is a laboratory test release, not a production recommendation.

Status at publication:

- implementation: ready for end-to-end laboratory validation;
- automation validation: must be green on the release snapshot commit;
- field validation: in progress;
- target Zabbix generations: 7.x and 8.x.

Use the exact prerelease tag when a public beta has been published. During internal laboratory work before that tag exists, record the exact `main` commit used so the test can be reproduced.

Beta.57 is the current native-UI and update-policy field-test candidate. Field validation must prove the complete catalog/update/install/rollback workflow in both light and dark themes while confirming that the underlying comparison, evidence and controlled-write behavior remains unchanged.

## Safety assumptions

Use a disposable or otherwise non-production Zabbix environment.

For the first write-path pass, select only a small number (2–3) of official templates with updates available. Prefer templates with no detected local modifications and complete historical/three-way analysis. `none`/`low` risk is standard-path eligible; only explicitly recognized bounded-medium changes such as discard-only preprocessing maintenance may remain on the standard path.

Do not begin with a business-critical template or host. Medium impact remains manual unless the risk analyzer explicitly marks the exact known change class `standard_path_eligible`. Medium/high and local-overwrite candidates may use the explicit reviewed batch path only after verified rollback evidence, per-row selection and the required acknowledgements. Conflict and unresolved templates remain hard blocked.

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
git checkout main
cat VERSION
git rev-parse HEAD
```

Record the exact project version and commit before starting the test.

Record the exact commit SHA. Install the complete module directory below the Zabbix frontend `modules` directory, then run:

```text
Administration → General → Modules → Scan directory
```

Confirm `0.1.0-beta.57`, enable the module and open:

```text
Data collection → Template updates
```

## 2A. Native Zabbix UI regression

Validate the module in both Zabbix light and dark themes.

Check at minimum:

- **Data collection → Template updates** catalog;
- update batch preparation/execution;
- template comparison;
- individual preflight/update result;
- **Not installed** catalog and installation batch;
- installation review/result;
- rollback history/review/result.

Expected behavior:

- page header/navigation/actions use the same native Zabbix visual language as adjacent built-in pages;
- reviewed row controls render as native Zabbix checkboxes, not browser-default raw inputs;
- **Select all eligible reviewed updates** and **Clear reviewed selection** use native Zabbix buttons;
- tables, filters and action bars inherit Zabbix spacing/typography/theme automatically;
- Ready/success states use native success styling, review/risk states native warning styling, blocked/error states native error styling and neutral/unavailable states native neutral styling;
- state remains understandable from text without relying on color alone;
- empty catalog filters use a contextual native Zabbix no-data message instead of a generic large empty table;
- upstream source, commit and index-cache state are integrated into the **Official catalog** summary table rather than displayed as a loose metadata line;
- empty filtered catalog views do not show selection instructions that cannot be acted on;
- operator-visible reasons are readable labels such as `Historical baseline unavailable`, not raw snake-case service codes;
- UUID/commit/SHA-256 values are compact and monospace where appropriate, with the complete value available through the element title;
- section headings and descriptive/status messages are visually consistent across catalog, comparison, update, installation and rollback flows;
- there are no hard-coded colors, Bootstrap/Tailwind/Material UI artifacts or module-specific visual theme;
- dynamic batch controls are interactive only after document-ready lifecycle initialization;
- UI enable/disable state never bypasses server-side permissions, fresh preflight or evidence checks.

Repeat this visual pass on at least one supported Zabbix 7.x lab and one supported Zabbix 8.x lab before calling the UI field-validated.

## 2B. Native catalog pagination regression

On both Zabbix 7.x and 8.x frontends with more catalog entries than the configured rows-per-page value:

1. Open **Data collection → Template updates** and confirm the catalog renders without HTTP 500/PHP fatal errors.
2. Confirm the pager matches the native Zabbix pagination used by built-in pages.
3. Navigate through several pages and return to page 1.
4. Repeat with at least one filtered status such as **Update available** or **Not installed**.
5. Confirm there is no module-specific **All** or **Pages** control below the native pager.
6. Confirm the frontend/Nginx/PHP logs contain no pager-related PHP errors.

Catalog pagination must be provided only by `CPagerHelper::paginate()`. Do not recreate native pager markup or add module-specific display modes around it.

## 2C. Native catalog name-filter regression

On both Zabbix 7.x and 8.x:

1. Open **Data collection → Template updates** and expand **Filter**.
2. Confirm a native **Name** text box appears above **Status**.
3. Enter a partial template name such as `Acronis`, apply the filter and confirm only matching visible template names remain.
4. Repeat with a short substring such as `Adv` against a template whose displayed name contains that substring; exact-name matching must not be required.
5. If a row displays a technical name in parentheses, search by a substring found only in that technical name and confirm the row is returned.
6. Repeat with different letter case and confirm matching is case-insensitive.
7. Combine **Name** with a non-default **Status** and confirm both conditions are applied.
8. Navigate away from the first result page when possible, change the filter and confirm the catalog returns to page 1.
9. Click **Reset** and confirm both Name and Status return to their defaults.
10. Enter a value that matches no template and confirm the native no-data message explains that the Name filter can be changed or cleared.
11. Confirm the catalog still uses only native `CPagerHelper` pagination and no custom **All / Pages** control reappears.

Name filtering must be applied before pagination and must not change catalog summary counts, update eligibility, Never update policy or any write-path behavior.

The filter layout must visually follow the native Zabbix Templates list pattern: `CFormGrid` with native label/field alignment and `ZBX_TEXTAREA_MEDIUM_WIDTH` for Name.

## 2D. Request-bounded update regression

For the first beta.57 write-path test, prepare several update candidates but keep the Ready subset small enough to inspect easily.

Expected behavior after confirmation:

- the browser remains on the preparation page instead of navigating to `action=ztum.templates.batch_update`;
- each Ready row changes to `Updating...` and then `Updated and validated` before the next row starts;
- the execution summary increments Updated/Failed/Not attempted after each request;
- the first non-success or HTTP failure stops the queue and all later Ready rows become `Not attempted`;
- the cumulative duration of several updates may exceed the proxy timeout because no single request spans the whole batch;
- if one individual request itself returns 504, do not retry that template until its installed state and logs have been checked.

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

## 3A. Official catalog / missing-template installation

Confirm the Templates table uses native Zabbix pagination and contains upstream-only entries with:

```text
Installed: —
Status: Not installed
Upstream identity: Official catalog
Action: Review installation
```

For one non-critical official template that is absent locally and has no missing template-link dependencies:

1. open **Review installation**;
2. confirm official UUID/version/commit/path/raw SHA-256;
3. confirm dependency list is complete;
4. confirm the **Structural reference audit** is Passed with no unresolved issues;
5. confirm import preview has `Added > 0`, `Updated = 0`, `Removed = 0`;
6. confirm as Super Admin and install;
7. verify result `Installed and validated`;
8. return to catalog and confirm the row is now installed/current rather than `Not installed`.

Also test at least one blocked dependency case if naturally available. Missing linked templates must be listed and the write must remain disabled.

Do not test recursive dependency installation: dependencies are intentionally installed explicitly first.

## 3B. Multi-template installation

From the `Not installed` filter, first verify the header checkbox selects all visible missing templates. For the write test, select 2–3 non-critical official templates with no missing linked-template dependencies and choose **Prepare selected installations**.

Expected preparation:

```text
Selected: N
Completed: N
Ready: N or subset
Blocked: remainder
```

Only Ready rows may be submitted. If preparation finishes with `Ready = 0`, verify the page explicitly shows `Unavailable — no Ready templates` and explains that blocked reasons must be resolved first.

Confirm **Install ready templates** when at least one candidate is Ready and verify:

- execution is sequential and each Ready template uses its own HTTP request;
- every successful row returns a local Template ID and `validated` post-install state;
- the batch reports Installed / Failed / Not attempted / Any configuration write;
- returning to the catalog shows successful rows as installed/current.

Negative case: include one candidate with a missing linked-template dependency if available. It must remain Blocked and must not be present in the execution set. ZTUM does not recursively install selected dependencies.

Stop-on-first-failure remains mandatory: if one controlled install returns a non-success, subsequent Ready UUIDs must be reported Not attempted.

## 4. Native checkbox/subset selection

Confirm:

- eligible `Official UUID match` + `Update available` rows have active checkboxes;
- current/unresolved/custom rows keep a visible but disabled checkbox and are not selectable through the normal update selection workflow;
- the header checkbox uses native Zabbix selection behavior;
- select only 2–3 candidates for the first test;
- **Prepare selected updates** opens the request-bounded preparation queue directly with only those explicitly selected template IDs.

The former 25-template update-batch ceiling remains removed. Update selection retains the existing **500-template** sanity ceiling. Selections larger than 25 must reach preparation intact, and preparation must still run one template per HTTP request without silent truncation.

## 3C. Inherited host-impact regression

Choose one template that is directly linked to hosts and, when available, one parent template inherited by another template that is linked to additional hosts.

1. Open the comparison page.
2. Confirm **Risk and impact** shows direct, indirect, total impacted hosts and dependent-template count.
3. For a template with no descendants, confirm indirect hosts and dependent templates are zero.
4. For a parent template with descendants, compare the reported total with the unique hosts linked to the selected template or any descendant template.
5. Confirm the total never becomes smaller than the direct count.
6. If the inheritance graph cannot be resolved, confirm ZTUM reports the limitation and does not invent an indirect count.

This analysis is read-only and must not change readiness or write authorization solely because impact metadata is unavailable.

## 4A. Never update policy regression

As Super Admin, choose one installed official template that is safe to use for a policy-only test.

1. From **All**, **Current** or **Update available**, select the template and choose **Never update**.
2. Confirm the row shows **Never update** in the **Update policy** column.
3. Confirm the protected-template summary count increases and, when the template had an update available, the actionable **Updates available** count no longer includes it.
4. Open the **Never update** filter and confirm the template appears there.
5. Confirm read-only comparison remains available, while normal update preparation/preflight refuses the protected template.
6. As a non-Super Admin, confirm policy mutation controls are unavailable.
7. Return as Super Admin, select the template under the **Never update** filter and choose **Allow updates**.
8. Confirm the policy returns to managed state and normal eligibility is recalculated.

Also verify the private policy file is created under:

```text
/var/lib/zabbix-template-update-manager/update-policy.json
```

A malformed/unreadable policy store must fail closed for update writes; it must never silently restore update eligibility.

## 4B. Large selected-update regression

Select at least 26 eligible official update candidates in the lab.

Expected behavior:

- **Prepare selected updates** accepts the complete selection directly from the catalog;
- the preparation queue shows all selected candidates without truncation;
- preparation runs sequentially, one template per HTTP request;
- progress reaches the full selected count;
- Stop after current template remains available while preparation is running;
- there is no reappearance of the old 25-template rejection;
- the 500-template selected-update sanity ceiling remains fail-closed.

## 5. Direct catalog selection → batch preparation

As Super Admin, select the intended official update candidates in the catalog and click:

```text
Prepare selected updates
```

Expected behavior:

1. each selected template runs the full authoritative update analysis using bounded HTTP work; an installed official `<major>.<minor>-0` template first resolves BASE from the immutable official `<major>.<minor>.0` UUID index, while other versions may use request-bounded historical continuation;
2. historical baseline and BASE/LOCAL/UPSTREAM analysis are reused;
3. conflict/local-overwrite/unresolved states remain blocked;
4. high risk, local-overwrite and unrecognized medium-risk cases become Manual review; recognized bounded-medium cases may remain on the standard path;
5. standard-path candidates (`none`/`low` plus recognized bounded-medium) that reached `candidate_for_backup` receive/refresh a persistent rollback artifact;
6. after backup creation, only rollback verification/readiness is refreshed from the completed analysis snapshot;
7. `backup_verified` candidates derive bounded preparation evidence without repeating the complete upstream/history/importcompare analysis;
8. only a valid preparation evidence SHA-256 can classify the template as `Ready`;
9. controlled execution still reruns a complete authoritative fresh preflight immediately before any write;
10. no `configuration.import` occurs during preparation.

The page must summarize:

```text
Selected
Ready
Manual review
Conflict
Blocked
```

For a mixed plan, deliberately keep at least one `Ready` and one `Manual review`/Conflict/Blocked candidate when naturally available. After preparation completes, confirm that:

- the execution state shows `Available — N Ready template(s).`;
- the confirmation checkbox becomes enabled whenever `N > 0`, regardless of non-Ready rows in the same plan;
- only Ready rows contribute hidden template/evidence inputs;
- a Ready row without a valid 64-hex SHA-256 evidence value is converted to Blocked and cannot be submitted;
- if `Ready = 0`, the execution state shows `Unavailable — no Ready templates.`;
- stopping preparation keeps execution unavailable even if an earlier row had become Ready.

### Initial-release baseline regression

For Zabbix 7.0, include several untouched official templates still installed at vendor version `7.0-0`, including at least one AWS template whose source path changed after 7.0.0.

Expected behavior:

- the baseline is resolved from the immutable `7.0.0` UUID index without a historical continuation loop;
- AWS templates that lived in separate YAML files at 7.0.0 remain resolvable even though the current upstream source is the consolidated AWS YAML;
- `historical_baseline_unavailable`, `historical_baseline_ambiguous` and `historical_baseline_continuation_limit_reached` must not be produced solely because of later source-file restructuring;
- the resulting BASE still feeds native import comparison, three-way analysis, risk evaluation and rollback gates;
- if UUID/vendor/version does not match the immutable initial index exactly, the flow falls back conservatively to normal historical discovery.

## 5A. Preparation failure / zero-Ready regression

Use a naturally failing preparation request if available; the current field case is `VMware SD-WAN VeloCloud by HTTP` returning an HTTP 504 while the other VMware candidates classify as Manual review.

Expected behavior:

- after all rows finish, a plan with `Ready = 0` shows `Unavailable — no Ready templates.` in both the execution-state text and the lower execution-summary Status cell;
- `Retry failed preparation` becomes enabled only when at least one row has `readiness = request_failed`;
- clicking retry reruns only request-failed rows, sequentially, without restarting Manual review/Conflict/normal Blocked rows;
- the previous request-failed Blocked count is removed before fresh reclassification, so summary totals remain consistent;
- an HTTP failure reason includes elapsed time, for example `HTTP 504 after 100.1s`;
- if retry succeeds and produces an evidence-backed Ready row, normal execution controls become available;
- a long historical scan may show `history_scan_pending` / continuation progress and issue several bounded requests for the same template before final classification;
- no single historical continuation request should approach the previous ~30-second gateway failure window;
- if an HTTP failure still occurs, the Reason should include elapsed time and gateway identity such as `server=cloudflare` / `cf-ray=...` when exposed by the response;
- if a real 504 still occurs on beta.57, capture that reason plus frontend/PHP logs; it is then an infrastructure/transport failure rather than the expected historical continuation path;
- preparation continuation/retry must never call `configuration.import`.

## 5B. Manual-review continuation regression

Select one or more candidates that naturally classify as `Manual review` with verified rollback evidence. Use any naturally available Manual review candidate in the test environment.

Expected behavior:

- every Manual review row exposes a native `Review details` link in the Execution column;
- the link opens the existing individual template comparison for that exact template ID;
- a review-only plan with `Ready = 0` explains that unattended batch execution is unavailable and directs the operator to `Review details`;
- the comparison page retains the detailed BASE / LOCAL / UPSTREAM, risk and rollback evidence;
- when readiness is `review_backup_verified`, the next action is `Run reviewed controlled preflight`;
- the reviewed preflight must bind the manual-review reasons and still require the additional explicit Super Admin acknowledgement before `configuration.import`;
- opening `Review details` itself performs no configuration write.

## 5C. Explicit reviewed batch override regression

Use Manual review candidates whose only reasons are `medium_technical_risk` and/or `high_technical_risk`. Use any naturally available Manual review candidate in the test environment.

Expected behavior:

- an eligible Manual review row has an active checkbox in the first `Include` column and shows `Reviewed batch eligible · Review details` in Execution;
- the table header has a select-all checkbox that selects every eligible reviewed row and no ineligible row;
- changing one reviewed row manually updates the header checkbox to checked/unchecked/indeterminate as appropriate;
- the row remains classified `Manual review`; it must not be reclassified as Ready;
- the main batch confirmation stays disabled until at least one Ready row exists or one eligible Manual review checkbox is selected;
- selecting reviewed rows enables the confirmation path and the status reports Ready plus selected-reviewed counts separately;
- after the global confirmation is checked, `Update eligible templates` executes Ready rows plus explicitly selected reviewed rows in original selection order;
- reviewed rows POST both manual-override acknowledgement fields and rerun fresh reviewed preflight before import;
- successful reviewed rows finish as `Updated and validated`;
- stop-on-first-failure still applies across the combined queue;
- a row containing `local_customization_overwrite` has an active reviewed checkbox only when rollback and manual preflight evidence are valid; Execution shows `Reviewed overwrite eligible · Review details`;
- Conflict, Blocked, unresolved and request-failed rows must never be selectable or executable.

## 5D. Sticky select-all during preparation

Start a large preparation run and check the header `Include all eligible` control before preparation finishes.

Expected behavior:

- the header checkbox is enabled even if zero eligible reviewed rows have completed so far;
- the checked state remains active while preparation continues;
- every later Manual review row that is technical-risk-only and has valid reviewed evidence appears checked automatically;
- local-customization-overwrite rows may be selected only after they receive valid reviewed evidence; they must never become unattended Ready;
- Blocked/request-failed/unresolved rows never become selected;
- manually clearing an individual reviewed row cancels sticky select-all and puts the header into the appropriate unchecked/indeterminate state;
- execution remains disabled until preparation completes.

## 5E. Local-overwrite reviewed batch regression

Use one or more candidates that naturally report `local_customization_overwrite` plus technical-risk reasons.

Expected behavior:

- the row remains `Manual review` and keeps its local-overwrite reason visible;
- after verified rollback and a passing reviewed preflight, the leading Include checkbox becomes active;
- header `Include all eligible` may select it together with other reviewed candidates;
- Execution shows `Reviewed overwrite eligible · Review details`;
- selecting any local-overwrite row enables the additional acknowledgement:
  `I explicitly accept overwriting local customizations for the selected templates.`;
- `Update eligible templates` remains disabled until both the normal reviewed acknowledgement and the local-overwrite acknowledgement are checked;
- the per-template request carries `manual_override=1`, `confirm_manual_override=1` and `confirm_local_overwrite=1`;
- omitting the local-overwrite acknowledgement must return a no-write blocked result before import;
- successful acknowledged rows still rerun fresh reviewed preflight, verify evidence, import through the single approved write boundary and validate afterward;
- Conflict, unresolved, request-failed and unknown manual-reason rows remain unselectable.

## 5F. Explicit select-all / preparation-wait regression

Start a large update preparation and use `Select all eligible` before preparation finishes.

Expected behavior:

- per-row reviewed checkboxes remain in the first Include column;
- `Select all eligible` selects every currently eligible reviewed row and remains sticky for later eligible rows;
- `Clear reviewed selection` clears current reviewed selections and cancels sticky selection;
- manually clearing one row also cancels sticky select-all;
- while preparation is incomplete, the execution status explicitly shows completed/total progress and states that update execution starts only after preparation completes;
- update controls remain write-disabled until the full selected set has completed preparation.

## 5G. Cross-template installation dependency regression

Use an absent official template with a top-level trigger/graph/dashboard reference to another template (current field example: Vyatta Virtual Router by SNMP).

Expected behavior:

- install-mode isolation preserves the selected template's cross-template definition instead of returning `cross_template_trigger_dependency`/graph dependency immediately;
- the external template name appears in Required dependencies;
- if the external template is not installed, it appears in Missing dependencies and the candidate remains Blocked with `missing_template_dependencies`;
- after installing that dependency first, preparing the dependent template again may continue to reference audit/import preview;
- update/historical strict isolation remains unchanged and still blocks unsafe cross-template isolation;
- installation remains non-recursive and never installs missing dependencies automatically.

## 5H. Update cross-template comparison regression

Prepare updates for templates that previously ended as `blocked_unresolved / comparison_error`, including current field examples Check Point Next Generation Firewall by SNMP, Cisco SD-WAN device by HTTP, Elasticsearch Cluster by HTTP and Generic Java JMX.

Expected behavior:

- cross-template trigger/graph/dashboard references no longer fail solely because the selected official template was strictly isolated;
- if Zabbix importcompare can resolve the external template references, comparison continues into preview / historical / risk analysis;
- if comparison still fails, the Reason column includes `comparison_error:` followed by the sanitized diagnostic text rather than only the generic token;
- any external template names discovered from the official source are bound into fresh preflight evidence;
- immutable candidate reconstruction immediately before import must produce exactly the same external dependency-name set or fail closed before the write;
- `Select all eligible (N)` always shows the current eligible reviewed count, and after completed preparation with N=0 the control is disabled rather than looking actionable.

## 6. Batch classification sanity checks

Before any write, inspect at least one item from each category that naturally occurs:

- **Ready**: eligible for controlled sequential execution;
- **Manual review**: high or unrecognized-medium technical review state, or local-overwrite reviewed path;
- **Conflict**: must never be executed; authoritative local-overwrite without conflict remains Manual review only;
- **Blocked**: incomplete/unresolved/not-applicable evidence.

If every selected item becomes blocked unexpectedly, stop and inspect the individual **Review update** page for one template before changing code or filesystem data.

### Beta.39 risk-calibration regression

When available in the lab, include `APC UPS Symmetra RM by SNMP` at installed version `7.0-3` with upstream `7.0-4`. The official delta removes `DISCARD_UNCHANGED_HEARTBEAT 6h` from a status item.

Expected analysis:

```text
technical impact: medium
risk reason: bounded_preprocessing_discard_change
standard path eligible: yes
local overwrite/conflict/unresolved: 0
```

After verified rollback evidence and fresh preflight, this candidate may become `Ready`. A changed JavaScript/regex/transformation preprocessing step must still classify high/manual.

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
5. validate exact commit/path, the path-specific raw YAML SHA-256, the separately bound canonical template-content fingerprint and template identity;
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

For a template whose official source contains top-level graphs/triggers (the Acronis MSP template is the current regression case), confirm the comparison no longer reports those official objects as LOCAL-only solely because they live outside the nested template object.

If a dashboard references a top-level graph, the controlled update must preserve that graph in the isolated candidate and must not produce `Cannot find graph ... used in dashboard ...`.

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
- stored YAML is compared with `format=yaml` (no JSON parse error);
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

Rollback remains an explicit per-template operation; ZTUM does not perform automatic batch rollback.

## 12. Permission/CSRF negative checks

Confirm:

- normal Admin can inspect supported read-only pages but cannot run batch preparation/execution or change the Never update policy;
- only Super Admin can prepare and execute selected updates;
- leaving batch confirmation unchecked is rejected;
- direct POST without the valid action-specific CSRF token is rejected by native Zabbix handling;
- tampering a reviewed evidence fingerprint prevents that template from being written;
- submitting more than the 500-template selected-update sanity ceiling fails closed;
- conflict/manual-review/blocked items are absent from the hidden Ready execution set;
- a preflight built from a legacy index without a raw source fingerprint remains blocked until the refreshed index is available.

## 13. Filesystem tamper test (optional, disposable lab only)

If a stored backup YAML or JSON manifest is altered, the artifact must become invalid and must not be accepted as verified rollback evidence.

Do not perform this on a production backup store.

## 13A. Controlled-operation serialization regression

In a disposable lab, start one controlled update/install/rollback request and, while it is still inside fresh preflight/import/validation, attempt a second configuration-changing ZTUM operation from another browser/session.

Expected behavior:

- the first operation proceeds normally;
- the second operation fails closed with `Another Template Update Manager configuration operation is already in progress.`;
- the second operation must not start a second controlled preflight/import;
- after the first request finishes, a later controlled operation can acquire the lock normally;
- on a multi-node frontend, run this only when all nodes share a lock filesystem with reliable cross-node `flock()`; otherwise record cross-node serialization as unproven.

## 13B. Air-gapped/offline upstream regression

Build an offline bundle on a connected system using the exact validated 7.x or 8.x index and a local canonical Zabbix Git checkout. Copy it to private storage on the lab frontend.

Configure:

```text
ZTUM_OFFLINE_BUNDLE_DIR=/var/lib/zabbix-template-update-manager/offline
ZTUM_OFFLINE_ONLY=1
```

Expected behavior:

- diagnostics show Offline bundle = Configured and Offline-only = Enabled;
- catalog/comparison/preflight can use the bundle with network egress disabled;
- the loaded index reports offline runtime state;
- tampering one consumed bundle file causes SHA-256 verification failure and no network fallback;
- removing a required index/source/history artifact blocks the operation rather than using public endpoints;
- controlled update/install safety gates remain unchanged.

## 13B. Never update policy regression

Use one installed official template that currently has an update available and one installed official template that is already current.

Expected behavior:

- a Super Admin can select one or more installed official templates and choose **Never update**;
- the catalog displays **Never update** in the **Update policy** column;
- the **Never update** status filter shows the protected installed templates;
- protected templates are excluded from the actionable **Updates available** count and cannot enter update preparation;
- opening an individual comparison remains read-only and clearly shows that the update path is blocked by policy;
- fresh update preflight returns the explicit blocker `update_policy_never`;
- controlled update rechecks the policy immediately before import and performs no configuration write while protection is active;
- selecting protected templates under the **Never update** filter and choosing **Allow updates** removes the block;
- after **Allow updates**, a template with a newer official version becomes eligible for the normal preparation/preflight path again;
- corrupt or unreadable existing policy storage fails closed and does not enable a configuration write.

## 13C. Runtime setup helper regression

Run:

```bash
sudo tools/ztum-runtime-setup.sh --check
sudo tools/ztum-runtime-setup.sh --apply --user <actual-php-fpm-user>
sudo tools/ztum-runtime-setup.sh --check --user <actual-php-fpm-user>
```

Expected behavior:

- private runtime directories exist as mode `0700`;
- ownership matches the PHP-FPM runtime account;
- the backup directory is writable by that account;
- a symbolic-link runtime directory is refused;
- when multiple PHP-FPM users are detected, the helper refuses to guess and requires `--user`.

## 13D. Individual local-overwrite acknowledgement regression

Exercise the individual reviewed update path for a candidate whose manual reasons include `local_customization_overwrite`.

Expected behavior:

- the normal reviewed-risk acknowledgement remains required;
- a separate `Local customization overwrite` checkbox is rendered;
- omitting that specific acknowledgement blocks before `configuration.import`;
- accepting both acknowledgements allows only the existing fresh-preflight/evidence-controlled path.

## 13E. Trigger-expression dependency regression

Prepare missing-template installation for Jira Data Center by JMX and Vyatta Virtual Router by SNMP.

Expected behavior:

- arithmetic division between history functions must identify only real template hosts;
- `last(` must never appear under Required dependencies or Missing dependencies;
- a real external-template reference must still remain a dependency and block when missing;
- structural reference auditing must use the same host-extraction semantics.

## 13F. Single-page catalog pager regression

Use a status filter whose complete result fits within the current Zabbix rows-per-page preference.

Expected behavior:

- native table statistics remain visible;
- the custom `All` / `Pages` display-mode control is not rendered;
- when the filtered result exceeds rows per page, native pagination plus the reversible `All` control remains available.

## 13G. Create-only installation rule regression

Prepare a genuinely missing official template whose template group already exists locally, then execute controlled installation.

Expected behavior:

- fresh preflight and the write use the same `install_create_only_v1` rule profile;
- existing template groups and other pre-existing configuration are not updated/deleted by the installation rule profile;
- missing required groups may be created;
- a successful install is followed by exact post-install validation;
- a Zabbix import rejection is reported as `import_failed`, not as an HTTP request failure;
- the batch stops immediately and reports an uncertain import outcome plus read-only target inspection;
- no automatic retry or continuation occurs.

## 14. Evidence to capture

Record for each Zabbix generation:

```text
Zabbix version:
PHP version:
Frontend/web runtime user:
Module version:
Module version / exact commit or release tag:
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
- a template protected by **Never update** is offered for update preparation or execution;
- selected review contains IDs that were not selected;
- frontend logs indicate an ambiguous `configuration.import` result.

In a write-performed-but-unvalidated state, inspect the current Zabbix template manually before choosing the next operation.

## 16. Exit criteria for beta.57 laboratory validation

A Zabbix generation passes beta.57 only after evidence demonstrates:

```text
module discovery/enable
inventory
official catalog + native pagination
upstream-only Not installed detection
controlled individual installation + post-install validation
upstream UUID/version matching
native subset selection
direct selected-update preparation
batch safety preparation
Ready/Review/Conflict/Blocked classification
persistent rollback creation + verification
Never update policy + Allow updates reversal
controlled sequential update
stop-on-first-failure behavior (real or safely simulated)
post-update validation
individual comparison/preflight regression
rollback review
recovery backup
controlled rollback + post-validation
permission/CSRF negative checks
global controlled-operation serialization
air-gapped/offline-only regression
runtime setup helper validation
individual local-overwrite acknowledgement
no unexpected PHP/frontend errors
```

Run Zabbix 7.x first. Repeat on Zabbix 8.x after the 7.x path is understood. A green GitHub CI run is necessary but does not replace runtime field validation.
