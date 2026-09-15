# Security Policy

Zabbix Template Update Manager executes inside the Zabbix frontend environment.

Security is therefore a core project requirement.

## Principles

- Repository access is read-only by default.
- Discovery operations never modify Zabbix configuration.
- Comparison operations never modify Zabbix configuration.
- Template updates require explicit administrator confirmation.
- Backups are required before future write operations.
- User input must never be passed directly to shell commands.
- Repository URLs and Git references must be validated.
- Credentials must never be committed to the repository.
- Secrets must never be stored in logs.
