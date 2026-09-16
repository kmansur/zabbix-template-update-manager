# Runtime validation notes

This file records field findings that are promoted into regression tests and release notes.

## Zabbix 7.0.30 / 0.1.0-beta.2

- Local inventory loaded successfully with 303 visible templates.
- Upstream transport succeeded, but runtime index validation rejected an official Zabbix template path containing `+` in a MikroTik model/path component.
- `0.1.0-beta.3` widens the path allow-list only for the literal `+` character while preserving the fixed `templates/.../*.yaml` prefix/suffix requirement and traversal protections.
- `0.1.0-beta.3` also introduces native Zabbix row selection controls for choosing a subset of update candidates. Selection is a review/preflight scope; it does not bypass per-template safety gates.
