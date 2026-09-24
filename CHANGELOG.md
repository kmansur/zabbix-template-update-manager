# Changelog

All notable changes to Template Update Manager will be documented in this file.

## [Unreleased]

### Added

- Added a privacy-conscious read-only field-evidence collector for the remaining beta.59 Zabbix 7.x/8.x laboratory matrix, including module identity, release checksum verification, runtime-directory metadata and operator browser/theme metadata without collecting secrets or network/database identifiers.

### Changed

- Laboratory validation now pins the immutable `v0.1.0-beta.59` release artifact explicitly, and roadmap/audit links reflect the published prerelease state.

## [0.1.0-beta.59] - 2026-09-24

### Added

- Added disposable official Zabbix 7.x and 8.x full-stack browser smoke environments using PostgreSQL, Zabbix server and Zabbix web containers.
- Added Chromium smoke coverage for real module registration, Super Admin login, catalog rendering, native Name filtering, operation-history rendering and both dark/light themes, with an explicit runtime-major assertion so the rolling Zabbix 8 image cannot silently drift to an unsupported future major.
- Added minimum automated coverage floors for runtime-file reachability and observed executable-line coverage.

### Changed

- Release/production-readiness/compatibility documentation now distinguishes full-stack read-only browser evidence from real controlled-write field validation.
- The laboratory test plan is synchronized to beta.59.
- Coverage metrics are now a regression gate instead of report-only telemetry.

### Fixed

- Fixed module JavaScript asset registration to use filenames relative to Zabbix's native `assets/js` module directory; the previous prefixed paths produced duplicated `assets/js/assets/js/...` URLs and real Zabbix 7 frontend 404s.
- Removed ineffective global `RuntimeException` imports from standalone tests to keep PHP 8.2/8.3/8.4 output warning-free.

### Safety

- The disposable full-stack smoke remains read-only. It does not execute update/install/rollback configuration writes and therefore does not replace the real Zabbix 7.x/8.x controlled-write field matrix.

## [0.1.0-beta.58] - 2026-09-24

### Security / runtime hardening

- Restricted catalog/read-only module access to Zabbix Administrators and Super Admins; configuration writes remain Super-Admin-only.
- Moved the default global operation lock to the persistent private runtime root at `/var/lib/zabbix-template-update-manager/locks`.
- Added AGPL-3.0-only licensing plus explicit Zabbix/project attribution notices.

### Added

- Added direct + inherited/indirect host-impact analysis through the visible template inheritance graph.
- Added private bounded supplemental operation history and an administrator-only native history page.
- Added PHP 8.2 / 8.3 / 8.4 syntax + unit-test CI matrix.
- Added native frontend symbol compatibility checks against Zabbix 7.0 and current Zabbix 8.0 sources.
- Added Chromium smoke tests for update/install batch orchestration assets.
- Added native accessibility contracts for navigation, filter labels and confirmation controls.
- Refreshed first-party GitHub Actions majors for checkout, Python setup and artifact upload workflows.

### Refactored

- Extracted the two large request-bounded batch JavaScript implementations into registered module assets.
- Batch PHP views now retain server-rendered native controls, JSON configuration/labels and only a minimal document-ready initializer.

### Changed

- Non-official templates display update policy as **Not applicable** instead of **Managed**.
- Documentation, compatibility notes, 1.0 readiness criteria and the consolidated field-validation issue were synchronized with implemented behavior.

### Field evidence

- Zabbix 7.0.31 dark-theme catalog + partial Name search (`Adv`) were confirmed on beta.57.
- Zabbix 8.0.0beta2 + PHP 8.4.24 catalog loading was confirmed after the pager compatibility fix; the complete Zabbix 8 write-path matrix remains open.

### Safety

- Configuration writes still converge on the single `TemplateConfigurationImportService` boundary.
- Browser/JavaScript tests do not replace server-side CSRF, permission, evidence, fresh-preflight or post-validation checks.
- Operation history is supplemental only and is never authorization/evidence.
## [0.1.0-beta.57] - 2026-09-23

### Fixed

- Fixed catalog Name search so partial matching checks the complete visible template name, including `technical_name` when it is displayed in parentheses.
- Short queries such as `Adv` no longer require an exact template name match.

### Changed

- Rebuilt the catalog filter layout to follow the native Zabbix Templates page pattern with `CFormGrid`, `CLabel`, `CFormField` and `ZBX_TEXTAREA_MEDIUM_WIDTH`.
- Name + Status filtering, profile persistence, page-1 reset and native `CPagerHelper` pagination remain unchanged.

### Tests

- Catalog contracts now require partial matching against the full visible name and the native Zabbix Templates filter-grid structure.
- Laboratory validation now explicitly covers short substrings, technical-name-only matches and the native filter layout on Zabbix 7.x and 8.x.

### Safety

- Read-only catalog search/presentation change only. Template comparison, backup, preflight, update, installation, rollback, Never update policy and the controlled `configuration.import` boundary are unchanged.

## [0.1.0-beta.56] - 2026-09-23

### Added

- Added a native **Name** text field to the catalog filter using the standard Zabbix `CFilter` / `CTextBox` pattern.
- Name filtering is persisted in the existing ZTUM filter profile and can be combined with the Status filter.

### Changed

- Template-name matching is case-insensitive and is applied before native `CPagerHelper` pagination.
- Applying or resetting the catalog filter returns the result set to page 1.
- Empty name-filter results now show a contextual native no-data message.

### Tests

- Catalog contracts now require the native Name textbox, profile persistence/reset, case-insensitive pre-pagination filtering and page-1 reset behavior.
- Laboratory validation now includes Name-only, Name + Status, case-insensitive, reset and zero-result checks on Zabbix 7.x and 8.x.

### Safety

- Read-only catalog filtering only. Template comparison, backup, preflight, update, installation, rollback, Never update policy and the controlled `configuration.import` boundary are unchanged.

## [0.1.0-beta.55] - 2026-09-23

### Changed

- Removed the custom catalog **All / Pages** display mode and now delegate pagination entirely to native `CPagerHelper::paginate()`.
- The catalog no longer creates pager markup or applies pager CSS classes itself; each supported Zabbix frontend generation renders its own native pager.
- Simplified cross-version guidance to prefer native Zabbix abstractions before introducing compatibility code.

### Removed

- Removed the now-unused `ZabbixUiCompat` pager compatibility helper and its dedicated test after custom pager composition was eliminated.
- Removed the `show_all` request/input state and the module-specific **All** and **Pages** controls.

### Tests

- Catalog contracts now require native `CPagerHelper` pagination only and reject reintroduction of custom **All / Pages** controls or pager-style compatibility code.
- Laboratory validation now explicitly checks that Zabbix 7.x and 8.x show only their native pager.

### Safety

- Presentation/navigation change only. Template comparison, backup, preflight, update, installation, rollback, Never update policy and the controlled `configuration.import` boundary are unchanged.

## [0.1.0-beta.54] - 2026-09-23

### Added

- Added `src/Support/ZabbixUiCompat.php` as the single compatibility boundary for proven native frontend differences between supported Zabbix generations.

### Changed

- Catalog pagination now asks `ZabbixUiCompat` for native pager and pager-container classes instead of carrying Zabbix 7.x/8.x constant checks inside the controller.
- Maintainer guidance and architecture documentation now explicitly require one codebase/package for Zabbix 7.x and 8.x, with proven frontend differences centralized in the compatibility layer.
- Recorded successful Zabbix 8 catalog loading after the beta.53 pager fix while keeping the full 8.x field matrix open.

### Tests

- Added direct compatibility tests proving Zabbix 7.x fallback behavior and Zabbix 8.x precedence for the renamed pager constants.
- Catalog controller contracts now reject locally implemented pager-version checks and require delegation to `ZabbixUiCompat`.

### Safety

- Refactor only: no template comparison, backup, preflight, update, installation, rollback, policy or `configuration.import` behavior changed.

## [0.1.0-beta.53] - 2026-09-23

### Changed

- Polished the public README for community testing with native GitHub CI/Security/Quality badges, clearer project positioning and a dedicated **Never update** usage section.
- Synchronized the engineering status and laboratory test plan with the current beta, including explicit **Never update / Allow updates** and Zabbix 8 pager compatibility regressions.

### Fixed

- Fixed an HTTP 500 on the Zabbix 8 template catalog caused by frontend pager constants renamed after Zabbix 7.x.
- Catalog pager composition now resolves the native Zabbix 8 `ZBX_STYLE_PAGER` / `ZBX_STYLE_PAGER_CONTAINER` constants first and safely falls back to the Zabbix 7.x `ZBX_STYLE_TABLE_PAGING` / `ZBX_STYLE_PAGING_BTN_CONTAINER` constants.
- Corrected stale beta.50 installation references, obsolete beta.42 laboratory exit criteria and outdated selected-update/install wording.
- Restored the missing Markdown code fence around the official-catalog installation workflow diagram.

### Tests

- Catalog contracts now require both Zabbix 7.x and 8.x pager constant families to be runtime-resolved and reject direct use of the removed Zabbix 7.x pager constants.
- Laboratory validation now includes explicit Zabbix 8 paginated/All/Pages catalog regression checks and log verification for undefined pager constants.

## [0.1.0-beta.52] - 2026-09-23

### Added

- Persistent per-template **Never update** policy for installed official templates.
- Native bulk actions to mark one or more templates **Never update** and later **Allow updates** again.
- Dedicated **Never update** catalog filter and **Update policy** table column.
- Official-catalog summary count for protected templates.
- Private UUID-based policy storage at `/var/lib/zabbix-template-update-manager/update-policy.json`.

### Changed

