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

### Immutable object verification (offline-only)

The diagnostic reporter can be verified by the companion tool:

```sh
python tools/verify_historical_candidate_diagnostic.py \\
  --source-dir /path/to/official-zabbix-git-checkout \\
  --report build/historical/acronis-diagnostic.json
```

It compares the declared SHA-256 for each candidate to the YAML bytes read from the pinned Git object and rejects altered fingerprints, invalid report identity and any attempt to set `authoritative` or `history_complete` to true. This is **local integrity only**, not publisher authentication: an attacker capable of replacing both the index and its upstream checkout is not excluded.

### Requirements before publishing signed historical indexes

1. Produce reproducible historical candidate manifests with fixed schema, sorted identities and stable serialization; reject shallow/truncated history, missing paths, unresolved renames and ambiguous UUID/version attribution.
2. Define a separate offline signing identity and publish the corresponding **pinned public key with the ZTUM release**. Do not trust public keys downloaded from the same mutable index endpoint.
3. Sign exactly the canonical manifest bytes and verify the detached signature and pinned key **before** any historical index can influence BASE/LOCAL/UPSTREAM decisions.
4. Bind the signature to the expected Zabbix major/minor line, upstream source commit, template UUID and canonical paths; require repository identity and content hashes.
5. Publish positive and negative tests for valid/expired or rotated keys, byte tampering, substitution, truncation, duplicate vendor-version candidates, renamed files and merge parents. Rotation requires a deliberate trusted module release or a signed delegation policy.

Until then, the diagnostic artifacts are not read by the ZTUM frontend and cannot make updates eligible.

### Experimental detached Ed25519 signing (publisher only)

The offline `tools/sign_historical_diagnostic.py` script signs the **canonicalized diagnostic JSON bytes** (sorted JSON keys, compact separators, UTF-8, trailing newline). It accepts an explicit Ed25519 private PEM, key identifier and output location. The corresponding verifier accepts an **explicit, independently trusted public PEM** and expected key identifier:

```sh
python tools/sign_historical_diagnostic.py sign \\
  --report build/historical/acronis-diagnostic.json \\
  --private-key /secure/publisher-only/ed25519-private.pem \\
  --key-id experimental-01 \\
  --output build/historical/acronis-diagnostic.sig.json

python tools/sign_historical_diagnostic.py verify \\
  --report build/historical/acronis-diagnostic.json \\
  --signature build/historical/acronis-diagnostic.sig.json \\
  --trusted-public-key /path/to/independently-trusted-public.pem \\
  --key-id experimental-01
```

Keep the private key outside the source tree and CI logs; **do not commit it or generate it on the Zabbix host**. No trusted public key is pinned in a released ZTUM frontend yet. A valid signature authenticates bytes under the supplied key; it **does not** prove the manifest is complete, authoritative, or even correctly attributable to an official Zabbix source. The existing immutable-object verifier and strict completeness checks are separate prerequisites. Signed diagnostic reports remain **ineligible for automatic template imports**.

### Combined offline verification gate

To avoid treating a valid Ed25519 signature as proof of correct Git history, use
`tools/verify_signed_historical_diagnostic.py` before inspecting a report:

```sh
python tools/verify_signed_historical_diagnostic.py \\
  --report build/historical/acronis-diagnostic.json \\
  --signature build/historical/acronis-diagnostic.sig.json \\
  --trusted-public-key /path/to/independently-trusted-public.pem \\
  --key-id experimental-01 \\
  --source-dir /path/to/official-zabbix-git-checkout
```

This command must pass **both** Ed25519 verification and immutable Git-object/
ancestry checks, and fails on either problem. The accompanying regression test
also checks a forged candidate hash that was re-signed with a valid key. The
output always remains a **diagnostic**, never a frontend authorization.

### Diagnostic integrity versus update authorization

A signed report with `truncated: true`, `shallow_repository: true`, a missing source revision, or an unresolved rename may **pass diagnostic integrity verification**: that means only that the supplied report and available immutable objects are consistent. It is **not** proof that a historical baseline is complete. The verifier enforces the evidence-field schema, `authoritative: false`, `history_complete: false`, and the documented traversal identity.

No ZTUM frontend code consumes these reports or converts their verification outcome into update eligibility. A future authoritative index needs a **separate, explicitly reviewed policy gate**: anchored trusted signing key, complete historical range, multi-parent and rename provenance, template UUID isolation, unambiguous semantic baseline comparison and negative field tests. An integrity-only PASS must never be displayed as an update-ready status.
