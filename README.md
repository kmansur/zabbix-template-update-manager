# Zabbix Template Update Manager

Zabbix Template Update Manager is a native Zabbix frontend module for discovering, matching, comparing and eventually updating installed Zabbix templates safely.

## Project status

Early development.

Current version:

`0.1.0-dev`

The current milestone is strictly read-only. The module can inventory templates, verify upstream identity and compare official vendor versions, but it must not modify templates or other Zabbix configuration yet.

## Target versions

- Zabbix 7.x
- Zabbix 8.x

Runtime compatibility detection uses the native Zabbix `ZABBIX_VERSION` frontend constant. Unsupported or unknown major versions fail closed instead of being assumed compatible.

## Current functionality

The module inventories templates visible to the current Zabbix user through the native `template.get` API service. No direct database access is used.

The inventory currently displays:

- visible/technical template name;
- UUID;
- vendor name;
- installed vendor version;
- official upstream vendor version when the UUID matches;
- template groups;
- number of directly linked hosts;
- upstream identity status;
- vendor-version comparison status.

Upstream identity is verified by UUID against compact indexes generated from the canonical Zabbix Git repository. `vendor_name = Zabbix` alone is never treated as proof that a template is official.

Current upstream identity states:

- `Official UUID match`;
- `Not found upstream`;
- `No UUID`;
- `Invalid UUID`;
- `Repository unavailable`.

## Version comparison

Version comparison runs only after an authoritative upstream UUID match. Official Zabbix vendor versions in the numeric `major.minor-revision` form, such as `7.0-4`, are compared component by component instead of lexically.

Current version states:

- `Current` — installed and upstream vendor versions are equal;
- `Update available` — the official upstream vendor version is newer;
- `Installed version is newer` — the installed vendor version is numerically newer than the current index;
- `Installed version missing` — the local template has no vendor version;
- `Upstream version missing` — the matched upstream record has no vendor version;
- `Version format cannot be compared` — one of the versions does not use the supported numeric format;
- `Not applicable` — the template has no authoritative official UUID match.

`Update available` is intentionally **not** a safety decision. The module does not yet compare the installed template content against the official source, so local modifications and merge conflicts are not classified at this stage.

## Upstream index architecture

Querying every official YAML file from the Zabbix repository on every page load would require hundreds of remote requests. Instead, the project builds compact UUID indexes with GitHub Actions from the canonical full Zabbix Git repository.

The official repository can legitimately package the same template UUID in more than one YAML bundle (for example, shared VMware child templates). The index therefore merges repeated UUID definitions when their technical identity and vendor metadata agree, retains every official source path, and records SHA-256 hashes for each distinct content variant. A repeated UUID with conflicting identity metadata still fails index generation.

Supported index lines are built for:

- 7.0;
- 7.2;
- 7.4;
- 8.0.

The index records its exact source ref, source commit and commit date. For active release branches, the `release/<major.minor>` branch is preferred. If an old non-LTS line no longer has an active release branch, the latest matching maintenance tag is used. For 8.0 prereleases, the latest matching prerelease tag or guarded `master` fallback is used according to source availability.

The canonical repository is required for index generation because it contains the complete ref/tag history. The GitHub repository maintained by the Zabbix organization mirrors master and supported release branches and remains useful as a public browsing/reference mirror.

The Zabbix frontend downloads one compact JSON index and caches it locally for 15 minutes. If refresh fails, a previously cached index can be used as stale read-only data. If no index is available, the local inventory still works and upstream status fails closed as `Repository unavailable`.

Canonical Zabbix source and clone URL:

- <https://git.zabbix.com/projects/ZBX/repos/zabbix/>
- `https://git.zabbix.com/scm/zbx/zabbix.git`

Official GitHub mirror:

- <https://github.com/zabbix/zabbix>

## Initial goals

- Discover installed/visible templates
- Identify official Zabbix templates by UUID
- Detect installed vendor/version
- Compare installed vendor versions with upstream metadata
- Compare installed templates with upstream template content
- Detect local modifications
- Detect available updates
- Display granular differences
- Estimate update risk
- Show affected hosts
- Preserve the native Zabbix frontend experience

## Safety

The current development phase is read-only.

No template will be modified or imported automatically.

CI includes a read-only guard that rejects known Zabbix API write methods, template write operations and direct database write calls during this milestone.

## Architecture

The project is implemented as a native Zabbix frontend module and does not modify Zabbix core files.

The frontend reuses native Zabbix components and styling so that menus, tables, controls, fonts, colors and themes remain consistent with the installed Zabbix version.

Current flow:

```text
Zabbix template.get
      |
      v
TemplateInventoryService
      |
      +---------------------------+
      |                           |
      v                           v
Local template inventory    Upstream index JSON
                                  |
                                  v
                           UUID identity matcher
                                  |
                                  v
                        Vendor version comparator
                                  |
                                  v
                         Native Zabbix table
```

## Development validation

CI validates:

- PHP syntax;
- `manifest.json` structure and action registration;
- read-only constraints;
- Zabbix 7/8 runtime version detection;
- template repository query contract;
- template inventory normalization;
- upstream index decoding;
- UUID matching;
- vendor-version comparison;
- deterministic upstream-index generation;
- handling of equivalent/repeated UUID definitions and rejection of conflicting identity metadata.

Local checks:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/validate_manifest.php
php tests/read_only_guard.php
for test in tests/unit/*Test.php; do php "$test"; done
python -m py_compile tools/build_upstream_index.py
python tests/test_build_upstream_index.py
```

## License

License to be defined.
