# Beta 0.1.0-beta.7 field fix

The Zabbix 7.0.30 laboratory comparison of `AIX by Zabbix agent` still aborted normalized three-way and risk analysis in beta.6 even after ambiguous fallback identities were made non-fatal.

Review of Zabbix 7.0 `CConfigurationImportcompare::compareByStructure()` showed a second valid native shape: an updated structured entity may contain only nested change blocks and intentionally omit direct `before` / `after` state when the parent itself did not change.

Beta.7 handles these structural-only wrappers without throwing. Nested changes are retained, but because the wrapper no longer carries an authoritative parent identity, descendants are marked `identity_reliable=false` with `identity_issue=unresolved_parent_identity`. This keeps the analysis useful and the readiness gate fail-closed.

Beta.7 also appends a bounded, sanitized exception diagnostic to administrator-facing analysis errors so future runtime incompatibilities can be diagnosed from the comparison page without relying on PHP-FPM log routing.

No configuration-write path is changed by this fix.
