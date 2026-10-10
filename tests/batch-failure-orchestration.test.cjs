'use strict';

// Integration-level browser orchestration test with isolated dependencies.
// Does not use Zabbix APIs, network, or production batch state.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '..', 'assets/js/ztum-update-batch.js'), 'utf8'
);
const start = source.indexOf('\tconst runExecution = async () => {');
const end = source.indexOf('\n\tconst retryFailedPreparation = async () => {', start);
assert.ok(start >= 0 && end > start, 'Locate real batch execution implementation');
const extracted = source.slice(start, end).trim().replace(/^const runExecution = /, '').replace(/;\s*$/, '');

async function runScenario(secondOutcome) {
    const requests = [];
    const labels = {
        request_failed: 'Request failed', execution_running: 'Running',
        execution_failed: 'Stopped on first failure', execution_completed: 'Completed',
        updated: 'Updated', failed: 'Failed', not_attempted: 'Not attempted',
        executing: 'Updating', yes: 'Yes', no: 'No', resumed_success: 'Previously completed'
    };
    const elements = {
        'ztum-batch-confirm': {checked: true, disabled: false},
        'ztum-batch-update-submit': {disabled: false}
    };
    const rendered = {};
    const sandbox = {
        executionStarted: false,
        fullyPrepared: true,
        executionEntries: () => ['101', '102', '103'].map(templateId => ({
            templateId, evidence: templateId.repeat(64).slice(0, 64), manualOverride: true
        })),
        byId: id => elements[id] ?? (elements[id] = {disabled: false}),
        createOrResumeOperation: async () => ({
            entries: ['101', '102', '103'].map(id => ({subject: 'template-' + id, state: 'pending'}))
        }),
        reviewEvidence: new Map(),
        updateReviewedSelectAll: () => {},
        persistedOperationId: 'simulated-batch',
        executeOne: async (_operationId, templateId) => {
            requests.push(templateId);
            if (templateId === '102') {
                if (secondOutcome === 'throws') {
                    throw new Error('Injected request failure before import');
                }
                return {status: 'blocked', write_performed: false, reason: 'Injected pre-import failure'};
            }
            return {status: 'updated', write_performed: true,
                candidate: {vendor_version: 'fixture-2'}, validation: {status: 'validated'}};
        },
        setStateText: (id, value) => {rendered[id] = value;},
        setText: (id, value) => {rendered[id] = value;},
        labels,
        config: {templateIds: ['101', '102', '103']},
        Map
    };
    const fn = vm.runInNewContext('(' + extracted + ')', sandbox, {timeout: 1000});
    await fn();
    assert.deepEqual(requests, ['101', '102'], 'The third request must not execute');
    assert.equal(rendered['ztum-batch-exec-updated'], '1');
    assert.equal(rendered['ztum-batch-exec-failed'], '1');
    assert.equal(rendered['ztum-batch-exec-not-attempted'], '1');
    assert.equal(rendered['ztum-batch-exec-status'], labels.execution_failed);
    assert.equal(rendered['ztum-execution-103'], labels.not_attempted);
    assert.equal(rendered['ztum-batch-exec-write'], labels.yes);
}

(async () => {
    await runScenario('blocked');
    process.stdout.write('PASS: denied pre-import result stops later requests\n');
    await runScenario('throws');
    process.stdout.write('PASS: rejected HTTP-like request stops later requests\n');
    process.stdout.write('PASS: UI counts and failure status match stop-on-first-failure\n');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
