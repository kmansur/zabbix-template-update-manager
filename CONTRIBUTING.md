# Contributing

External bug reports, field-validation reports and pull requests are welcome while the project remains in beta.

Do not include credentials, private template exports, customer names/addresses or other sensitive operational data.

## Requirements

Changes should:

- follow the existing project architecture and `AGENTS.md`;
- preserve compatibility with supported Zabbix versions;
- use native Zabbix frontend components whenever possible;
- avoid modifications to Zabbix core files;
- preserve the single controlled `configuration.import` boundary;
- preserve fail-closed behavior and global controlled-operation serialization;
- include regression tests/contracts for meaningful fixes when practical;
- include documentation for significant changes.

## Branches

- `main` - integration branch;
- `feature/*` - new functionality;
- `fix/*` - bug fixes;
- `refactor/*` - internal refactoring;
- `docs/*` - documentation;
- `test/*` - tests;
- `ci/*` - automation.

## Validation

Before opening a pull request, run the commands in `AGENTS.md`. The repository provides issue forms for reproducible bugs and sanitized field-validation reports.

See `docs/release-policy.md` for the distinction between development commits, field-test betas and formal tagged releases.
