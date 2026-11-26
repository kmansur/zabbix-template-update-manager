'use strict';

// Regression: a persisted batch that is still running (or blocked) must never
// silently fall through to creating a second batch plan for the same templates.
// Exercises the real createOrResumeOperation implementation of both assets in an
// isolated sandbox. No Zabbix API, network or production state is used.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const labels = {
    request_failed: 'Request failed',
    execution_persisted_blocked: 'BLOCKED',
    execution_persisted_running: 'RUNNING'
};

function extract(asset) {
    const source = fs.readFileSync(path.join(__dirname, '..', 'assets/js', asset), 'utf8');
    const marker = '\tconst createOrResumeOperation = async (entries) => {';
    const start = source.indexOf(marker);
    assert.ok(start >= 0, 'Locate createOrResumeOperation in ' + asset);
    const end = source.indexOf('\n\t};\n', start);
    assert.ok(end > start, 'Locate end of createOrResumeOperation in ' + asset);
    return source.slice(start, end + 4).trim()
        .replace(/^const createOrResumeOperation = /, '').replace(/;\s*$/, '');
}

function sandboxFor(type, existing) {
    const calls = {create: 0};
    const storage = new Map([['ztum.persisted.' + type + '.batch', 'a'.repeat(32)]]);
    const sandbox = {
        labels,
        config: {csrfName: '_csrf_token', batchCreateCsrfToken: 't'},
        persistedOperationId: null,
        window: {localStorage: {
            getItem: key => storage.get(key) ?? null,
            setItem: (key, value) => storage.set(key, value)
        }},
        requestBatchState: async () => existing,
        samePersistedPlan: () => true,
        persistedEntries: entries => entries,
        requireBatchEndpoint: () => 'zabbix.php?action=ztum.batch.create',
        FormData: class { append() {} },
        JSON,
        fetch: async () => {
            calls.create++;
            return {ok: true, json: async () => ({ok: true, operation: {id: 'b'.repeat(32), entries: []}})};
        }
    };
    return {sandbox, calls};
}

for (const [asset, type] of [['ztum-update-batch.js', 'update'], ['ztum-install-batch.js', 'install']]) {
    const implementation = extract(asset);
    const run = (existing) => {
        const {sandbox, calls} = sandboxFor(type, existing);
        const fn = vm.runInNewContext('(' + implementation + ')', sandbox);
        return {promise: fn([{templateId: '1'}]), calls};
    };

    test(asset + ': running persisted plan stops instead of creating a new batch', async () => {
        const {promise, calls} = run({status: 'running', entries: [{state: 'running'}]});
        await assert.rejects(promise, {message: 'RUNNING'});
        assert.equal(calls.create, 0);
    });

    test(asset + ': blocked persisted plan stops', async () => {
        const {promise, calls} = run({status: 'uncertain', entries: [{state: 'uncertain'}]});
        await assert.rejects(promise, {message: 'BLOCKED'});
        assert.equal(calls.create, 0);
    });

    test(asset + ': resumable plan is resumed without creating', async () => {
        const existing = {status: 'running', entries: [{state: 'succeeded'}, {state: 'pending'}]};
        const {promise, calls} = run(existing);
        assert.equal(await promise, existing);
        assert.equal(calls.create, 0);
    });

    test(asset + ': abandoned plan is never resumed', async () => {
        const {promise, calls} = run({status: 'abandoned', entries: [{state: 'pending'}]});
        await promise;
        assert.equal(calls.create, 1);
    });
}
