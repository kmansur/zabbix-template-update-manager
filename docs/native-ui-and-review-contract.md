# Native Zabbix UI and administrator review contract

ZTUM is a Zabbix frontend module, not a separate web application. The module
must look and behave like the running Zabbix frontend on **both 7.x and 8.x**.

## Native presentation (required)

- Render pages with Zabbix frontend view/layout infrastructure. Prefer core
  Zabbix table, form, button, checkbox, message, modal and pager components.
- Reuse the current frontend's styles, colors, fonts, sizing, spacing, navigation,
  breadcrumbs, messages, light/dark theme and responsive behavior.
- Do not introduce a UI framework, hardcoded third-party theme, independent
  dashboard shell or bespoke alert/confirmation popup.
- Reuse Zabbix translation and escaping conventions. Avoid inline JavaScript or
  unsafe HTML in diff values. Preserve accessibility and keyboard navigation.
- Avoid hidden behavior: preview, backup, risk assessment and confirmation
  must be visible and distinguishable before a write.

## Review and local customization policy

- **Known local overrides:** identify the exact affected entities and fields,
  current values, proposed values, and effects (overwrite/remove/retain).
  Show an explicit, prominent warning whenever an identified local override
  would be overwritten or removed. Never merely say `update available`.
- **Baseline unavailable:** show `Differences between installed and incoming
  template` and explicitly say `It is not possible to determine which
  differences are local customizations without a verified baseline`.
  Do not mislabel all changes as local customizations and do not claim that
  there is no customization.
- **Completely known reviewed changes:** permit a consciously acknowledged
  assisted route even if the historical baseline is absent, subject to all
  independent identity, integrity, preview, backup and preflight requirements.
- **Incomplete/unknown changes:** never present a reassuring partial list.
  Clearly mark the preview incomplete and block a write until authoritative
  impact can be established.
- **Explicit confirmation:** when changes can overwrite/remove installed
  settings, use Zabbix-native checkbox/confirmation in the operation view.
  Confirmation must refer to the current immutable preflight evidence and
  expire/revalidate when any source, template, configuration or backup changes.
- **Rollback:** backup must be persisted and verified before writing. Display
  its status and the documented recovery path.

## Layout for an individual update

1. Native Zabbix page header: template name and installed -> available version.
2. Clear result summary: detected local overrides, possible overwrites,
   unknown baseline status, and risk severity.
3. Standard Zabbix table: affected entity, field, current value, incoming
   value, action. Paginate without suppressing unknown/truncated changes.
4. Backup and final verification state.
5. Native confirmation and the update action, available only after server-side
   authorization. Disabled buttons alone are not security controls.

## Non-negotiable security gates

- Official immutable source and fingerprint checks; correct UUID/template
  identity; required privileges and CSRF checks.
- Complete reliable preview; verified persisted backup; independent
  server-side preflight immediately before write.
- One Zabbix import/write boundary; auditable operation history and
  predictable recovery path.
- An administrative acknowledgement can accept **known overwrite risk**, not
  bypass a failed integrity check, an unknown effect or missing backup.

## Acceptance criteria for Zabbix 7/8 laboratory review

- Confirm native look and operation in Zabbix light and dark themes.
- Confirm tables, pager, buttons, messages and confirmation match native Zabbix
  behavior at common desktop widths and keyboard interaction.
- Confirm an explicit overwrite example names the affected entities and fields.
- Confirm unknown-baseline wording does not claim proven local changes.
- Confirm incomplete preview and missing backup prevent update even if the
  administrator acknowledges the warning.
- Confirm stale acknowledgement is rejected server-side after a changed
  template or changed upstream source.
- Confirm full list of identified affected changes is reviewable before write;
  no truncation silently authorizes an update.

This is the product and UI contract. It does **not** claim that all checks are
already implemented or field-validated.
