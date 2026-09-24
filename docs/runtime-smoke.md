# Disposable Zabbix runtime smoke

ZTUM CI starts isolated official Zabbix container stacks for the supported major generations and exercises the real frontend with Chromium.

## Scope

The smoke test:

- starts PostgreSQL, Zabbix server and Zabbix web containers;
- mounts the current ZTUM checkout as a frontend module;
- registers/enables the module through the native Zabbix API;
- logs into the frontend as the default disposable Super Admin;
- opens the ZTUM catalog;
- verifies the native Name filter can be submitted;
- renders the catalog in dark and light themes;
- opens the ZTUM operation-history page;
- rejects PHP-fatal/browser-error markers;
- stores screenshots as workflow artifacts.

## Matrix

- Zabbix 7.0 official Alpine images + PostgreSQL 16.
- Zabbix 8.0 trunk official Alpine images + PostgreSQL 18 while 8.0 remains on the upstream trunk image line.

The exact images are declared in `.github/workflows/ci.yml`.

## Local execution

Docker with Compose v2 and Node.js are required.

```bash
npm install --ignore-scripts --no-audit --no-fund
npx playwright install chromium

export POSTGRES_IMAGE=postgres:16-alpine
export ZABBIX_SERVER_IMAGE=zabbix/zabbix-server-pgsql:alpine-7.0-latest
export ZABBIX_WEB_IMAGE=zabbix/zabbix-web-nginx-pgsql:alpine-7.0-latest
export ZTUM_SMOKE_PORT=18080

docker compose -f tests/runtime-smoke/compose.yaml up -d
ZTUM_SMOKE_BASE_URL=http://127.0.0.1:18080 node tests/browser/full-zabbix-smoke.mjs
docker compose -f tests/runtime-smoke/compose.yaml down -v --remove-orphans
```

## Boundary

This smoke is deliberately non-destructive. It increases confidence that the module is discoverable and renderable against real supported frontend generations, but it is **not** evidence that controlled update/install/rollback write paths have been field-validated.

The authoritative real-environment matrix remains `docs/lab-test-plan.md` and the GitHub 1.0 readiness issue.
