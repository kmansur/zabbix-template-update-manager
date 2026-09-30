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

The default lock lives in the persistent private runtime directory `/var/lib/zabbix-template-update-manager/locks`. An administrator may override it with:

```text
ZTUM_LOCK_DIR=/var/lib/zabbix-template-update-manager/locks
```

The directory must be private and writable by the PHP runtime account.

The current mechanism serializes processes that see the same filesystem lock. ZTUM treats local POSIX storage as the supported baseline. In a multi-node/HA frontend deployment, do **not** assume that merely sharing `/var/lib` proves serialization: the filesystem and mount options must provide reliable cross-node `flock()` behavior and that behavior must be tested in the actual deployment. NFS/CIFS or other remote filesystems are therefore not automatically considered a validated lock backend.

If reliable cross-node locking has not been demonstrated, keep configuration-changing ZTUM operations on one frontend node and record HA serialization as not validated. ZTUM does not claim a distributed database/Redis lock in the current beta.

The server-side evidence/preflight model remains authoritative even with the lock; the lock is defense in depth against concurrent operator workflows, not a replacement for stale-evidence checks.
