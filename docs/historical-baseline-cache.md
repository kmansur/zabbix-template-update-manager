# Historical baseline cache

## Purpose

Historical baseline discovery can require a path-history request plus several immutable source fetches before the installed `vendor.version` is found. Repeating that scan on every comparison page load is unnecessary once a matching baseline has been proven.

The module therefore caches only **successful** historical baseline resolutions locally.

## Cache identity

A cache entry is keyed from all values that make the historical baseline authoritative:

- current official upstream commit;
- validated canonical `templates/*.yaml` source path;
- stable template UUID;
- installed target `vendor.version`;
- expected official vendor name.

A new upstream commit, a different template path, UUID or installed vendor version automatically selects a different cache entry. No mutable branch name or guessed Git tag is part of the cache identity.

## Stored data

Only the isolated historical official import source and its provenance are stored:

- historical immutable commit;
- canonical path;
- installed vendor version;
- commits examined when the baseline was originally resolved;
- history truncation flag;
- isolated Zabbix import source;
- SHA-256 fingerprint of the isolated source.

The cache contains no local template state, credentials or Zabbix secrets.

## Integrity and permissions

Runtime cache files are stored in `/var/lib/zabbix-template-update-manager/cache/historical-baselines`, under the private runtime directory provisioned by `install.sh`; there is no fallback to `/tmp`.

Safety rules:

- directory creation requests mode `0700`;
- temporary files request mode `0600`;
- writes are atomic through a temporary file followed by rename;
- cache files have a hard size limit;
- schema and every identity field are validated on read;
- source SHA-256 must match before cached content is trusted;
- malformed or tampered cache entries are ignored and the canonical lookup path is used again.

## Positive-only caching

`not_found`, `history_limit_reached` and lookup failures are deliberately not cached.

This avoids turning an incomplete historical scan or transient repository/network problem into a persistent negative result. Only a successfully proven immutable baseline is reusable.

## Runtime behavior

```text
lookup historical baseline
        |
        v
validated cache key
        |
   +----+----+
   |         |
 cache hit   cache miss
   |         |
   |         v
   |   canonical path history
   |         |
   |   resolve baseline
   |         |
   |    if found -> cache
   |         |
   +----+----+
        |
        v
configuration.importcompare
```

The comparison semantics do not change. The cache only avoids repeated canonical history/source retrieval for a baseline that was already proven by immutable identity.
## Pipeline candidate diagnostics (experimental)

`tools/build_historical_candidate_diagnostic.py` generates a **non-authoritative** JSON report from an offline Git checkout. It records immutable commit IDs, per-file SHA-256 and vendor version candidates, including multiple bodies with an unchanged `vendor.version`. The tool traverses all reachable parents in topological order and retains distinct source bodies across merge parents. It explicitly exposes missing paths (including rename boundaries), shallow repositories and truncated scanning; it **does not** prove rename tracking, version-boundary completeness or equivalence to canonical history and is **not** consumed by the Zabbix frontend. CI tests it against synthetic Git history.

Example (developer pipeline checkout, not Zabbix host):

```sh
python tools/build_historical_candidate_diagnostic.py \
  --source-dir /path/to/official-zabbix-git-checkout \
  --commit <full-official-40-character-sha> \
  --path templates/app/acronis/template_app_acronis_cyber_protect_cloud_http.yaml \
  --uuid bf3107deff3a4aabab1e1c0ee71a3281 \
  --output build/historical/acronis-diagnostic.json
```

No production release or automatic update decision may use this report. Before an authoritative index is enabled we must implement exhaustive merge/rename candidate coverage, schema and provenance validation, reproducibility checks, and a signed publisher trust chain (issue #116).

### Rename evidence (diagnostic only)

The report includes `rename_transitions` obtained from `git log --follow --find-renames` and always sets `rename_tracking_authoritative: false` and `history_complete: false`. This provides clues when a YAML source moved, but **does not** resolve every merge-parent path or validate a complete historical candidate set. An unresolved file path remains `missing_history_path: true`; the consumer must not interpret the report as proof of a baseline or authorize writes.

### Negative-evidence inventory

A diagnostic report now records `missing_revisions`, `invalid_revisions`, `shallow_repository`, `truncated`, `distinct_vendor_versions` and `duplicate_vendor_versions`. A missing source path no longer stops enumeration of other reachable revisions; however **any gap remains visible and never upgrades the report to authoritative**. Invalid YAML and ambiguous UUID definitions are counted as invalid revisions instead of silently choosing a nearby baseline. CI covers both situations.

The existing frontend is unchanged. In particular, this pipeline output is **not** written into the live ZTUM cache and may not authorize a template import.
