# Runtime validation notes

This file records field findings that are promoted into regression tests and release notes.

## Zabbix 7.0.31 / 0.1.0-beta.57

- Real dark-theme catalog field pass completed after the native filter/search changes.
- The catalog loaded with 403 local templates and 356 authoritative Zabbix-vendor matches.
- Official catalog source resolved as Zabbix 7.0 with a fresh index.
- Partial Name search was confirmed with `Adv`, returning both `Advanced ICMP Ping` and `Advanced ICMP Ping with Jitter`.
- The search therefore does not require an exact name and the native `CFormGrid` Name + Status filter layout renders correctly on Zabbix 7.0.31.
- This evidence is read-only/UI evidence; it does not by itself re-certify update/install/rollback write paths for beta.58.

## Zabbix 8.0.0beta2 / PHP 8.4.24

- Real catalog loading originally exposed an HTTP 500 caused by the removed Zabbix 7.x `ZBX_STYLE_PAGING_BTN_CONTAINER` constant.
- Beta.53 introduced the compatibility fix; the catalog subsequently loaded successfully.
- Beta.55 removed the custom All/Pages wrapper and delegated catalog pagination entirely to native `CPagerHelper`.
- This field finding directly motivated the cross-Zabbix frontend-symbol CI gate added during beta.58 hardening.
- Full Zabbix 8.x controlled update/install/rollback/offline validation remains open.

## Zabbix 7.0.30 / 0.1.0-beta.2

- Local inventory loaded successfully with 303 visible templates.
- Upstream transport succeeded, but runtime index validation rejected an official Zabbix template path containing `+` in a MikroTik model/path component.
- `0.1.0-beta.3` widens the path allow-list only for the literal `+` character while preserving the fixed `templates/.../*.yaml` prefix/suffix requirement and traversal protections.
- `0.1.0-beta.3` also introduces native Zabbix row selection controls for choosing a subset of update candidates. Selection is a review/preflight scope; it does not bypass per-template safety gates.
