# Test metrics

Template Update Manager reports automation and field validation separately.

## Required CI gates

- PHP syntax;
- manifest/version consistency;
- single controlled-write boundary;
- native Zabbix UI contract;
- deterministic runtime security guard;
- PHP unit/contract tests;
- upstream-index generator validation;
- offline-bundle generator validation.

## Coverage workflow

`.github/workflows/quality-metrics.yml` runs standalone PHP tests under Xdebug and publishes:

- runtime PHP files in `src/` + `actions/`;
- files observed by Xdebug;
- executable lines observed;
- observed executable lines executed.

File reachability is reported separately from line coverage. Files never loaded by the standalone tests are not silently treated as covered.

Views are primarily protected by UI/contract tests and still require browser/field validation. Coverage is a transparency metric, not a replacement for Zabbix runtime testing.