- The actionable **Updates available** count excludes protected templates.
- Protected templates remain available for read-only comparison but are excluded from normal update preparation.
- Catalog selection behavior is mode-aware: update candidates can be prepared or protected; current/all official templates can be protected; the **Never update** filter exposes the reversal action.

### Safety

- Policy mutation is Super-Admin-only and uses native CSRF validation.
- **Never update** is enforced in readiness analysis, fresh preflight and immediately before controlled import.
- Policy mutation serializes with the existing global controlled-operation lock.
- Existing malformed/unreadable policy storage fails closed for update writes.
- Policy storage never writes to the Zabbix database and introduces no second `configuration.import` boundary.

### Tests

- Added persistent policy repository tests including atomic private storage and malformed-state failure.
- Added UI/action/write-boundary policy contracts.
- Added fresh-preflight and immediate-before-import policy enforcement regressions.
- Added a laboratory field matrix for mark/filter/block/allow behavior.

## [0.1.0-beta.51] - 2026-09-23

### Changed

- Integrated upstream source, commit and cache state into the native **Official catalog** summary table instead of rendering them as a separate floating metadata line.
- Shortened the catalog selection guidance, made it visually secondary and moved it below the **Templates** heading.
- Empty filtered views no longer display irrelevant selection instructions; the contextual native no-data message is sufficient.
- Index cache state now uses native success/warning/neutral status styling.

### Safety

- This change is presentation-only. Catalog filtering, selection eligibility, preparation, preflight and controlled-write behavior are unchanged.

### Tests

- Catalog contracts now require integrated source/commit/cache metadata and suppress guidance for empty filtered results.

## [0.1.0-beta.50] - 2026-09-23

### Added

- Beginner-friendly new-install quick installers in English and Brazilian Portuguese.
- Matching quick-install guides with copy/paste installation commands and post-install Zabbix UI steps.
- Shared native frontend presentation helpers for section headings, descriptions, message boxes, technical fingerprints and human-readable reason labels.
- Context-aware native Zabbix empty states for catalog filters.

### Changed

- Completed a full user-facing UI/copy pass across catalog, comparison, update preparation/execution, installation preparation/review/result, preflight, rollback history/review/result and backup views.
- Standardized page titles, section names, confirmation language, status messages and write-boundary warnings.
- Internal snake-case reason/status codes are translated into operator-readable labels before display while remaining unchanged in service/API evidence.
- Commit, UUID and SHA-256 values use compact native monospace presentation with the complete value retained in the HTML title for inspection.
- Long engineering explanations were reduced to concise operator guidance without changing fail-closed behavior or controlled-write requirements.
- Repository hygiene policy now removes merged topic branches instead of retaining permanent per-beta release branches.
- Laboratory installation documentation no longer depends on stale `release/<version>` branches; exact commits or formal prerelease tags are used instead.

### Removed

- Obsolete empty source placeholders that were never referenced by runtime code.
- Superseded beta6/beta7 implementation-note documents that no longer represented the current architecture.

### Safety

- No comparison, backup, preflight, evidence, installation, update or rollback decision logic changed in the UI refactor.
- Configuration writes remain restricted to the existing controlled `configuration.import` boundary with fresh server-side preflight and explicit confirmation.
- The UI continues to use native Zabbix PHP components and `ZBX_STYLE_*` classes only; no custom CSS/UI framework was introduced.

### Tests

- Native UI guard now requires the shared presentation layer across every user-facing view and rejects direct per-view section/paragraph composition.
- Catalog tests require contextual `CTableInfo::setNoDataMessage()` behavior.
- Existing update/install/rollback write-boundary, security, CSRF and evidence contracts remain mandatory.

## [0.1.0-beta.49] - 2026-09-23

### Fixed

- Installed official templates at vendor version `<major>.<minor>-0` now resolve their BASE from an immutable initial-release UUID index generated from the official Zabbix `<major>.<minor>.0` tag.
- This fixes baseline failures caused by later upstream source-file restructuring that cannot be reconstructed reliably from the current file path alone. In particular, Zabbix 7.0 AWS templates that were separate YAML files in 7.0.0 and were later consolidated into `templates/cloud/AWS/aws_http/template_cloud_aws_http.yaml` now retain an authoritative historical BASE.
- The same fast path removes false `historical_baseline_unavailable`, `historical_baseline_ambiguous` and continuation-limit results for other unchanged initial-release templates when the exact official 7.0.0 UUID/content is available.
- If the immutable initial-release index is unavailable, ZTUM logs that condition and conservatively falls back to the existing request-bounded historical scan.

### Upstream data

- The upstream-index workflow now builds and publishes a second immutable index set under `upstream-index/initial/<line>.json`.
- Initial indexes are generated from official `7.0.0`, `7.2.0`, `7.4.0` and `8.0.0` tags.
- Each index preserves the historical UUID, vendor metadata, source path and raw-source SHA-256 at the initial release tag.

### Safety

- The initial-release baseline is accepted only when UUID, vendor name and vendor version exactly match the installed template.
- Historical raw bytes are fetched at the exact immutable release commit and verified against the SHA-256 stored in the generated baseline index.
- The initial-release result feeds the existing native `configuration.importcompare` and three-way analysis; it does not bypass local-customization detection or risk classification.
- If the UUID/version is absent from the initial release index, the normal fail-closed request-bounded historical workflow remains in force.
- Controlled execution still reruns a fresh authoritative preflight immediately before `configuration.import`.

### Tests

- Added initial-release endpoint validation.
- Added analysis-contract coverage requiring the immutable initial-release fast path before runtime history scanning.
- The generated initial indexes are validated with the same runtime decoder used for moving upstream indexes.

## [0.1.0-beta.48] - 2026-09-23

### Fixed

- Long historical-baseline discovery no longer needs to finish inside one proxy-long preparation request.
- Historical scans now have a bounded runtime budget and return the explicit retryable state `time_budget_reached` before starting another expensive revision fetch.
- Batch preparation automatically continues that same template across bounded requests until the historical baseline settles or the continuation safety limit is reached.
- Immutable historical raw sources are cached locally by exact commit/path and reused across later requests and templates that share the same upstream YAML file.
- Immutable commit-history results are cached locally by exact path/current-commit/scan-limit.
- Historical raw-source retrieval now prefers the official `zabbix/zabbix` GitHub mirror at the exact immutable commit and falls back to the canonical `git.zabbix.com` endpoint.
- Preparation HTTP failures now include bounded gateway diagnostics (elapsed time plus response `Server` and `CF-Ray` when present) to distinguish application failures from reverse-proxy/CDN failures.

### Performance / resilience

- This directly targets the field-observed `HTTP 504 after 30.2s` preparation failures on large shared template files such as the AWS HTTP template source.
- The AWS source path has a long official revision history; beta.48 avoids serially forcing the whole history through one HTTP request and lets later AWS templates reuse immutable revision bytes already fetched by the first candidate.
- Source/history transport timeouts are kept below the per-request historical budget so network stalls fail boundedly instead of consuming the full frontend/proxy window.

### Safety

- No historical revision is silently skipped and no baseline is guessed.
- Runtime caches are keyed by immutable commit/path identities; current-upstream bytes remain protected by the index SHA-256 fingerprint.
- Offline-only mode still refuses network fallback.
- A continuation state is non-writing and cannot become Ready by itself.
- Controlled execution still reruns authoritative fresh preflight immediately before `configuration.import`; warmed immutable history caches only reduce repeated network I/O.
- No Nginx, PHP-FPM or CDN timeout increase is required or recommended by this change.

### Tests

- Added request-budget regression coverage for historical baseline lookup.
- Added official-mirror URL coverage.
- Batch contracts require bounded historical continuation and gateway diagnostics.
- Existing CI, Security and single-write-boundary guards remain mandatory.

## [0.1.0-beta.47] - 2026-09-23

### Changed

- Bulk update selection now goes directly from the template catalog to request-bounded safety preparation.
- Removed the redundant `Selected template updates` scope-only review page and its `ztum.templates.review_selected` route/controller/view.
- The catalog action is now **Prepare selected updates** and posts the explicitly selected template IDs directly to `ztum.templates.prepare_selected`.
- Bulk update checkboxes are actionable only for Super Admin users, matching the permission required by batch preparation. Administrators still retain individual read-only comparison/review access.

### Safety

- No safety gate was removed: preparation still performs authoritative comparison, historical baseline, three-way analysis, risk evaluation, rollback verification and preparation evidence generation.
- Preparation remains non-writing.
- Controlled execution still requires explicit confirmation and reruns a complete authoritative fresh preflight immediately before each `configuration.import`.
- The 500-template selected-update sanity ceiling and one-template-per-HTTP-request preparation model remain unchanged.
- No additional configuration-write path or direct database write was introduced.

### Tests

- Reworked the selected-update action contract to require direct native `CActionButtonList` submission to `ztum.templates.prepare_selected`.
- Added regression checks that the obsolete review route/view/controller are absent and that users without bulk-prepare permission cannot receive actionable bulk-update checkboxes.
- Updated the laboratory test plan for the direct catalog-to-preparation workflow.

## [0.1.0-beta.46] - 2026-09-23

### Fixed

- Historical baseline retrieval is now source-path rename aware.
- The Bitbucket/Zabbix commit history may correctly follow file renames while immutable raw-source retrieval still requires the path that existed at the requested historical revision. ZTUM now resolves the source path from the preceding rename/move commit when the currently tracked path cannot be parsed/resolved at an older revision.
- Historical path fallback is lazy: the additional commit-changes request is made only after the tracked path fails, avoiding an extra network request for every historical revision.
- Historical read failures now include the immutable commit and attempted path in their bounded diagnostic instead of surfacing only a generic YAML parsing failure.

