# Native Zabbix UI contract

Template Update Manager is a native Zabbix frontend module. Its UI should look and behave like Zabbix, not like a separate web application embedded inside Zabbix.

## Principles

1. Use Zabbix frontend components before introducing custom markup.
2. Use Zabbix theme/style constants instead of hard-coded colors.
3. Keep light and dark theme behavior owned by Zabbix.
4. Treat frontend state as presentation only; server-side controllers/services remain the authority for permissions, evidence and configuration writes.
5. Do not introduce Bootstrap, Tailwind, Material UI or another UI framework.
6. Avoid custom CSS unless an interface requirement cannot be expressed safely with a native Zabbix class/component.

## Page composition

User-facing views use `CHtmlPage`.

Navigation/actions that belong to the page header should use `CHtmlPage::setControls()` with native Zabbix elements such as `CList` and `CLink`, instead of inserting ad-hoc navigation text into the page body.

Recommended building blocks include:

- `CHtmlPage`;
- `CTableInfo`;
- `CFilter`;
- `CForm`;
- `CFormList`;
- `CCheckBox`;
- `CButton` / `CSubmitButton`;
- `CActionButtonList`;
- `CTabView` when a genuinely tabbed workflow is useful;
- `CList` for compact horizontal metadata/navigation.

## Status presentation

Semantic status text should use Zabbix-provided style classes through `src/Support/FrontendUi.php`.

Current mappings:

| Semantic tone | Native Zabbix style |
|---|---|
| Success | `ZBX_STYLE_GREEN` |
| Warning / reviewed state | `ZBX_STYLE_ORANGE` |
| Error / blocked state | `ZBX_STYLE_RED` |
| Informational / running state | `ZBX_STYLE_BLUE` |
| Neutral / unavailable state | `ZBX_STYLE_GREY` |

Do not hard-code hexadecimal/RGB colors in module views.

## Selection controls

Selection controls that are visible to users must be rendered by Zabbix PHP components. In particular, reviewed update rows use native `CCheckBox` controls rendered server-side.

JavaScript may enable/disable/check an existing native control, but should not create raw `<input>`, `<button>` or `<select>` elements.

Bulk reviewed update selection uses explicit native buttons:

- **Select all eligible reviewed updates**
- **Clear reviewed selection**

The batch safety rules still determine which row checkboxes become enabled.

## JavaScript

Behavior-heavy batch views may use inline `CScriptTag`, but it must run through `setOnDocumentReady()`.

JavaScript must not become a security boundary. Before every update/install/rollback write, the server must rerun the authoritative preflight and evidence validation.

A future maintainability refactor may move large batch scripts into dedicated frontend JS files without changing this security model.

## Forms and writes

Write forms/actions must continue to use:

- native CSRF protection;
- native Zabbix permission checks;
- explicit user confirmation;
- server-side fresh preflight;
- the single approved `configuration.import` boundary.

Visual enable/disable state is never sufficient authorization.

## Theme and accessibility

The module should inherit Zabbix typography, spacing, table behavior, focus behavior and light/dark theme automatically.

Where practical:

- page controls should carry an accessible navigation label;
- controls should have meaningful labels/titles;
- status must remain understandable from text alone, not color alone;
- dynamic batch state must always include text in addition to native status color.

## Automated guard

`tests/ui_native_guard.php` enforces the current minimum UI contract:

- no empty view stubs;
- all user-facing views use `CHtmlPage`;
- no raw input/button/select markup in views;
- no dynamically created raw input controls;
- no inline/hard-coded six-digit colors;
- no Bootstrap/Tailwind/Material UI;
- batch scripts run on document ready;
- reviewed update selection remains a native `CCheckBox`;
- semantic status helpers remain bound to native Zabbix style constants.

A green UI guard proves repository-level UI conventions only. It does not replace visual field validation on supported Zabbix versions and themes.
