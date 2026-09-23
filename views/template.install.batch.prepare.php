<?php

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(
					_('Back to template catalog'),
					(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['uuids'] === []) {
	$page->addItem(new CTag('p', true, _('Batch installation preparation returned no selected templates.')))->show();
	return;
}

$selectedCount = count($data['uuids']);

$summaryTable = (new CTableInfo())
	->setHeader([_('Selected'), _('Completed'), _('Ready'), _('Blocked')])
	->addRow([
		$selectedCount,
		(new CSpan('0'))->setId('ztum-install-summary-completed'),
		(new CSpan('0'))->setId('ztum-install-summary-ready'),
		(new CSpan('0'))->setId('ztum-install-summary-blocked')
	]);

$progressText = (new CSpan(sprintf(_('Preparing %1$d of %2$d installations...'), 0, $selectedCount)))
	->setId('ztum-install-batch-progress-text');

$stopButton = (new CButton('ztum-install-batch-stop', _('Stop after current template')))
	->setId('ztum-install-batch-stop')
	->addClass(ZBX_STYLE_BTN_ALT);

$page
	->addItem(new CTag('h4', true, _('Batch installation preparation')))
	->addItem($summaryTable)
	->addItem(new CDiv([$progressText, ' ', $stopButton]))
	->addItem(new CTag('p', true, _(
		'Each selected official template is prepared in its own request. Preparation is read-only and never imports Zabbix configuration.'
	)))
	->addItem(new CTag('p', true, _(
		'Candidates with missing linked-template dependencies remain Blocked, even when the dependency is also selected. Install dependencies first, then prepare dependent templates again.'
	)));

$table = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Available'),
		_('Preflight'),
		_('Batch class'),
		_('Required dependencies'),
		_('Missing dependencies'),
		_('Reason'),
		_('Execution')
	]);

foreach ($data['uuids'] as $uuid) {
	$table->addRow(
		(new CRow([
			(new CSpan($uuid))->setId('ztum-install-name-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-version-'.$uuid),
			(new CSpan(_('Pending')))->setId('ztum-install-preflight-'.$uuid),
			(new CSpan(_('Pending')))->setId('ztum-install-category-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-required-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-missing-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-reason-'.$uuid),
			(new CSpan(_('Pending')))->setId('ztum-install-execution-'.$uuid)
		]))->setId('ztum-install-row-'.$uuid)
	);
}

$page
	->addItem(new CTag('h4', true, _('Prepared installation candidates')))
	->addItem($table);

$confirm = (new CCheckBox('confirm', '1'))
	->setId('ztum-install-batch-confirm')
	->setLabel(_('I reviewed the completed installation plan and want to install the Ready templates sequentially.'))
	->setEnabled(false);

$submit = (new CButton('ztum-install-batch-submit', _('Install ready templates')))
	->setId('ztum-install-batch-submit')
	->setEnabled(false);

$noReadyMessage = (new CSpan(''))
	->setId('ztum-install-no-ready-message');

$executionSummary = (new CTableInfo())
	->setHeader([_('Status'), _('Installed'), _('Failed'), _('Not attempted'), _('Any configuration write')])
	->addRow([
		(new CSpan(_('Waiting for preparation')))->setId('ztum-install-exec-status'),
		(new CSpan('0'))->setId('ztum-install-exec-installed'),
		(new CSpan('0'))->setId('ztum-install-exec-failed'),
		(new CSpan('0'))->setId('ztum-install-exec-not-attempted'),
		(new CSpan(_('No')))->setId('ztum-install-exec-write')
	]);

$page
	->addItem(new CTag('h4', true, _('Controlled sequential installation')))
	->addItem(new CTag('p', true, _(
		'Only Ready candidates are executed. Each template uses its own HTTP request, reruns the complete installation preflight immediately before import and is validated before the next template begins. Execution stops on the first failure and no automatic uninstall is performed.'
	)))
	->addItem(new CDiv([$confirm, ' ', $submit]))
	->addItem(new CDiv($noReadyMessage))
	->addItem($executionSummary);

$prepareOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.install_prepare_one')
	->getUrl();

$executeOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.install_execute_one')
	->getUrl();