### Safety

- Rename handling does not skip unreadable revisions or guess an arbitrary historical source.
- Fallback is accepted only when the official commit changes metadata maps the tracked destination path to a different valid `templates/.../*.yaml` source path.
- If the rename cannot be proven, historical baseline resolution still fails closed and update readiness remains blocked.
- Offline-only mode never uses a network rename lookup and therefore preserves its fail-closed boundary when rename evidence is absent from the local bundle.
- Controlled update execution, rollback verification, immutable source fingerprints and the single approved `configuration.import` boundary are unchanged.

### Tests

- Added commit-changes URL/path decoding regressions.
- Added a historical-baseline regression covering an official template file rename where the older revision exists only at the pre-rename path.
- CI and Security validation remain mandatory before field testing.

## [0.1.0-beta.45] - 2026-09-23

### Fixed

- Request-bounded update preparation no longer repeats the complete template analysis merely to derive batch preflight evidence from a state that was already analyzed in the same HTTP request.
- After creating a rollback artifact, preparation now refreshes only the backup-verification/readiness state instead of repeating upstream retrieval, historical-baseline resolution, import comparison, three-way analysis and risk analysis.
- A template that already has verified rollback evidence now needs one complete analysis during preparation instead of a second complete analysis solely for the preparation evidence fingerprint.
- Slow single-template preparation requests now record bounded elapsed-time diagnostics in the frontend/PHP error log.

### Performance / resilience

- The change directly targets the field-observed per-template HTTP 504 class around 30 seconds without relaxing any safety gate.
- Batch preparation still processes one template per HTTP request and remains safe to retry manually at the preparation layer.
- Historical/source analysis itself is unchanged; beta.45 therefore requires field validation against cold and warm caches before the timeout blocker can be considered fully resolved.

### Safety

- Reusing an analysis snapshot is limited to non-writing batch preparation. The only state intentionally changed during that preparation step is the local rollback artifact.
- Rollback verification still performs a fresh current-template export and SHA-256 equality check after the backup is created.
- Controlled update execution still reruns a complete authoritative preflight immediately before `configuration.import`, validates the posted evidence against that fresh state and preserves the single approved write boundary.
- No automatic retry, timeout increase, direct database write or additional configuration-import path was introduced.

### Tests

- Added regression coverage proving verified batch preparation does not rerun complete analysis only to derive preparation evidence.
- Added regression coverage proving backup creation/verification does not trigger a second full analysis.
- Existing controlled-write, native-UI, security and unit-test gates remain mandatory.

## [0.1.0-beta.44] - 2026-09-23

### Fixed

- Post-install validation is now aligned with the create-only installation contract introduced in beta.42.
- A successful missing-template installation no longer fails validation solely because an already existing shared `template_group` or `host_group` differs from the official source. The create-only write profile intentionally does not update those pre-existing shared objects.
- Template-owned differences remain authoritative: items, discovery rules, triggers, graphs, dashboards, HTTP tests, value maps, template identity/version and any other non-shared-group difference still fail closed.
- Batch installation now surfaces the actual post-install validation reasons and remaining/raw difference counts instead of only `post_install_validation_failed`.
- Individual installation results now show effective differences, raw differences and the count of intentionally ignored shared-group differences.

### Safety

- Standard update validation is unchanged and still requires an exact zero-difference current-upstream result.
- The post-install exception is narrow and explicit: only `host_groups` and `template_groups` may be excluded, and only when all remaining template-owned content validates exactly.
- Raw comparison status/counts remain available in the validation result for diagnostics.
- No retry, automatic uninstall, additional configuration-write path or direct database write was introduced.

### Tests

- Added regression coverage for a create-only installation whose only remaining comparison changes are shared groups.
- Added a negative regression proving any non-group difference still fails post-install validation.
- Added UI/action contract coverage for detailed post-install validation diagnostics.

## [0.1.0-beta.43] - 2026-09-23

### Fixed

- Critical frontend integration bug: generic module view names such as `template.list` could override native Zabbix core views because enabled module view directories are registered ahead of the core view directories.
- Opening the native Zabbix `Data collection → Templates` page (`action=template.list`) could therefore render the Template Update Manager's incompatible `template.list.php` and return HTTP 500.
- All Template Update Manager views are now namespaced with the `ztum.` prefix in both filenames and manifest view names.

### Safety / compatibility

- Module action names remain unchanged (`ztum.*`); only internal view identifiers were namespaced.
- Native Zabbix actions such as `template.list` are no longer shadowed by module view files.
- No controlled-write behavior, evidence gate or Zabbix configuration data path was changed.

### Tests

- Added a permanent module-view namespace guard: every registered module view and every PHP file below `views/` must begin with `ztum.`.
- Updated manifest/native-UI validation to use the namespaced view filenames.
- The guard is part of CI and the formal release validation path.


## [0.1.0-beta.42] - 2026-09-23

### Fixed

- Missing-template installation now uses a dedicated create-only import rule profile for both `configuration.importcompare` and the controlled `configuration.import` write boundary.
- Installation no longer asks Zabbix to update existing template groups, templates, items, discovery rules, triggers, graphs, HTTP tests, value maps or dashboards while creating a missing official template.
- Batch installation no longer labels a Zabbix import rejection as a browser/request failure. Import-stage rejection, request outcome uncertainty and post-install validation failure are shown as distinct states.
- The batch Reason column now receives the import failure detail and bounded read-only post-failure inspection state.
- Individual installation results now distinguish import attempted, confirmed configuration write and uncertain write outcome.
- Structural installation auditing now validates self trigger item references, graph item references and graph Y-axis item references in addition to host references.

### Safety

- The create-only installation profile still allows genuinely missing template/host groups required by the selected official source, but it never updates an existing group merely because it appears in the import file.
- The selected rule profile is bound into fresh installation evidence and is revalidated immediately before the write.
- An import rejection remains stop-on-first-failure. ZTUM does not retry the failed candidate and does not continue the batch automatically.
- When an import request does not return confirmed success, the write outcome is reported as uncertain even if the target UUID is absent after a read-only inspection.
- The single `TemplateConfigurationImportService` write boundary remains unchanged.

### Tests

- Added create-only import-rule profile assertions.
- Added structured import-failure/result-view contracts.
- Added trigger-item and graph-item structural reference regression coverage.
- Extended expression parsing tests to preserve host/item pairs across arithmetic division expressions.


## [0.1.0-beta.41] - 2026-09-23

### Fixed

- Corrected trigger-host extraction used by installation isolation/reference auditing. Arithmetic division such as `min(/Template/key,5m)/last(/Template/other.key)` could previously be misread as a cross-template dependency named `last(`.
- Jira Data Center by JMX and Vyatta Virtual Router by SNMP no longer receive the false `Required dependencies = last(` / `Missing dependencies = last(` classification from that parser defect.
- Runtime trigger-host extraction now prefers Zabbix's native `CExpressionParser`/expression-result host semantics; standalone tests use a narrow grammar-aware fallback.
- Catalog pagination no longer shows the custom `All` / `Pages` display control when the complete filtered result fits on a single native Zabbix page.

### Safety

- Real cross-template trigger references remain dependency-aware and fail closed when the referenced template is actually missing.
- The change does not weaken `configuration.importcompare`, installation reference auditing, immutable evidence or controlled-write gates.

### Tests

- Added direct Jira/Vyatta-style arithmetic-division expression regressions.
- Added isolation and installation-reference-audit regressions proving `last(` cannot become a synthetic template dependency.
- Added catalog controller contract coverage for suppressing unnecessary single-page display controls.


## [0.1.0-beta.40] - 2026-09-23

### Added

- Verified air-gapped/offline upstream bundle support with SHA-256 manifest enforcement and fail-closed `ZTUM_OFFLINE_ONLY` behavior.
- `tools/build_offline_bundle.py` plus deterministic generator/repository tests.
- Global controlled-operation filesystem lock covering individual/batch update, install and rollback write controllers.
- Explicit individual local-customization-overwrite acknowledgement before controlled import.
- `tools/ztum-runtime-setup.sh` for fail-closed runtime directory creation/validation and PHP-FPM account handling.
- Visible native-Zabbix laboratory warning on the module catalog.
- Dedicated security workflow, deterministic runtime security guard and dependency audit.
- Formal tag/release workflow producing tar/zip assets and SHA-256 checksums.
- Xdebug-backed transparent PHP coverage metrics and separate runtime-file reachability metric.
- GitHub issue/field-validation/feature-request templates and pull-request safety checklist.
- GitHub workflow YAML/action-pin validation.
- Offline, concurrency, runtime-setup, test-metrics and release-policy documentation.

### Changed

- Runtime diagnostics now report offline-bundle/offline-only state.
- Documentation is synchronized with the implemented controlled-write architecture rather than describing the former read-only phase.
- Release policy now distinguishes development commits from field-test beta snapshots instead of treating every internal change as a public beta.
- Controlled write workflows are serialized before their fresh authoritative preflight and remain locked through post-operation validation.

### Safety

- Offline bundle data is used only after manifest SHA-256 verification and the existing index/source identity validation.
- Offline-only mode never silently falls back to internet access.
- Concurrent controlled writes fail closed before starting a second authoritative write workflow.
- Global locking is defense in depth; stale-evidence/preflight gates remain authoritative.
- The existing single `TemplateConfigurationImportService` write boundary is unchanged.
- No automatic retry, rollback or uninstall was introduced after ambiguous writes.

### Tests

- Added operation-lock service and action-contract regression coverage.
- Added offline bundle repository/generator integrity and tamper tests.
- Added runtime setup helper validation.
- Added workflow YAML/action-pin validation and runtime security guard coverage.
- CI and Security workflows are green on the beta.40 preparation branch before snapshot promotion.


