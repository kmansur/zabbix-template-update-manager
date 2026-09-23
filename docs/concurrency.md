# Controlled-operation concurrency

Template Update Manager serializes configuration-changing workflows with a global non-blocking filesystem lock.

The lock is acquired in the write controller **before** the fresh controlled preflight and is held through import and post-operation validation.

Covered operations:

- controlled update;
- request-bounded batch update;
- controlled installation;
- request-bounded batch installation;
- rollback.

A second concurrent operation on the same frontend host fails closed with:

```text
Another Template Update Manager configuration operation is already in progress.
```

No second preflight or configuration write is started while the lock is held.

## Scope

The default lock lives in a private ZTUM directory below the PHP temporary directory. An administrator may set:

```text
ZTUM_LOCK_DIR=/var/lib/zabbix-template-update-manager/locks
```

The directory must be private and writable by the PHP runtime account.

The current mechanism serializes processes that see the same filesystem lock. In a multi-node/HA frontend deployment, administrators must point every node to storage that provides reliable cross-node `flock()` semantics, or treat cross-node serialization as not yet proven. ZTUM does not claim a distributed database/Redis lock in the current beta.

The server-side evidence/preflight model remains authoritative even with the lock; the lock is defense in depth against concurrent operator workflows, not a replacement for stale-evidence checks.
