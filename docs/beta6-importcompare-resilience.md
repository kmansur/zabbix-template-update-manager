# Beta 0.1.0-beta.6 field fix

The Zabbix 7.0.30 laboratory comparison of `AIX by Zabbix agent` showed that a remaining ambiguous fallback identity could abort the complete normalized three-way and risk analysis even though the native `configuration.importcompare` result itself was valid.

Beta.6 changes the extractor so an identity collision is retained as two separate records and both are marked `identity_reliable=false` with `identity_issue=ambiguous_identity`. Downstream analysis therefore remains fail-closed for the affected records but can continue explaining all other entities instead of dropping the entire analysis.

No configuration-write path is changed by this fix. The update readiness gate remains blocked whenever unresolved identities are present.