## [0.1.0-beta.39] - 2026-09-22

### Changed

- Refactored user-facing views toward the native Zabbix frontend visual language.
- Moved secondary/back navigation into `CHtmlPage::setControls()` instead of rendering ad-hoc navigation inside page content.
- Reviewed batch row selection is now server-rendered with native Zabbix `CCheckBox` controls; JavaScript only changes enabled/checked state.
- Batch JavaScript now initializes through `CScriptTag::setOnDocumentReady()`.
- Added native semantic status styling backed only by Zabbix `ZBX_STYLE_*` classes for success, review/warning, blocked/error, running/info and neutral states.
- Catalog metadata now uses native horizontal-list presentation.
- Removed empty legacy action/view/asset stubs that were not registered or used.
- Rewrote stale architecture/roadmap sections to match the implemented update/install/rollback engines.

### Added

- `src/Support/FrontendUi.php` as a small native-status presentation helper.
- `docs/ui-style.md` defining the module's native Zabbix UI contract.
- `tests/ui_native_guard.php` and CI enforcement for native controls, theme-safe styling and batch document-ready lifecycle.
- Beta.39 light/dark and Zabbix 7.x/8.x visual regression checklist.

### Fixed

- Eliminated dynamically created raw HTML reviewed-update checkboxes, the main source of inconsistent checkbox appearance/behavior compared with built-in Zabbix controls.
- Removed the raw text separator used between update-result navigation links.
- User-visible status text now has consistent native semantic styling while retaining explicit text so meaning never depends on color alone.

### Safety

- This is a presentation/composition refactor; the controlled-write security model is unchanged.
- UI state remains non-authoritative. Permissions, fresh preflight, evidence comparison, reviewed acknowledgements and the single approved `configuration.import` boundary remain server-side requirements.
- No third-party UI framework or hard-coded theme color was added.

### Tests

- Added a repository-level native UI guard.
- Strengthened batch action contracts to require native reviewed `CCheckBox` controls, native status constants and document-ready initialization.
- Existing PHP syntax, manifest/version, controlled-write, unit/contract and upstream-index validation remain required.

## [0.1.0-beta.38] - 2026-09-22

### Changed

- Renamed the Zabbix frontend module display name from `Zabbix Template Update Manager` to `Template Update Manager`.
- Kept the existing module ID, namespace, action names, repository name and `ZTUM` acronym unchanged to avoid breaking installed-module identity or routes.

## [0.1.0-beta.37] - 2026-09-22

### Fixed

- Controlled update analysis no longer fails immediately on official templates containing cross-template trigger/graph/dashboard references. Update comparison now uses dependency-aware isolation and lets Zabbix `configuration.importcompare` validate those references against the current local environment.
- Historical baseline isolation can preserve the same external references, preventing false unresolved results caused only by strict source isolation.
- Immutable controlled-update candidate reconstruction now preserves and verifies the exact external-template dependency set bound by preflight evidence.
- Batch preparation now displays the actual sanitized comparison diagnostic instead of only `comparison_error`.
- Reviewed selection controls now always show explicit counts, e.g. `Select all eligible (0)`, and an empty select-all is disabled once preparation is complete.

### Safety

- Dependency-aware update isolation does not auto-install or invent dependencies. If Zabbix importcompare cannot resolve an external reference, comparison still fails closed and the diagnostic is now visible.
- The external dependency-name set is included in the update preflight evidence and must match again when the immutable candidate is rebuilt immediately before import.
- Fresh preflight, rollback verification, reviewed acknowledgements, request-bounded execution and the single approved write boundary remain unchanged.

### Field finding

- Check Point Next Generation Firewall by SNMP, Cisco SD-WAN device by HTTP, Elasticsearch Cluster by HTTP and Generic Java JMX all reached `blocked_unresolved / comparison_error` in beta.36, leaving zero selectable reviewed rows. Beta.37 removes the strict-isolation false blocker where cross-template references are the cause and exposes the exact diagnostic for any remaining failure.

### Tests

- Added regression coverage for dependency-aware historical isolation.
- Added controlled-update candidate coverage proving the preflight-bound external dependency set is preserved and mismatch fails closed.
- Added batch-plan coverage for surfaced comparison diagnostics.
- Updated reviewed-selection contracts for explicit eligible/selected counts.

## [0.1.0-beta.36] - 2026-09-22

### Fixed

- Replaced the Zabbix-rendered header select-all checkbox, which remained visually/behaviorally unreliable in field testing, with explicit `Select all eligible` and `Clear reviewed selection` controls.
- Per-row reviewed checkboxes remain in the first Include column.
- The execution status now explicitly states that writes remain locked until the full selected preparation set completes, while reviewed selections may be made during preparation.
- Installation preflight no longer blocks a template immediately on `cross_template_trigger_dependency` or `cross_template_graph_dependency` when those references can be represented as external template dependencies.

### Changed

- Select-all remains sticky during preparation: later reviewed-eligible rows inherit the selection until the operator clears or manually deselects.
- Cross-template trigger/graph/dashboard host names discovered during installation isolation are surfaced as required template dependencies.
- If those external templates are already installed, installation preflight may continue; if they are missing, the candidate now fails as `missing_template_dependencies` with actionable Required/Missing dependency names instead of a generic isolation block.
- Strict isolation used by update/historical paths is unchanged and still fails closed on cross-template definitions.

### Safety

- Update execution still waits until every selected template finishes preparation, preventing concurrent preparation and configuration writes.
- Local-overwrite reviewed updates still require the additional explicit overwrite acknowledgement.
- Installation remains non-recursive: external dependencies must already be installed locally before the dependent template can become Ready.
- Conflict, unresolved, request-failed and unknown reviewed states remain non-executable.

### Field findings

- A beta.35 update run at 24/26 completed showed valid reviewed row checkboxes but the header checkbox still appeared unusable, while execution was correctly waiting for the remaining preparation requests.
- Vyatta Virtual Router by SNMP installation was blocked as `cross_template_trigger_dependency`; beta.36 converts that structural relationship into an explicit dependency when possible.

### Tests

- Updated batch action contracts for explicit select-all/clear controls and preparation-wait messaging.
- Added dependency-service coverage for structural external dependencies.
- Added installation-isolation regression coverage proving strict update isolation stays fail-closed while install-mode isolation preserves mixed-host graphs/triggers and reports external dependency names.

## [0.1.0-beta.35] - 2026-09-22

### Fixed

- Manual review rows whose reason includes `local_customization_overwrite` no longer make the reviewed select-all appear broken by exposing no selectable rows.
- Verified local-overwrite Manual review candidates can now be explicitly included in the request-bounded reviewed batch.

### Added

- Separate `batch_manual_requires_local_overwrite_ack` evidence flag produced during preparation.
- Additional explicit acknowledgement: `I explicitly accept overwriting local customizations for the selected templates.`
- Server-side enforcement in `TemplateControlledUpdateService`: a local-overwrite reviewed batch request without that acknowledgement is blocked before `configuration.import`.
- Execution state distinguishes ordinary `Reviewed batch eligible` from `Reviewed overwrite eligible`.

### Safety

- Local-overwrite rows still remain Manual review; they are never promoted to unattended Ready.
- They require verified rollback evidence, successful manual-mode preflight, bound SHA-256 evidence, explicit row/select-all selection, the normal reviewed acknowledgement and the additional local-overwrite acknowledgement.
- Unknown manual reasons, Conflict, Blocked, unresolved and request-failed rows remain non-executable.
- Fresh preflight, evidence revalidation, request-bounded execution, stop-on-first-failure and the single approved write boundary remain unchanged.

### Field finding

- A completed 34-template Zabbix 7.x preparation contained 7 Manual review and 27 Blocked rows, with the visible Manual review set dominated by `local_customization_overwrite, high_technical_risk`. Because beta.34 intentionally excluded local-overwrite rows, the header select-all had no eligible rows to act on and appeared broken to the operator.

### Tests

- Updated batch-plan coverage to prove local-overwrite reviewed candidates receive manual evidence plus a mandatory overwrite-ack flag.
- Added negative coverage for unknown manual reasons.
- Added controlled-update coverage proving a missing local-overwrite acknowledgement blocks before import.
- Added batch action/UI contracts for the second acknowledgement and transport of `confirm_local_overwrite`.

## [0.1.0-beta.34] - 2026-09-22

### Fixed

- The reviewed select-all checkbox is no longer disabled simply because no eligible reviewed row has completed preparation yet.
- Select-all can now be checked while a long batch is still preparing; eligible reviewed rows discovered later are automatically selected.
- Header checked/unchecked/indeterminate state remains synchronized with individual reviewed selections.

### Safety

- Sticky select-all applies only to rows that later satisfy the same reviewed-batch eligibility gates: verified rollback, passing reviewed preflight and technical-risk-only reasons.
- `local_customization_overwrite`, Conflict, Blocked, unresolved and request-failed rows remain excluded even when select-all is enabled.
- Global acknowledgement, fresh preflight, evidence binding and stop-on-first-failure are unchanged.

### Field finding

- A 50-template preparation run showed the beta.33 header checkbox disabled while preparation was still at 25/50 because all completed Manual review rows were individual-review-only at that point. Beta.34 makes the operator's select-all intent persistent across the remainder of preparation.

### Tests

- Added regression coverage that select-all is clickable before any eligible reviewed row exists and that later eligible rows inherit the selection automatically.

## [0.1.0-beta.33] - 2026-09-22

### Changed

