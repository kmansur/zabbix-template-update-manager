import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {dirname, resolve} from 'node:path';
import {chromium} from 'playwright';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const browser = await chromium.launch({headless: true});

const baseLabels = {
  ready: 'Ready',
  blocked: 'Blocked',
  pending: 'Pending',
  processing: 'Processing...',
  request_failed: 'Request failed',
  complete: 'Preparation complete.',
  stopped: 'Preparation stopped.',
  stopping: 'Stopping',
  progress: 'Preparing {current} of {total}...',
  yes: 'Yes',
  no: 'No'
};

async function newPage() {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  return {page, errors};
}

async function testInstallBlockedFlow() {
  const {page, errors} = await newPage();
  await page.setContent(`
    <span id="ztum-install-summary-completed">0</span>
    <span id="ztum-install-summary-ready">0</span>
    <span id="ztum-install-summary-blocked">0</span>
    <span id="ztum-install-batch-progress-text"></span>
    <button id="ztum-install-batch-stop">Stop</button>
    <input id="ztum-install-batch-confirm" type="checkbox" disabled>
    <button id="ztum-install-batch-submit" disabled>Install</button>
    <span id="ztum-install-no-ready-message"></span>
    <span id="ztum-install-exec-status"></span>
    <span id="ztum-install-exec-installed">0</span>
    <span id="ztum-install-exec-failed">0</span>
    <span id="ztum-install-exec-not-attempted">0</span>
    <span id="ztum-install-exec-write">No</span>
    <span id="ztum-install-exec-uncertain">0</span>
    <span id="ztum-install-exec-notice"></span>
    <span id="ztum-install-name-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-version-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-preflight-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-category-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-required-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-missing-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-reason-deadbeefdeadbeefdeadbeefdeadbeef"></span>
    <span id="ztum-install-execution-deadbeefdeadbeefdeadbeefdeadbeef"></span>
  `);

  await page.evaluate(() => {
    window.fetch = async () => ({
      ok: true,
      json: async () => ({
        ok: true,
        item: {
          name: 'Fixture template',
          available_version: '8.0-1',
          preflight_status: 'blocked_candidate',
          category: 'blocked',
          evidence_sha256: '',
          reason: 'missing_template_dependencies',
          required_dependencies: ['Base template'],
          missing_dependencies: ['Base template'],
          reference_issues: []
        }
      })
    });
  });

  await page.addScriptTag({path: resolve(root, 'assets/js/ztum-install-batch.js')});
  await page.evaluate(({labels}) => {
    window.ZTUMInstallBatchInit({
      uuids: ['deadbeefdeadbeefdeadbeefdeadbeef'],
      prepareOneUrl: '/prepare',
      executeOneUrl: '/execute',
      csrfName: '_csrf',
      prepareCsrfToken: 'prepare-token',
      executeCsrfToken: 'execute-token',
      statusClasses: {}
    }, {
      ...labels,
      execution_ready: 'Ready for execution',
      execution_running: 'Running',
      execution_completed: 'Completed',
      execution_stopped: 'Stopped',
      execution_stopped_uncertain: 'Stopped uncertain',
      executing: 'Installing...',
      installed: 'Installed',
      import_failed: 'Import failed',
      validation_failed: 'Validation failed',
      validation_reasons: 'Validation reasons',
      remaining_differences: 'remaining differences',
      raw_differences: 'raw differences',
      ignored_shared_differences: 'ignored differences',
      create_only_validated: 'Validated',
      failed: 'Failed',
      not_attempted: 'Not attempted',
      import_failure_notice: 'Import failure',
      request_failure_notice: 'Request failure',
      execution_unavailable: 'Unavailable',
      no_ready: 'No Ready templates ({blocked} blocked).'
    });
  }, {labels: baseLabels});

  await page.waitForFunction(() =>
    document.getElementById('ztum-install-summary-completed')?.textContent === '1'
  );

  assert.equal(await page.textContent('#ztum-install-summary-blocked'), '1');
  assert.equal(await page.isDisabled('#ztum-install-batch-confirm'), true);
  assert.match(await page.textContent('#ztum-install-no-ready-message'), /No Ready templates/);
  assert.deepEqual(errors, []);
  await page.close();
}

async function testUpdateEmptyFlow() {
  const {page, errors} = await newPage();
  await page.setContent(`
    <span id="ztum-summary-completed">0</span>
    <span id="ztum-summary-ready">0</span>
    <span id="ztum-summary-review">0</span>
    <span id="ztum-summary-conflict">0</span>
    <span id="ztum-summary-blocked">0</span>
    <span id="ztum-batch-progress-text"></span>
    <span id="ztum-batch-execution-state"></span>
    <span id="ztum-batch-exec-status"></span>
    <span id="ztum-batch-exec-updated">0</span>
    <span id="ztum-batch-exec-failed">0</span>
    <span id="ztum-batch-exec-not-attempted">0</span>
    <span id="ztum-batch-exec-write">No</span>
    <button id="ztum-reviewed-select-all"></button>
    <button id="ztum-reviewed-clear-all"></button>
    <input id="ztum-batch-confirm" type="checkbox" disabled>
    <input id="ztum-batch-confirm-local-overwrite" type="checkbox" disabled>
    <button id="ztum-batch-update-submit" disabled>Update</button>
    <button id="ztum-batch-retry-failed" disabled>Retry</button>
    <button id="ztum-batch-stop">Stop</button>
  `);

  await page.addScriptTag({path: resolve(root, 'assets/js/ztum-update-batch.js')});
  await page.evaluate(({labels}) => {
    window.ZTUMUpdateBatchInit({
      templateIds: [],
      prepareOneUrl: '/prepare',
      executeOneUrl: '/execute',
      compareUrl: '/compare',
      csrfName: '_csrf',
      prepareCsrfToken: 'prepare-token',
      executeCsrfToken: 'execute-token',
      maxHistoricalContinuationRequests: 2,
      statusClasses: {}
    }, {
      ...labels,
      review: 'Manual review',
      conflict: 'Conflict',
      history_continuing: 'Continuing {attempt}/{max}',
      execution_waiting: 'Waiting',
      execution_waiting_progress: 'Waiting {completed}/{total}',
      execution_available: 'Available {ready}/{review}',
      execution_none: 'No executable templates',
      execution_review_only: 'Review only',
      execution_stopped: 'Stopped',
      retrying_failed: 'Retrying',
      retry_complete: 'Retry complete',
      execution_ready: 'Ready',
      select_reviewed: 'Select reviewed',
      select_all_reviewed: 'Select all reviewed',
      clear_all_reviewed: 'Clear reviewed',
      review_batch_eligible: 'Reviewed eligible',
      review_overwrite_eligible: 'Overwrite eligible',
      review_details: 'Review details',
      review_individual_only: 'Individual only',
      execution_running: 'Running',
      execution_completed: 'Completed',
      execution_failed: 'Failed',
      executing: 'Updating',
      updated: 'Updated',
      failed: 'Failed',
      not_attempted: 'Not attempted'
    });
  }, {labels: baseLabels});

  await page.waitForFunction(() =>
    document.getElementById('ztum-batch-progress-text')?.textContent === 'Preparation complete.'
  );

  assert.equal(await page.textContent('#ztum-batch-exec-status'), 'No executable templates');
  assert.equal(await page.isDisabled('#ztum-batch-update-submit'), true);
  assert.deepEqual(errors, []);
  await page.close();
}

try {
  await testInstallBlockedFlow();
  await testUpdateEmptyFlow();
  console.log('Browser batch-asset smoke tests passed.');
}
finally {
  await browser.close();
}
