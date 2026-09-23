## Summary

Describe the problem and the smallest safe change that solves it.

## Safety checklist

- [ ] No Zabbix core file is modified.
- [ ] No new direct database write is introduced.
- [ ] No new `configuration.import` call site is introduced.
- [ ] Fail-closed behavior is preserved.
- [ ] Zabbix 7.x / 8.x compatibility was considered.
- [ ] Native Zabbix UI conventions are preserved.
- [ ] Tests/contracts were added or updated where practical.
- [ ] User-visible behavior is documented.
- [ ] No secrets/customer-identifying data are included.

## Validation

List automated tests and any real Zabbix field validation performed.