- Moved the reviewed-override checkbox from the Execution column to a dedicated leading Include column so large batches are easier to scan and select.
- Added a header select-all checkbox that selects/deselects every currently eligible reviewed override in one action.
- The select-all control tracks checked/unchecked/indeterminate state as individual reviewed rows are changed.
- The Execution column now focuses on outcome/workflow state: `Reviewed batch eligible`, `Individual review required`, `Updated and validated`, `Blocked`, and `Review details`.

### Safety

- Select-all applies only to reviewed rows that already have verified rollback evidence, approved technical-risk-only reasons and valid reviewed-preflight evidence.
- Rows containing `local_customization_overwrite` remain unselectable for batch override and still show `Individual review required`.
- Conflict, Blocked, unresolved and request-failed rows remain unselectable.
- Global confirmation, fresh reviewed preflight, bound SHA-256 evidence and stop-on-first-failure remain unchanged.

### Field finding

- A 50-template Zabbix 7.x preparation run produced 1 Ready, 35 Manual review and 14 Blocked candidates. Technical-risk-only reviewed rows updated successfully through the reviewed batch path, while several templates with local customization correctly remained individual-review-only.
- Multiple AWS templates also exposed isolated per-template HTTP 504 preparation failures near 30 seconds; those remain handled by the existing request-failure retry/diagnostic path.

### Tests

- Added contract coverage for the leading reviewed-selection column, select-all behavior and indeterminate-state synchronization.

## [0.1.0-beta.32] - 2026-09-22

### Added

- Eligible Manual review rows now expose an explicit per-row `Include reviewed update` checkbox directly in batch preparation.
- Technical-risk-only reviewed candidates receive a separate reviewed-preflight SHA-256 evidence fingerprint during preparation; this evidence is never confused with normal Ready evidence.
- Ready candidates and explicitly selected reviewed candidates are merged into one ordered, request-bounded execution queue.

### Changed

- The main confirmation now explicitly acknowledges Manual review reasons for any reviewed rows selected by the operator.
- The batch execution button is now `Update eligible templates` because the queue can contain normal Ready rows plus explicitly selected reviewed overrides.
- Review-only plans no longer force individual navigation for ordinary technical-risk-only cases; `Review details` remains available for inspection.

### Safety

- Manual review is not auto-approved. Each reviewed row must be explicitly selected, and the batch confirmation must also be checked.
- Reviewed execution sends both `manual_override=1` and `confirm_manual_override=1`, then reruns fresh manual-mode preflight immediately before import and verifies the bound evidence fingerprint.
- Only `medium_technical_risk` and `high_technical_risk` reasons are eligible for batch reviewed override.
- Any `local_customization_overwrite`, Conflict, unresolved state or non-approved manual reason remains individual-review only.
- The single approved `configuration.import` boundary and stop-on-first-failure semantics are unchanged.

### Tests

- Added service coverage for reviewed evidence generation and separate manual evidence storage.
- Added negative coverage proving local-customization overwrite cannot receive a reviewed batch checkbox/evidence.
- Added UI/action contracts for per-row reviewed selection, dual acknowledgement and the combined ordered execution queue.

## [0.1.0-beta.31] - 2026-09-22

### Changed

- Removed the former 25-template ceiling for controlled update preparation.
- Selected update preparation can now use the full existing selected-template safety ceiling of 500 candidates.
- The selected-review page no longer blocks Super Admin batch preparation merely because more than 25 templates were selected.

### Safety

- This does not restore a long-running multi-template request. Preparation still runs one template per HTTP request and Ready execution still runs one template per HTTP request.
- The existing 500-template selection ceiling remains as an anti-abuse/sanity boundary.
- Stop-after-current-template, request-failure retry, Manual review separation, bound SHA-256 evidence, fresh preflight, stop-on-first-failure and the single approved configuration-write boundary remain unchanged.

### Tests

- Added regression coverage proving a 26-template update plan is accepted and represented completely, preventing reintroduction of the old 25-template ceiling.
- Updated selected-review contracts to reject the obsolete `BATCH_PREPARE_LIMIT` and its limit-warning UI.

## [0.1.0-beta.30] - 2026-09-22

### Fixed

- Batch preparation no longer leaves Manual review candidates in a dead-end-looking state. Each Manual review row now exposes a direct `Review and update` link to the existing individual comparison flow.
- Zero-Ready plans that contain Manual review candidates now explain that unattended batch execution is unavailable and direct the operator to the per-row reviewed path instead of only stating that no Ready templates exist.

### Changed

- The Execution column now acts as a workflow continuation point: Ready rows remain executable through the request-bounded batch path, Manual review rows navigate to detailed comparison/reviewed preflight, and Conflict/Blocked rows remain non-executable.
- The reviewed path itself is unchanged: authoritative comparison, verified rollback evidence, fresh reviewed preflight and a second explicit Super Admin acknowledgement remain mandatory before import.

### Safety

- High technical risk is still not promoted to unattended Ready. Beta.30 improves navigation only and does not relax the risk classifier or readiness gate.
- Manual review links target the existing read-only comparison controller; no configuration write occurs from the batch page.
- The single approved `configuration.import` boundary remains unchanged.

### Field finding

- Zabbix 7.x beta.29 field validation showed Aranet Cloud, Asterisk by HTTP and AWS by HTTP correctly reaching `review_backup_verified` with `high_technical_risk`, but the batch page gave no obvious continuation action. Beta.30 makes that required manual path explicit.

### Tests

- Added batch-view contract coverage for the direct Manual review comparison link and the review-only zero-Ready guidance.

## [0.1.0-beta.29] - 2026-09-22

### Fixed

- Completed update-preparation plans with `Ready = 0` now synchronize the execution summary status to `Unavailable — no Ready templates` instead of leaving the lower status table at `Waiting for preparation`.
- Per-template preparation transport failures no longer require restarting the entire selected batch just to retry the failed row.

### Added

- Explicit `Retry failed preparation` control, enabled only after preparation completes and only when one or more request-level preparation failures exist.
- Request-failure tracking that safely removes the old Blocked count before retrying and reclassifies the row from the fresh result.
- Elapsed request timing in HTTP preparation errors, e.g. `HTTP 504 after 100.1s`, to distinguish repeatable proxy deadlines from short transient failures.

### Safety

- Retry remains preparation-only: it may refresh rollback evidence but never imports Zabbix configuration.
- Retry is manual, sequential and limited to rows that failed at the HTTP/request layer; Manual review, Conflict and normal Blocked analysis states are not retried automatically.
- Controlled update execution, fresh preflight, evidence binding, stop-on-first-failure and the single approved configuration-write boundary are unchanged.

### Field finding

- Zabbix 7.x beta.28 field test updated and validated 9 Ready APC templates sequentially with 0 failures and no cumulative Cloudflare 504.
- A later VMware preparation set produced 4 Manual review candidates plus one isolated `HTTP 504` preparation failure, motivating explicit retry/timing diagnostics and the zero-Ready status correction.

### Tests

- Added contract coverage for synchronized zero-Ready execution status, explicit preparation retry state and elapsed HTTP-failure timing.

## [0.1.0-beta.28] - 2026-09-22

### Fixed

- Multi-template update execution no longer keeps the whole Ready set inside one synchronous frontend request. Each Ready template now executes in its own request, preventing cumulative batch runtime from triggering reverse-proxy/Cloudflare 504 timeouts.
- A request failure stops client-side sequencing immediately and marks every later Ready template as Not attempted instead of continuing into an ambiguous state.
- The legacy synchronous `ztum.templates.batch_update` endpoint now fails fast for selections larger than one template.

### Changed

- Controlled update execution now mirrors the existing request-bounded installation architecture: browser-side sequential orchestration, per-template CSRF-protected JSON execution, visible per-row execution state and aggregate Updated/Failed/Not attempted/write counters.
- The execution page stays in place while the queue runs; successful templates report version/validation inline.

### Safety

- Every per-template request still delegates to `TemplateControlledUpdateService`, which reruns fresh authoritative preflight, verifies bound evidence, reconstructs the immutable candidate and uses the single approved configuration-import boundary.
- Execution still stops on the first non-success. No automatic rollback or blind retry was added.
- A transport timeout for one individual template remains an ambiguous state and must be inspected before retrying; request-bounding removes only cumulative multi-template timeout exposure.

### Tests

- Added action-contract coverage for the request-bounded update route, per-template evidence/confirmation, sequential browser queue, stop-on-first-failure behavior and legacy multi-template fail-fast protection.

## [0.1.0-beta.27] - 2026-09-22

### Fixed

- Update-batch preparation now derives executable Ready state from candidates that actually carry a valid bound SHA-256 preflight evidence value, preventing a visual Ready / disabled-confirmation divergence.
- A server-reported Ready row without valid evidence is reclassified client-side as Blocked with `invalid_preflight_evidence` and is never submitted.
- Mixed batch plans can enable confirmation when at least one evidence-backed Ready candidate exists; Manual review, Conflict and Blocked rows remain outside the execution set.

### Changed

- The controlled sequential execution section now reports an explicit state: waiting for preparation, available with the exact Ready count, unavailable with zero Ready candidates, or unavailable because preparation was stopped.
- The execution explanation now states that only Ready candidates with valid bound preflight evidence are submitted.

### Safety

- The server-side `TemplateBatchPlanService` evidence gate remains authoritative; the browser now mirrors the same fail-closed invariant instead of maintaining a looser visual Ready state.
- Manual-review candidates may still receive rollback evidence during preparation, but they never receive batch execution evidence and cannot enter unattended sequential execution.
- Conflict, unresolved, stale-evidence and stop-on-first-failure protections remain unchanged.

### Tests