$jsConfig = json_encode([
	'uuids' => array_values(array_map('strval', $data['uuids'])),
	'prepareOneUrl' => $prepareOneUrl,
	'executeOneUrl' => $executeOneUrl,
	'csrfName' => CSRF_TOKEN_NAME,
	'prepareCsrfToken' => CCsrfTokenHelper::get('ztum.templates.install_prepare_one'),
	'executeCsrfToken' => CCsrfTokenHelper::get('ztum.templates.install_execute_one')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$jsLabels = json_encode([
	'ready' => _('Ready'),
	'blocked' => _('Blocked'),
	'pending' => _('Pending'),
	'processing' => _('Processing...'),
	'request_failed' => _('Request failed'),
	'complete' => _('Preparation complete.'),
	'stopped' => _('Preparation stopped. Reload or return to the catalog to prepare the full set.'),
	'stopping' => _('Stop requested; the current template will finish first.'),
	'progress' => _('Preparing {current} of {total} installations...'),
	'execution_ready' => _('Ready for execution'),
	'execution_running' => _('Running'),
	'execution_completed' => _('Completed'),
	'execution_stopped' => _('Stopped on first failure'),
	'executing' => _('Installing...'),
	'installed' => _('Installed and validated'),
	'failed' => _('Failed'),
	'not_attempted' => _('Not attempted'),
	'yes' => _('Yes'),
	'no' => _('No'),
	'execution_unavailable' => _('Unavailable — no Ready templates'),
	'no_ready' => _('No templates are eligible for installation. {blocked} selected template(s) were blocked during safety analysis. Review the blocked reasons above or return to the catalog.')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$script = <<<'JS'
(() => {
	const config = __CONFIG__;
	const labels = __LABELS__;
	const counts = {ready: 0, blocked: 0};
	const readyEvidence = new Map();
	let completed = 0;
	let stopRequested = false;
	let fullyPrepared = false;
	let executionStarted = false;

	const byId = (id) => document.getElementById(id);
	const setText = (id, value) => {
		const element = byId(id);
		if (element !== null) {
			element.textContent = value;
		}
	};

	const updateSummary = () => {
		setText('ztum-install-summary-completed', String(completed));
		setText('ztum-install-summary-ready', String(counts.ready));
		setText('ztum-install-summary-blocked', String(counts.blocked));
	};

	const progress = (current, text = null) => {
		setText(
			'ztum-install-batch-progress-text',
			text ?? labels.progress
				.replace('{current}', String(current))
				.replace('{total}', String(config.uuids.length))
		);
	};

	const listText = (value) => Array.isArray(value) && value.length > 0 ? value.join(', ') : '—';

	const applyItem = (uuid, item) => {
		const category = item.category === 'ready' ? 'ready' : 'blocked';
		counts[category]++;

		setText('ztum-install-name-' + uuid, item.name || uuid);
		setText('ztum-install-version-' + uuid, item.available_version || '—');
		setText('ztum-install-preflight-' + uuid, item.preflight_status || '—');
		setText('ztum-install-category-' + uuid, labels[category]);
		setText('ztum-install-required-' + uuid, listText(item.required_dependencies));
		setText('ztum-install-missing-' + uuid, listText(item.missing_dependencies));
		const referenceIssues = Array.isArray(item.reference_issues)
			? item.reference_issues
				.map((issue) => {
					const code = issue?.code || '';
					const reference = issue?.reference || '';
					return code !== '' ? code + (reference !== '' ? ': ' + reference : '') : '';
				})
				.filter((value) => value !== '')
			: [];

		setText(
			'ztum-install-reason-' + uuid,
			referenceIssues.length > 0 ? referenceIssues.join(' | ') : (item.reason || '—')
		);
		setText('ztum-install-execution-' + uuid, category === 'ready' ? labels.ready : labels.blocked);

		if (category === 'ready' && /^[a-f0-9]{64}$/.test(item.evidence_sha256 || '')) {
			readyEvidence.set(uuid, item.evidence_sha256);
		}
	};

	const applyRequestFailure = (uuid, error) => {
		counts.blocked++;
		setText('ztum-install-preflight-' + uuid, 'request_failed');
		setText('ztum-install-category-' + uuid, labels.blocked);
		setText('ztum-install-reason-' + uuid, error?.message || labels.request_failed);
		setText('ztum-install-execution-' + uuid, labels.blocked);
	};

	const prepareOne = async (uuid) => {
		const body = new FormData();
		body.append(config.csrfName, config.prepareCsrfToken);
		body.append('uuid', uuid);

		const response = await fetch(config.prepareOneUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
			headers: {'X-Requested-With': 'XMLHttpRequest'}
		});

		if (!response.ok) {
			throw new Error('HTTP ' + response.status);
		}

		const payload = await response.json();
		if (!payload || payload.ok !== true || !payload.item) {
			throw new Error(payload?.error || labels.request_failed);
		}

		return payload.item;
	};

	const executeOne = async (uuid, evidence) => {
		const body = new FormData();
		body.append(config.csrfName, config.executeCsrfToken);
		body.append('uuid', uuid);
		body.append('evidence_sha256', evidence);
		body.append('confirm', '1');

		const response = await fetch(config.executeOneUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
			headers: {'X-Requested-With': 'XMLHttpRequest'}
		});

		if (!response.ok) {
			throw new Error('HTTP ' + response.status);
		}

		const payload = await response.json();
		if (!payload || payload.ok !== true || !payload.result) {
			throw new Error(payload?.error || labels.request_failed);
		}

		return payload.result;
	};

	const updateExecutionState = () => {
		const confirm = byId('ztum-install-batch-confirm');
		const submit = byId('ztum-install-batch-submit');
		const canExecute = fullyPrepared && readyEvidence.size > 0 && !executionStarted;

		confirm.disabled = !canExecute;
		if (!canExecute && !executionStarted) {
			confirm.checked = false;
		}
		submit.disabled = !(canExecute && confirm.checked);

		if (fullyPrepared && readyEvidence.size > 0 && !executionStarted) {
			setText('ztum-install-exec-status', labels.execution_ready);
			setText('ztum-install-exec-not-attempted', String(readyEvidence.size));
			setText('ztum-install-no-ready-message', '');
		}
		else if (fullyPrepared && readyEvidence.size === 0 && !executionStarted) {
			setText('ztum-install-exec-status', labels.execution_unavailable);
			setText('ztum-install-exec-not-attempted', '0');
			setText(
				'ztum-install-no-ready-message',
				labels.no_ready.replace('{blocked}', String(counts.blocked))
			);
		}
	};

	const runExecution = async () => {
		if (executionStarted || !fullyPrepared || readyEvidence.size === 0
				|| !byId('ztum-install-batch-confirm').checked) {
			return;
		}

		executionStarted = true;
		byId('ztum-install-batch-confirm').disabled = true;
		byId('ztum-install-batch-submit').disabled = true;

		const entries = Array.from(readyEvidence.entries());
		let installed = 0;
		let failed = 0;
		let notAttempted = entries.length;
		let anyWrite = false;
		let stopped = false;

		setText('ztum-install-exec-status', labels.execution_running);
		setText('ztum-install-exec-installed', '0');
		setText('ztum-install-exec-failed', '0');
		setText('ztum-install-exec-not-attempted', String(notAttempted));
		setText('ztum-install-exec-write', labels.no);

		for (let index = 0; index < entries.length; index++) {
			const [uuid, evidence] = entries[index];
			setText('ztum-install-execution-' + uuid, labels.executing);

			try {
				const result = await executeOne(uuid, evidence);

				if (result.write_performed) {
					anyWrite = true;
					setText('ztum-install-exec-write', labels.yes);
				}

				if (result.status !== 'installed') {
					failed++;
					notAttempted = entries.length - index - 1;
					setText(
						'ztum-install-execution-' + uuid,
						labels.failed + (result.status ? ' (' + result.status + ')' : '')
					);

					for (let pending = index + 1; pending < entries.length; pending++) {
						setText('ztum-install-execution-' + entries[pending][0], labels.not_attempted);
					}

					stopped = true;
					break;
				}

				installed++;
				notAttempted = entries.length - index - 1;
				const validation = result.validation?.status || 'validated';
				setText('ztum-install-execution-' + uuid, labels.installed + ' (' + validation + ')');
			}
			catch (error) {
				failed++;
				notAttempted = entries.length - index - 1;
				setText(
					'ztum-install-execution-' + uuid,
					labels.request_failed + (error?.message ? ': ' + error.message : '')
				);

				for (let pending = index + 1; pending < entries.length; pending++) {
					setText('ztum-install-execution-' + entries[pending][0], labels.not_attempted);
				}

				stopped = true;
				break;
			}

			setText('ztum-install-exec-installed', String(installed));
			setText('ztum-install-exec-failed', String(failed));
			setText('ztum-install-exec-not-attempted', String(notAttempted));
		}

		setText('ztum-install-exec-installed', String(installed));
		setText('ztum-install-exec-failed', String(failed));
		setText('ztum-install-exec-not-attempted', String(notAttempted));
		setText('ztum-install-exec-write', anyWrite ? labels.yes : labels.no);
		setText(
			'ztum-install-exec-status',
			stopped ? labels.execution_stopped : labels.execution_completed
		);
	};

	byId('ztum-install-batch-confirm').addEventListener('change', updateExecutionState);
	byId('ztum-install-batch-submit').addEventListener('click', (event) => {
		event.preventDefault();
		runExecution();
	});

	const stopButton = byId('ztum-install-batch-stop');
	stopButton.addEventListener('click', () => {
		stopRequested = true;
		stopButton.disabled = true;
		progress(completed, labels.stopping);
	});

	const run = async () => {
		updateSummary();

		for (let index = 0; index < config.uuids.length; index++) {
			if (stopRequested) {
				break;
			}

			const uuid = config.uuids[index];
			progress(index + 1);
			setText('ztum-install-preflight-' + uuid, labels.processing);
			setText('ztum-install-category-' + uuid, labels.processing);
			setText('ztum-install-reason-' + uuid, '—');

			try {
				applyItem(uuid, await prepareOne(uuid));
			}
			catch (error) {
				applyRequestFailure(uuid, error);
			}

			completed++;
			updateSummary();
		}

		stopButton.disabled = true;
		fullyPrepared = !stopRequested && completed === config.uuids.length;
		progress(completed, fullyPrepared ? labels.complete : labels.stopped);
		updateExecutionState();
	};

	run();
})();
JS;

$script = str_replace(
	['__CONFIG__', '__LABELS__'],
	[$jsConfig, $jsLabels],
	$script
);

$page
	->addItem((new CScriptTag($script))->setOnDocumentReady())
	->show();
