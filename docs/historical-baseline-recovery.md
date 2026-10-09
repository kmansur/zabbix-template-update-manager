# Historical baseline recovery: Zabbix 8.0 pre-release

## Why some templates remain blocked

The initial baseline endpoint `upstream-index/initial/8.0.json` currently responds with HTTP 404 because the release-tag based index publisher requires an exact official `8.0.0` tag. Do **not** substitute a beta/RC tag for a stable immutable `8.0.0` reference.

This is only one possible failure. Runtime Git history lookup may also return `ambiguous`, `not_found`, `history_limit_reached` or `time_budget_reached`; an upstream fetch may raise an exception. None is authorization to write.

## Operator workflow

1. Update the module and run `php -l src/Service/TemplateUpdateAnalysisService.php` and `php tests/unit/HistoricalBaselineDiagnosticContractTest.php`.
2. Re-prepare **one** blocked template at a time, without starting an update.
3. Inspect PHP-FPM/Nginx logs for `Historical baseline unresolved for template`, `Initial-release baseline lookup failed`, and `Historical baseline lookup failed`.
4. Classify failures as source-index 404, source UUID mismatch, ambiguous multiple historical contents, transient transport timeout, or bounded search exhaustion.
5. Verify an **official** immutable commit/path/UUID/vendor version and content for each candidate before proposing any mapping change.
6. Re-run preparation only after the evidence is repaired; preserve preflight, rollback backup, explicit review, and post-import validation.

Example (read-only):

```sh
grep -RiE 'Historical baseline unresolved for template|Initial-release baseline lookup failed|Historical baseline lookup failed' /var/log/nginx/ /var/log/php* 2>/dev/null | tail -60
```

Never clear backup/evidence caches blindly, invent baseline metadata, or bypass blocked readiness. For pre-release baselines, establish a verifiable exact revision separately from the stable initial-release index.