- Added browser contract coverage for evidence-gated Ready classification, mixed-plan enablement and explicit execution-state UX.
- Extended batch-plan tests to prove Manual review, Conflict and Blocked rows never carry execution evidence.
- Existing batch-update tests continue to cover ordered execution, invalid evidence and stop-on-first-failure with Not attempted reporting.

## [0.1.0-beta.26] - 2026-09-22

### Added

- Read-only structural reference audit in controlled template-install preflight.
- Static checks for value-map references, dependent-item master keys, dashboard ITEM references, trigger-expression hosts and graph-item hosts.
- Structural audit counts are bound into installation preflight evidence and shown in individual installation review.
- Bounded structural-reference issue details are propagated into batch preparation.

### Changed

- A completed batch plan with zero Ready candidates now explicitly reports `Unavailable — no Ready templates` instead of leaving the execution area at `Waiting for preparation`.
- Zero-Ready plans explain that all selected candidates were blocked and direct the operator to the reasons above or back to the catalog.
- Proven unresolved structural references fail closed as `blocked_references` before `configuration.importcompare` / `configuration.import`.

### Safety

- The reference audit is read-only and only blocks relationships that can be proven unresolved from the isolated template plus already-validated linked-template names.
- Existing dependency, isolation, creation-only preview, evidence, CSRF, Super Admin and single-write-boundary protections remain unchanged.

### Tests

- Added structural-reference audit coverage for master items, value maps, dashboards, trigger hosts and graph hosts.
- Added contracts for zero-Ready batch UX and structural-reference preflight gating.
- Added batch-plan coverage for reference-audit blockers.

## [0.1.0-beta.25] - 2026-09-22

### Fixed

- Preserve native Zabbix frontend API error messages when `configuration.import` returns `false` instead of replacing the real cause with a generic import failure.
- Request-bounded batch-install rows now display the underlying controlled import failure detail inline for Super Admins.
- Known cross-template isolation hazards are classified as explicit blocked states rather than generic `analysis_exception` rows.

### Changed

- Cross-template top-level trigger, graph and dashboard dependencies now carry stable reason codes.
- Isolation-blocked batch rows retain the official candidate name/version, improving operator review.

### Field findings

- Beta.24 full-catalog test: 37 selected, 32 Ready, 5 Blocked; 30 installed and validated before the first execution failure; 1 failed and 1 remained Not attempted.
- `Jira Data Center by JMX` and `Vyatta Virtual Router by SNMP` were confirmed as deterministic cross-template top-level trigger isolation blocks.
- `VeloCloud SD-WAN Edge by HTTP` reached the write boundary but the frontend API returned failure; beta.25 now surfaces the native Zabbix reason required to diagnose it.

### Tests

- Added explicit isolation reason-code assertions.
- Added contracts requiring native `CMessageHelper` import diagnostics and inline request-bounded failure detail.

## [0.1.0-beta.24] - 2026-09-22

### Fixed

- The `Not installed` header select-all checkbox is active again and can select the full current missing-template set.

### Changed

- Missing-template batch selection ceiling increased from 25 to 500, which covers the complete current official Zabbix catalog while remaining bounded.
- Controlled multi-template installation execution is now request-bounded: each Ready template is imported and validated in its own HTTP request.
- Removed the legacy single-request batch-install execution route to avoid large batches recreating proxy/Cloudflare timeout risk.
- The preparation page now keeps execution results inline, including Installed / Failed / Not attempted / Any configuration write and per-template execution state.

### Safety

- Every request-bounded write still goes through `TemplateControlledInstallService`, which reruns fresh install preflight/evidence immediately before the single approved `configuration.import` boundary.
- Sequential order and stop-on-first-failure semantics are preserved across browser-driven requests.
- No automatic uninstall is performed after a failed or ambiguous installation request.

### Tests

- Updated catalog contract to require active select-all.
- Added route/action contracts requiring request-bounded execution with native CSRF and Super Admin authorization.
- Removed tests and manifest expectations for the legacy long-running batch-install endpoint.

## [0.1.0-beta.23] - 2026-09-22

### Changed

- The catalog selection column now always renders a checkbox instead of leaving ineligible rows visually blank.
- Ineligible rows render a disabled checkbox with a reason tooltip.
- The select-all checkbox is always visible in the table header.
- For `Not installed`, select-all is disabled when the filtered result exceeds the bounded batch-install limit of 25 templates; individual eligible checkboxes remain available.

### Tests

- Extended catalog UI contracts to require visible disabled row checkboxes and a persistent select-all control with bounded-limit disabling.

## [0.1.0-beta.22] - 2026-09-22

### Added

- Multi-select controlled installation for official catalog templates in the `Not installed` filter.
- Request-bounded per-template installation preparation with `Ready` / `Blocked` classification.
- Sequential Ready-only installation execution with stop-on-first-failure behavior.
- Batch installation results showing installed, failed, not-attempted and any-write state.

### Safety

- Every selected candidate reuses the existing `TemplateInstallPreflightService`; only `passed` candidates with bound SHA-256 evidence become Ready.
- Every write reuses `TemplateControlledInstallService`, which reruns fresh preflight/evidence immediately before the existing single `configuration.import` boundary.
- Missing dependencies, collisions and unsafe import previews remain Blocked and never enter the execution set.
- No recursive dependency installation is performed in beta.22, including when a missing dependency is also selected.
- Batch execution stops at the first non-success/exception and never performs automatic uninstall.
- Maximum selected installation batch size is 25.

### Tests

- Added multi-template install plan classification coverage.
- Added sequential batch install success, invalid-evidence and stop-on-first-failure coverage.
- Added controller/view/manifest safety contracts for CSRF, Super Admin access, request-bounded preparation and single-write-boundary preservation.

## [0.1.0-beta.21] - 2026-09-22

### Added

- Native Zabbix status filter for the template catalog with `All`, `Current`, `Not applicable`, `Update available` and `Not installed` choices.
- `All` control in the catalog pager to show every matching row, with a `Pages` control to return to normal pagination.

### Changed

- Catalog status selection is persisted with Zabbix `CProfile` and applied before pagination.
- Filter UI uses native `CFilter` + modern `CRadioButtonList` controls.

### Tests

- Extended catalog controller/view contracts to cover native filter controls, persisted status choices and reversible All/Pages pager mode.

## [0.1.0-beta.20] - 2026-09-22

### Fixed

- Fixed an HTTP 500 on the expanded template catalog introduced in beta.19.
- `TemplateList` now explicitly imports the native global Zabbix `CPagerHelper` and `CUrl` classes before using catalog pagination inside the module namespace.

### Tests

- Added a controller namespace contract that requires the native pager/URL imports and preserves the `CPagerHelper` pagination wiring.

## [0.1.0-beta.19] - 2026-09-22

### Added

- Official upstream catalog entries that are absent locally now appear as `Not installed` instead of being invisible.
- Added a separate `Review installation` workflow for upstream-only official templates.
- Added fail-closed install preflight with immutable commit/path/raw-source/content/import fingerprints.
- Added linked-template dependency discovery; missing dependencies block installation and are listed for the operator.
- Added local UUID/technical-name collision checks.
- Added creation-only `configuration.importcompare` gating: installation refuses previews that would update/remove existing configuration or contain unresolved identity.
- Added explicit super-administrator controlled installation using the existing single `TemplateConfigurationImportService` write boundary.
- Added post-install validation proving installed UUID/version/current-upstream equality and zero remaining differences.

### Changed

- Template inventory is now merged with the validated official upstream index to form an official catalog.
- The expanded catalog uses native Zabbix `CPagerHelper` pagination and the configured Zabbix search/page limit.
- Update and installation remain separate workflows; upstream-only templates are not batch-selected as updates.

### Safety

- Installation is individual in beta.19; recursive dependency installation and batch installation are intentionally not implemented.
- No prior rollback artifact exists for a template that was absent before installation. Failed post-install validation never triggers automatic uninstall.
- The write action reruns the complete installation preflight and rejects changed evidence immediately before `configuration.import`.
- Cross-template isolation protections already used for official update sources remain active for installation.

### Tests

- Added upstream-catalog merge coverage.
- Added nested linked-template dependency coverage.
- Added creation-only install-preview gate coverage.
- Added installation action/write-boundary contracts and not-installed version-state coverage.

## [0.1.0-beta.18] - 2026-09-22

### Fixed

- Risk classification now separates visible technical impact from eligibility for the standard controlled path, reducing false `high_technical_risk`/manual-only outcomes without weakening conflict or local-overwrite gates.
- Bounded preprocessing maintenance that only adds/removes/changes `DISCARD_UNCHANGED` or `DISCARD_UNCHANGED_HEARTBEAT` steps is classified as `medium` technical impact and may remain standard-path eligible when three-way evidence is complete and clean.
- Functional preprocessing changes such as JavaScript, regex or other transformations remain `high` and manual-only.

### Safety

- Key/type/value-type/master-item/SNMP-OID changes, trigger expression/dependency changes, functional entity removals, local overwrites, conflicts and unresolved evidence remain outside the standard path.
- Medium risk remains manual by default. Only an explicitly recognized bounded-medium change with `standard_path_eligible=true` may advance through the normal backup/preflight gates.
- A known-medium candidate still requires a verified rollback artifact and a fresh preflight before it can become batch `Ready`.

### Tests

- Added a regression modeled on `APC UPS Symmetra RM by SNMP` 7.0-3 -> 7.0-4, where the official update removes only `DISCARD_UNCHANGED_HEARTBEAT 6h` from one status item.
- Added regressions proving unchanged JavaScript preprocessing may coexist with the bounded delta, while changed JavaScript remains high/manual.
- Added readiness coverage proving standard-path-eligible medium impact still passes through backup verification and controlled preflight rather than bypassing safety gates.

