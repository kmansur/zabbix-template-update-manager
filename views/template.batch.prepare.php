<?php

$categoryLabels = [
	'ready' => _('Ready'),
	'review' => _('Manual review'),
	'conflict' => _('Conflict / local overwrite'),
	'blocked' => _('Blocked')
];

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(
		_('Back to template updates'),
		(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
	));

if ($data['error'] !== null || $data['templateids'] === [] || $data['templates'] === []) {
	$page->addItem(new CTag('p', true, $data['error'] ?? _('Batch preparation returned no selected templates.')))->show();
	return;
}

$templateById = [];
foreach ($data['templates'] as $template) {
	$templateById[(string) $template['templateid']] = $template;
}

$selectedCount = count($data['templateids']);

$summaryTable = (new CTableInfo())
	->setHeader([_('Selected'), _('Completed'), _('Ready'), _('Manual review'), _('Conflict'), _('Blocked')])
	->addRow([
		$selectedCount,
		(new CSpan('0'))->setId('ztum-summary-completed'),
		(new CSpan('0'))->setId('ztum-summary-ready'),
		(new CSpan('0'))->setId('ztum-summary-review'),
		(new CSpan('0'))->setId('ztum-summary-conflict'),
		(new CSpan('0'))->setId('ztum-summary-blocked')
	]);

$progressText = (new CSpan(sprintf(_('Preparing %1$d of %2$d templates...'), 0, $selectedCount)))
	->setId('ztum-batch-progress-text');

$stopButton = (new CButton('ztum-batch-stop', _('Stop after current template')))
	->setId('ztum-batch-stop')
	->addClass(ZBX_STYLE_BTN_ALT);

$page
	->addItem(new CTag('h4', true, _('Batch preparation')))
	->addItem($summaryTable)
	->addItem(new CDiv([
		$progressText,
		' ',
		$stopButton
	]))
	->addItem(new CTag('p', true, _(
		'Each template is prepared in its own request. Preparation may create or refresh rollback evidence, but it never imports Zabbix configuration.'
	)));

$table = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Installed'),
		_('Available'),
		_('Linked hosts'),
		_('Readiness'),
		_('Batch class'),
		_('Reason')
	]);

foreach ($data['templateids'] as $templateId) {
	$template = $templateById[(string) $templateId] ?? [];
	$name = (string) ($template['name'] ?? $templateId);
	$installed = (string) ($template['vendor_version'] ?? '');
	$hostCount = (int) ($template['host_count'] ?? 0);

	$table->addRow(
		(new CRow([
			$name,
			$installed !== '' ? $installed : '—',
			(new CSpan('—'))->setId('ztum-available-'.$templateId),
			$hostCount,
			(new CSpan(_('Pending')))->setId('ztum-readiness-'.$templateId),
			(new CSpan(_('Pending')))->setId('ztum-category-'.$templateId),
			(new CSpan('—'))->setId('ztum-reason-'.$templateId)
		]))->setId('ztum-row-'.$templateId)
	);
}

$page
	->addItem(new CTag('h4', true, _('Prepared templates')))
	->addItem($table);

$updateAction = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.batch_update')
	->getUrl();

$readyInputs = (new CDiv())->setId('ztum-batch-ready-inputs');
$executionState = (new CSpan(_('Waiting for preparation.')))
	->setId('ztum-batch-execution-state');
$confirm = (new CCheckBox('confirm', '1'))
	->setId('ztum-batch-confirm')
	->setLabel(_('I reviewed the completed batch plan and want to update the Ready templates sequentially.'))
	->setEnabled(false);
$submit = (new CSubmitButton(_('Update ready templates')))
	->setId('ztum-batch-update-submit')
	->setEnabled(false);

$form = (new CForm('post'))
	->setId('ztum-batch-update-form')
	->setAction($updateAction)
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.templates.batch_update')))->removeId())
	->addItem($readyInputs)
	->addItem([$confirm, $submit]);

$page
	->addItem(new CTag('h4', true, _('Controlled sequential execution')))
	->addItem(new CTag('p', true, _(
		'After preparation completes, only Ready templates with valid bound preflight evidence can be submitted. Manual review, Conflict and Blocked rows never enter the execution set. Each submitted template reruns fresh preflight immediately before import and execution stops on the first failure.'
	)))
	->addItem(new CTag('p', true, $executionState))
	->addItem($form);

$prepareOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.prepare_one')
	->getUrl();

