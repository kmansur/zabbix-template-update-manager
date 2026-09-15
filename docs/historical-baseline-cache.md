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

Runtime cache files are stored below the same private temporary project directory used by the upstream index cache, under a `historical-baselines` subdirectory.

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