## [0.1.0-beta.17] - 2026-09-22

### Fixed

- Per-template batch preparation now uses a dedicated module action explicitly registered with `layout.json` and `view: null`.
- This fixes the field failure where `disableView()` still inherited the default module `layout.htmlpage`, causing the browser to receive `<!DOCTYPE ...>` around `main_block` instead of JSON.

### Changed

- Restored `ztum.templates.prepare_one` as the bounded one-template preparation endpoint, now with the correct JSON layout contract.
- `ztum.templates.prepare_selected` remains HTML-only and renders the queue shell.

### Safety

- Both preparation actions remain super-administrator-only with native CSRF validation.
- Heavy preparation remains one candidate per request.
- No Zabbix configuration-write boundary changed.

### Tests

- Manifest validation now asserts the JSON layout and null view for `ztum.templates.prepare_one`.
- Batch action contracts require the dedicated JSON route and continue enforcing the single-write boundary.

## [0.1.0-beta.16] - 2026-09-22

### Fixed

- Per-template batch preparation now reuses the already registered `ztum.templates.prepare_selected` action in explicit `async=1` mode instead of relying on a second module action.
- The browser queue now receives raw `main_block` JSON from the existing action, avoiding the HTML `<!DOCTYPE ...>` response that caused `Unexpected token '<' ... is not valid JSON` during beta.15 field testing.

### Changed

- Removed the redundant `ztum.templates.prepare_one` manifest action and controller.
- Queue requests reuse the existing preparation action's CSRF token and post one validated `templateid` at a time.

### Safety

- Heavy preparation remains one candidate per HTTP request.
- The normal preparation page and asynchronous single-candidate mode share one super-administrator-only controller with native CSRF validation enabled.
- No configuration-write path changed.

### Tests

- Updated manifest/action contracts to require the existing-route async branch, raw JSON response, single-template planning and absence of the redundant route.

## [0.1.0-beta.15] - 2026-09-22

### Fixed

- Batch preparation no longer performs the complete selected set inside one long-lived HTTP request, avoiding proxy/client 499/504 failures observed behind Cloudflare.

### Changed

- `ztum.templates.prepare_selected` now renders a lightweight preparation queue shell only.
- Added super-administrator-only `ztum.templates.prepare_one`, which prepares exactly one template and returns authoritative classification data as JSON.
- The browser drives preparation sequentially one template per request and aggregates Ready / Manual review / Conflict / Blocked results.
- Preparation progress is visible and can be stopped between templates.
- Batch execution remains disabled until the complete selected set has finished preparation; only Ready items contribute fresh evidence to the update form.

### Safety

- Per-template preparation may create/refresh rollback evidence but never imports Zabbix configuration.
- Batch execution still reruns fresh per-template preflight immediately before each write and preserves stop-on-first-failure semantics.
- A failed/timeout preparation request is classified Blocked without losing results already completed for other candidates.

### Tests

- Added contract coverage requiring bounded per-template preparation, native CSRF protection, sequential queue behavior, cancellation between templates and unchanged single-write-boundary guarantees.

## [0.1.0-beta.14] - 2026-09-22

### Fixed

- `Review selected updates` now uses `CActionButtonList` native submit mode so the selected-template review action is actually posted with `action=ztum.templates.review_selected`.
- Removed the unbound `CSimpleButton` content override that displayed an enabled-looking button but provided neither a submit action nor JavaScript handler.

### Tests

- Strengthened the selected-template action contract to require native action-button submit semantics and reject the previous unbound button pattern.

## [0.1.0-beta.13] - 2026-09-22

### Fixed

- Rollback review now passes the stored artifact format to `configuration.importcompare` instead of always treating rollback YAML as JSON.
- Post-rollback validation now uses the same artifact format, preventing a successful YAML restore from failing its final comparison with `Cannot read JSON: Syntax error`.
- Rollback preflight evidence now binds the target format and the final rollback target revalidation verifies that the format has not changed.

### Safety

- Unsupported rollback comparison formats fail closed before a Zabbix API comparison call.
- The stored format participates in rollback TOCTOU evidence and target matching; no configuration-write boundary was added or changed.

### Tests

- Added regression coverage proving stored YAML backup bytes reach `configuration.importcompare` with `format=yaml` during review and post-rollback validation.
- Added format-preservation assertions across rollback preflight and rollback result descriptors.

## [0.1.0-beta.12] - 2026-09-21

### Fixed

- Isolated upstream documents now preserve top-level `graphs` and `triggers` that belong exclusively to the selected template instead of dropping them outside the nested `templates` object.
- Template dashboards that reference an official top-level graph now receive that graph in both current-upstream comparison and controlled import sources.
- The Acronis Cyber Protect Cloud MSP update no longer misclassifies the official `Acronis CPC: Alerts overview` graph as a LOCAL-only customization merely because the graph is stored at export top level.
- Controlled import no longer sends a dashboard that references a graph omitted by the isolation layer.

### Safety

- Top-level graphs or triggers that reference more than the selected template fail closed instead of silently importing cross-template dependencies.
- Dashboard references to missing or foreign-template top-level graphs fail before `configuration.import`.
- Historical baseline isolation uses the same dependency-preserving rules as current upstream isolation, keeping BASE / LOCAL / UPSTREAM analysis consistent.

### Tests

- Added regression coverage for top-level graph preservation, dashboard-to-graph dependencies, top-level triggers, historical baseline isolation and cross-template dependency rejection.

## [0.1.0-beta.11] - 2026-09-21

### Fixed

- Controlled update now distinguishes the SHA-256 of the exact raw upstream YAML file from the canonical per-template content fingerprint stored by the upstream index.
- `TemplateUpdateCandidateService` verifies the immutable commit/path raw source bytes against a dedicated per-path source fingerprint instead of incorrectly comparing whole-file YAML bytes with a template-object hash.
- Legacy validated indexes remain usable for read-only inventory/comparison, but controlled update fails closed until an index with raw source fingerprints is available.
- Upstream source and commit-history HTTP clients now use the packaged project version in their User-Agent instead of the stale `0.1.0-dev` literal.

### Added

- Per-source-path raw SHA-256 fingerprints in generated upstream indexes under `sources`, while retaining `content_sha256s` as canonical template-content fingerprints.
- Preflight evidence schema 5 binds both `upstream_source_sha256` and `upstream_content_sha256`.
- Regression coverage using intentionally different raw-source and template-content fingerprints so the beta.10 hash-contract bug cannot silently return.
- Upstream-index workflow smoke validation that compares bytes fetched from the canonical raw endpoint with the generated source fingerprint.

### Changed

- Preflight and update result views display raw source and template-content fingerprints separately.
- The controlled update candidate contract now carries both fingerprints through fresh preflight, TOCTOU evidence comparison, candidate reconstruction and result reporting.

### Safety

- The failing beta.10 path stopped before `configuration.import`; beta.11 preserves that fail-closed behavior and strengthens the immutable-source verification contract.
- No additional Zabbix configuration-write boundary was introduced.

## [0.1.0-beta.10] - 2026-09-21

### Added

- Explicit reviewed-update path for authoritative candidates with no unresolved identity and no BASE / LOCAL / UPSTREAM conflict, but with known local-overwrite and/or medium/high technical-risk conditions.
- Second super-administrator acknowledgement checkbox before a reviewed override can reach the single controlled `configuration.import` boundary.
- Reviewed-mode and exact review reasons bound into the fresh preflight SHA-256 evidence to prevent changing update mode between review and write.
- Historical-baseline provenance details in the comparison view: same-version commit count, distinct official contents, exact LOCAL matches and closest semantic distance.

### Changed

- Known local-overwrite is no longer an absolute dead-end when the historical baseline and three-way analysis are authoritative. It enters `review_required`, may create/verify a rollback backup, then advances to `review_backup_verified` and a manually acknowledged preflight.
- Medium/high technical-risk candidates use the same explicit reviewed path instead of being permanently unable to advance.
- True three-way conflicts, unresolved identities, ambiguous/missing baselines and incomplete risk coverage remain hard blockers and cannot be overridden.
- Reviewed override candidates remain excluded from unattended batch `Ready`; batch execution continues to update only standard fully automatic candidates.
- Backup verification now runs for both standard backup candidates and explicit reviewed candidates.

### Safety

- The reviewed path never bypasses rollback creation, backup/current-export equality, immutable upstream commit/path/hash validation, fresh server-side preflight, TOCTOU evidence matching or post-import validation.
- No additional Zabbix write boundary was introduced.

## [0.1.0-beta.4] - 2026-09-16

### Added

- Bounded multi-template safety preparation for up to 25 explicitly selected official update candidates.
- Batch classification into `Ready`, `Manual review`, `Conflict / local overwrite` and `Blocked` using the existing per-template analysis/readiness pipeline.
- Persistent rollback artifact creation/refresh during batch preparation only for templates that reached `candidate_for_backup`.
- Fresh per-template preflight evidence binding for every batch candidate classified `Ready`.
- Super-administrator-only controlled sequential batch execution.
- Stop-on-first-non-success behavior with explicit `updated`, `failed` and `not_attempted` result groups.
- Batch result page that reports whether any configuration write occurred and whether the stopping template itself performed a write.
- Unit coverage for batch planning categories, rollback preparation, sequential order, evidence validation and stop behavior.
- Static batch-action contract coverage for CSRF, Super Admin authorization, explicit confirmation and the single approved configuration-write boundary.

### Changed