$jsConfig = json_encode([
	'templateIds' => array_values(array_map('strval', $data['templateids'])),
	'prepareOneUrl' => $prepareOneUrl,
	'csrfName' => CSRF_TOKEN_NAME,
	'csrfToken' => CCsrfTokenHelper::get('ztum.templates.prepare_one')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$jsLabels = json_encode([
	'ready' => _('Ready'),
	'review' => _('Manual review'),
	'conflict' => _('Conflict / local overwrite'),
	'blocked' => _('Blocked'),
	'pending' => _('Pending'),
	'processing' => _('Processing...'),
	'request_failed' => _('Request failed'),
	'complete' => _('Preparation complete.'),
	'stopped' => _('Preparation stopped. Reload or return to the selection review to prepare the full set.'),
	'stopping' => _('Stop requested; the current template will finish first.'),
	'progress' => _('Preparing {current} of {total} templates...'),
	'execution_waiting' => _('Waiting for preparation.'),
	'execution_available' => _('Available — {ready} Ready template(s).'),
	'execution_none' => _('Unavailable — no Ready templates.'),
	'execution_stopped' => _('Unavailable — preparation stopped.')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$script = <<<'JS'
(() => {
	const config = __CONFIG__;
	const labels = __LABELS__;
	const counts = {ready: 0, review: 0, conflict: 0, blocked: 0};
	const readyEvidence = new Map();
	let completed = 0;
	let stopRequested = false;
	let fullyPrepared = false;

	const byId = (id) => document.getElementById(id);
	const setText = (id, value) => {
		const element = byId(id);
		if (element !== null) {
			element.textContent = value;
		}
	};

	const updateSummary = () => {
		setText('ztum-summary-completed', String(completed));
		for (const category of ['ready', 'review', 'conflict', 'blocked']) {
			setText('ztum-summary-' + category, String(counts[category]));
		}
	};

	const progress = (current, text = null) => {
		const message = text ?? labels.progress
			.replace('{current}', String(current))
			.replace('{total}', String(config.templateIds.length));
		setText('ztum-batch-progress-text', message);
	};

	const normalizeEvidence = (value) => typeof value === 'string'
		? value.trim().toLowerCase()
		: '';

	const isValidEvidence = (value) => /^[a-f0-9]{64}$/.test(value);

	const addReadyInput = (templateId, evidence) => {
		const container = byId('ztum-batch-ready-inputs');
		const index = readyEvidence.size;

		const idInput = document.createElement('input');
		idInput.type = 'hidden';
		idInput.name = 'templateids[' + index + ']';
		idInput.value = templateId;
		container.appendChild(idInput);

		const evidenceInput = document.createElement('input');
		evidenceInput.type = 'hidden';
		evidenceInput.name = 'evidence[' + templateId + ']';
		evidenceInput.value = evidence;
		container.appendChild(evidenceInput);

		readyEvidence.set(templateId, evidence);
	};

	const applyItem = (templateId, item) => {
		let category = ['ready', 'review', 'conflict', 'blocked'].includes(item.category)
			? item.category
			: 'blocked';
		let readinessStatus = item.readiness_status || '—';
		let reason = item.reason || '—';
		const evidence = normalizeEvidence(item.evidence_sha256 || '');

		// Browser-side consistency guard: a row cannot be displayed/count as Ready
		// unless it also carries the exact evidence shape required for execution.
		if (category === 'ready' && !isValidEvidence(evidence)) {
			category = 'blocked';
			readinessStatus = 'blocked_invalid_evidence';
			reason = 'invalid_preflight_evidence';
		}

		counts[category]++;
		setText('ztum-available-' + templateId, item.available_version || '—');
		setText('ztum-readiness-' + templateId, readinessStatus);
		setText('ztum-category-' + templateId, labels[category] || labels.blocked);
		setText('ztum-reason-' + templateId, reason);

		if (category === 'ready') {
			addReadyInput(templateId, evidence);
		}
	};

	const applyRequestFailure = (templateId, error) => {
		counts.blocked++;
		setText('ztum-readiness-' + templateId, 'request_failed');
		setText('ztum-category-' + templateId, labels.blocked);
		setText('ztum-reason-' + templateId, error?.message || labels.request_failed);
	};

	const prepareOne = async (templateId) => {
		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
		body.append('templateid', templateId);

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

	const updateExecutionState = () => {
		const confirm = byId('ztum-batch-confirm');
		const submit = byId('ztum-batch-update-submit');
		const canExecute = fullyPrepared && readyEvidence.size > 0;

		if (!fullyPrepared) {
			setText('ztum-batch-execution-state',
				stopRequested ? labels.execution_stopped : labels.execution_waiting);
		}
		else if (readyEvidence.size > 0) {
			setText('ztum-batch-execution-state',
				labels.execution_available.replace('{ready}', String(readyEvidence.size)));
		}
		else {
			setText('ztum-batch-execution-state', labels.execution_none);
		}

		confirm.disabled = !canExecute;
		if (!canExecute) {
			confirm.checked = false;
		}
		submit.disabled = !(canExecute && confirm.checked);
	};

	const confirm = byId('ztum-batch-confirm');
	confirm.addEventListener('change', updateExecutionState);

	const stopButton = byId('ztum-batch-stop');
	stopButton.addEventListener('click', () => {
		stopRequested = true;
		stopButton.disabled = true;
		progress(completed, labels.stopping);
	});

	const run = async () => {
		updateSummary();

		for (let index = 0; index < config.templateIds.length; index++) {
			if (stopRequested) {
				break;
			}

			const templateId = config.templateIds[index];
			progress(index + 1);
			setText('ztum-readiness-' + templateId, labels.processing);
			setText('ztum-category-' + templateId, labels.processing);
			setText('ztum-reason-' + templateId, '—');

			try {
				const item = await prepareOne(templateId);
				applyItem(templateId, item);
			}
			catch (error) {
				applyRequestFailure(templateId, error);
			}

			completed++;
			updateSummary();
		}

		stopButton.disabled = true;
		fullyPrepared = !stopRequested && completed === config.templateIds.length;

		if (fullyPrepared) {
			progress(completed, labels.complete);
		}
		else {
			progress(completed, labels.stopped);
		}

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
	->addItem(new CScriptTag($script))
	->show();