- Selected-template review is now bounded to 25 templates so the review limit matches the batch safety-preparation/execution limit.
- The selected-template review page can advance eligible candidates into `Prepare selected updates` for full safety analysis and rollback preparation.
- Batch execution reuses `TemplateControlledUpdateService` for every template instead of introducing a second import path.
- Every template reruns the complete server-side preflight immediately before its own `configuration.import`; stale evidence stops the batch before that template is written.
- Automatic rollback remains deliberately disabled. If one template fails after a write, execution stops and the operator must inspect/choose the correct rollback artifact.

### Validation status

- Zabbix 7.0.30 field validation has already confirmed inventory, upstream index retrieval, official UUID matching, version comparison and native selection controls.
- The upstream-index workflow now completes end-to-end with generated-index runtime-decoder validation, canonical raw/history endpoint smoke tests and publication.
- Beta.4 is the first laboratory candidate for end-to-end selected batch preparation and controlled sequential update testing.
- Zabbix 8.x runtime validation remains pending.
- Production use is not recommended.

## [0.1.0-beta.3] - 2026-09-15

### Added

- Native Zabbix `CCheckBox` row selection for official templates with an available upstream vendor-version update.
- Native select-all behavior using the same `checkAll()` pattern used by Zabbix list views.
- `Review selected updates` action using `CActionButtonList` and a CSRF-protected bounded selected-template review controller.
- Read-only selected-template review page that rebuilds inventory, upstream identity and vendor-version state for only the explicitly selected subset.
- Runtime validation notes promoted from the Zabbix 7.0.30 field pass.
- Generated-index validation through the same PHP runtime decoder before the upstream-index workflow may publish indexes.

### Fixed

- Runtime upstream-index validation now accepts the literal `+` character used by official MikroTik source paths such as `mikrotik_CRS305-1G-4S+IN_snmp`, while continuing to reject `.` / `..` traversal segments and paths outside `templates/.../*.yaml`.
- Invalid upstream paths now include a bounded sanitized path value in diagnostics, making future generator/runtime mismatches actionable in the lab.

### Changed

- The inventory's per-row `Update preflight` form was replaced by an `Update review` link so the table can use one native Zabbix selection form without invalid nested forms.
- Selection is intentionally a review scope in beta.3. Bulk `configuration.import` is not performed; every selected template still uses its own backup, preflight, explicit confirmation and single approved import boundary.
- Upstream index CI is triggered when runtime index-validation code changes and uses the packaged VERSION in canonical endpoint smoke tests.

### Test-release status

- Zabbix 7.0.30 local inventory is field-validated with 303 visible templates in the first lab environment.
- Beta.3 confirmed that the 7.0 upstream index validates, yielding 298 official UUID matches, 290 updates available and zero repository-unavailable results in the lab.
- Native selected-template checkbox behavior is visible in Zabbix 7.0.30.
- Zabbix 8.x runtime validation remains pending.
- Production use is not recommended.

## [0.1.0-beta.2] - 2026-09-15

### Added

- Runtime project-version source backed by the root `VERSION` file.
- Administrator-only upstream diagnostics showing the requested index endpoint, cURL availability, `allow_url_fopen`, OpenSSL availability and bounded failure detail.
- HTTP transport fallback: when cURL is available but fails, the upstream index loader can also try the PHP stream transport when `allow_url_fopen` is enabled.
- Regression coverage preventing the inventory page and upstream HTTP user agent from falling back to the stale `0.1.0-dev` literal.

### Fixed

- The inventory header no longer displays the hard-coded `0.1.0-dev`; it now reports the actual packaged version.
- The upstream HTTP user agent now tracks the packaged version instead of the stale development version.
- Upstream repository failures now retain a bounded diagnostic cause for administrators while continuing to fail closed for official-template identity.

### Test-release status

- First Zabbix 7.x runtime inventory pass observed successfully.
- The beta.2 upstream request reached and downloaded the index, but runtime validation rejected an official source path containing `+`; this is fixed in beta.3.
- Zabbix 8.x runtime validation remains pending.
- Production use is not recommended.

## [0.1.0-beta.1] - 2026-09-15

### Added

- Initial project structure and native Zabbix frontend module integration.
- Runtime detection for supported Zabbix 7.x and 8.x major versions.
- Read-only installed-template inventory using the native Zabbix `template.get` API service.
- Inventory metadata for UUID, vendor, vendor version, template groups and directly linked host count.
- Compact official-upstream template indexes generated from the canonical Zabbix Git repository.
- Runtime upstream-index retrieval with strict validation, 15-minute cache and stale-cache fallback.
- UUID-based upstream identity matching with explicit fail-closed states.
- Numeric comparison of installed and official upstream `vendor.version` values.
- Canonical raw-source and path-history smoke tests against `git.zabbix.com`.
- Safe official-template source retrieval from `git.zabbix.com` by validated immutable commit and path.
- Per-template content comparison through Zabbix `configuration.importcompare`.
- Historical baseline lookup by stable UUID plus installed `vendor.version` from canonical path history.
- Three-way BASE / LOCAL / UPSTREAM field analysis with upstream-only, local-overwrite, converged, conflict and unresolved classifications.
- Conservative update review-priority classification that keeps technical severity separate from three-way coverage.
- Exact directly linked host count as known impact breadth, without arbitrary host-count severity thresholds.
- Local immutable historical-baseline cache with SHA-256 validation and private atomic writes.
- Native one-template YAML export service backed by Zabbix `configuration.export`.
- Private local template backup repository with exact byte count and SHA-256 verification.
- Deterministic JSON backup manifests recording template identity and export provenance.
- Update readiness gate with explicit blocked/review/candidate/verified workflow states.
- Fail-closed readiness blockers for missing baseline, unresolved analysis, three-way conflict and local-customization overwrite risk.
- CSRF-protected POST action for persistent rollback-backup creation.
- Persistent default backup root at `/var/lib/zabbix-template-update-manager/backups` with no silent `/tmp` fallback.
- Bounded per-template rollback-artifact inventory with manifest, filename, path, size, mode and SHA-256 validation.
- Fresh installed-template export verification against the newest intact rollback artifact.
- Explicit `backup_verified` readiness state when the newest artifact exactly matches the current installed export.
- Administrator-only rollback backup history page with a bounded view of the newest 50 artifacts.
- Reusable `TemplateUpdateAnalysisService` for current-upstream, historical-baseline, three-way, risk, readiness and rollback-verification analysis.
- Fail-closed `TemplateUpdatePreflightService` that freshly recomputes authoritative update analysis and requires `backup_verified`.
- Deterministic update-preflight SHA-256 evidence binding template identity, immutable upstream source identity/hash, versions, rollback/current-export fingerprints and impact context.
- Immutable update-candidate reconstruction that re-fetches exact commit/path source and validates SHA-256 against the upstream index.
- `TemplateConfigurationImportService` as the repository's single approved `configuration.import` boundary, reusing the same rules as `configuration.importcompare`.
- `TemplateControlledUpdateService` with fresh preflight rerun, evidence-fingerprint TOCTOU protection, candidate revalidation, controlled import and post-import validation.
- Super-administrator-only CSRF-protected update action with explicit confirmation.
- Post-update validation requiring current official version, official UUID match, current-upstream content equality and zero remaining import-comparison differences.
- Controlled rollback review for explicitly selected valid stored artifacts.
- Deterministic rollback-preflight evidence binding current template export, selected artifact identity/fingerprint and import-preview counts.
- Fresh recovery backup creation before restoring an older artifact.
- Controlled rollback orchestration with a second read-only preflight, current-state drift detection and reuse of the single `configuration.import` boundary.
- Post-rollback validation requiring matching template ID/UUID/vendor version and zero remaining import-comparison differences.
- Super-administrator-only CSRF-protected rollback action with explicit confirmation.
- Native update and rollback result pages that distinguish blocked/no-write, successful/validated and write-performed-but-validation-failed states.
- CI controlled-write guard enforcing exactly one configuration-import call site and prohibiting additional Zabbix API/direct-database write paths.
- Root `VERSION` file and CI validation keeping project/module version metadata consistent.
- Unit and static action-contract coverage for inventory, upstream identity/version/source/history, baseline cache, import comparison, three-way analysis, risk/readiness, backups, update preflight/update execution and rollback execution.
- Dedicated documentation for controlled update, rollback, backup/readiness and three-way analysis.

### Changed

- Official identity is determined by UUID, not vendor metadata alone.
- Version comparison is independent from identity matching.
- Content differences on outdated templates are classified against a historical official baseline of the same vendor version rather than against current upstream alone.
- Overall update review priority is forced to `Unknown` when three-way local-overlap coverage is unavailable or unresolved.
- Direct host count is presented as impact context rather than being used to inflate technical severity.
- Backup artifacts use template IDs rather than names for filesystem paths and are written as private local files.
- Medium/high technical risk remains a manual-review state; the first controlled automatic update path is limited to `none`/`low` risk with complete three-way evidence.
- `backup_verified` advances only to a separate fresh controlled preflight; it never directly authorizes configuration import.
- Configuration writes are no longer globally disabled: exactly one reviewed `API::Configuration()->import()` boundary is permitted for explicit controlled update and rollback workflows.
- The historical `tests/read_only_guard.php` now acts as a controlled-write-boundary guard.
- Invalid backup artifacts are never selectable for rollback, and backup-history scanning remains bounded to the newest 50 manifests.
- Rollback never runs automatically after an update validation failure; it is a separate, explicit super-administrator operation.

### Test-release status

- Implementation: ready for laboratory testing.
- Automation validation: required CI suite must be green before tagging.
- Field validation: pending on real Zabbix 7.x and 8.x laboratory instances.
- Production use: not yet recommended.

## [0.1.0-dev] - 2026-09-14

### Added

- Project created.
