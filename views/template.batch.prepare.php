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

$retryFailedButton = (new CButton('ztum-batch-retry-failed', _('Retry failed preparation')))
	->setId('ztum-batch-retry-failed')
	->setEnabled(false)
	->addClass(ZBX_STYLE_BTN_ALT);

$page
	->addItem(new CTag('h4', true, _('Batch preparation')))
	->addItem($summaryTable)
	->addItem(new CDiv([
		$progressText,
		' ',
		$stopButton,
		' ',
		$retryFailedButton
	]))
	->addItem(new CTag('p', true, _(
		'Each template is prepared in its own request. Preparation may create or refresh rollback evidence, but it never imports Zabbix configuration.'
	)));

$reviewSelectAll = (new CCheckBox('ztum-reviewed-select-all', '1'))
	->setId('ztum-reviewed-select-all');

$table = (new CTableInfo())
	->setHeader([
		new CDiv([$reviewSelectAll, ' ', _('Include all eligible')]),
		_('Template'),
		_('Installed'),
		_('Available'),
		_('Linked hosts'),
		_('Readiness'),
		_('Batch class'),
		_('Reason'),
		_('Execution')
	]);

foreach ($data['templateids'] as $templateId) {
	$template = $templateById[(string) $templateId] ?? [];
	$name = (string) ($template['name'] ?? $templateId);
	$installed = (string) ($template['vendor_version'] ?? '');
	$hostCount = (int) ($template['host_count'] ?? 0);

	$table->addRow(
		(new CRow([
			(new CSpan('—'))->setId('ztum-select-'.$templateId),
			$name,
			$installed !== '' ? $installed : '—',
			(new CSpan('—'))->setId('ztum-available-'.$templateId),
			$hostCount,
			(new CSpan(_('Pending')))->setId('ztum-readiness-'.$templateId),
			(new CSpan(_('Pending')))->setId('ztum-category-'.$templateId),
			(new CSpan('—'))->setId('ztum-reason-'.$templateId),
			(new CSpan(_('Pending')))->setId('ztum-execution-'.$templateId)
		]))->setId('ztum-row-'.$templateId)
	);
}

$page
	->addItem(new CTag('h4', true, _('Prepared templates')))
	->addItem($table);

$executionState = (new CSpan(_('Waiting for preparation.')))
	->setId('ztum-batch-execution-state');

$confirm = (new CCheckBox('confirm', '1'))
	->setId('ztum-batch-confirm')
	->setLabel(_('I reviewed the completed batch plan and explicitly accept the Manual review reasons for any reviewed templates I selected.'))
	->setEnabled(false);

$submit = (new CButton('ztum-batch-update-submit', _('Update eligible templates')))
	->setId('ztum-batch-update-submit')
	->setEnabled(false);

$executionSummary = (new CTableInfo())
	->setHeader([_('Status'), _('Updated'), _('Failed'), _('Not attempted'), _('Any configuration write')])
	->addRow([
		(new CSpan(_('Waiting for preparation')))->setId('ztum-batch-exec-status'),
		(new CSpan('0'))->setId('ztum-batch-exec-updated'),
		(new CSpan('0'))->setId('ztum-batch-exec-failed'),
		(new CSpan('0'))->setId('ztum-batch-exec-not-attempted'),
		(new CSpan(_('No')))->setId('ztum-batch-exec-write')
	]);

$page
	->addItem(new CTag('h4', true, _('Controlled sequential execution')))
	->addItem(new CTag('p', true, _(
		'After preparation completes, Ready templates can run normally. Manual review templates that contain only technical-risk reasons and have valid reviewed-preflight evidence can be explicitly selected in the Execution column. Local-customization overwrite, Conflict and Blocked rows still require individual handling. Each selected template runs in its own HTTP request, reruns fresh preflight immediately before import and is validated before the next template begins. Execution stops on the first failure and no automatic rollback is performed.'
	)))
	->addItem(new CTag('p', true, $executionState))
	->addItem(new CDiv([$confirm, ' ', $submit]))
	->addItem($executionSummary);

$prepareOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.prepare_one')
	->getUrl();

$executeOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.batch_update_one')
	->getUrl();

$compareUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.compare')
	->getUrl();

$jsConfig = json_encode([
	'templateIds' => array_values(array_map('strval', $data['templateids'])),
	'prepareOneUrl' => $prepareOneUrl,
	'executeOneUrl' => $executeOneUrl,
	'compareUrl' => $compareUrl,
	'csrfName' => CSRF_TOKEN_NAME,
	'prepareCsrfToken' => CCsrfTokenHelper::get('ztum.templates.prepare_one'),
	'executeCsrfToken' => CCsrfTokenHelper::get('ztum.templates.batch_update_one')
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
	'execution_available' => _('Available — {ready} Ready + {review} selected reviewed template(s).'),
	'execution_none' => _('Unavailable — no executable templates selected.'),
	'execution_review_only' => _('No unattended Ready templates. Select eligible Manual review rows below, or use Review details for individual handling.'),
	'execution_stopped' => _('Unavailable — preparation stopped.'),
	'retrying_failed' => _('Retrying failed preparation...'),
	'retry_complete' => _('Failed preparation retry complete.'),
	'execution_ready' => _('Ready for execution'),
	'select_reviewed' => _('Include reviewed update'),
	'select_all_reviewed' => _('Include all eligible reviewed updates'),
	'review_batch_eligible' => _('Reviewed batch eligible'),
	'review_details' => _('Review details'),
	'review_individual_only' => _('Individual review required'),
	'execution_running' => _('Running'),
	'execution_completed' => _('Completed'),
	'execution_failed' => _('Stopped on first failure'),
	'executing' => _('Updating...'),
	'updated' => _('Updated and validated'),
	'failed' => _('Failed'),
	'not_attempted' => _('Not attempted'),
	'yes' => _('Yes'),
	'no' => _('No')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$script = <<<'JS'
(() => {
	const config = __CONFIG__;
	const labels = __LABELS__;
	const counts = {ready: 0, review: 0, conflict: 0, blocked: 0};
	const readyEvidence = new Map();
	const reviewEvidence = new Map();
	const requestFailures = new Set();
	let completed = 0;
	let stopRequested = false;
	let fullyPrepared = false;
	let executionStarted = false;
	let retryInProgress = false;
	let autoSelectReviewed = false;

	const byId = (id) => document.getElementById(id);
	const setText = (id, value) => {
		const element = byId(id);
		if (element !== null) {
			element.textContent = value;
		}
	};

	const setReviewedSelection = (templateId, category, manual = null) => {
		const element = byId('ztum-select-' + templateId);
		if (element === null) {
			return;
		}

		element.replaceChildren();
		if (category === 'review' && manual?.eligible === true
				&& isValidEvidence(manual.evidence || '')) {
			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.id = 'ztum-review-select-' + templateId;
			checkbox.title = labels.select_reviewed;
			checkbox.checked = autoSelectReviewed;
			checkbox.addEventListener('change', () => {
				if (!checkbox.checked) {
					autoSelectReviewed = false;
				}
				updateReviewedSelectAll();
				updateExecutionState();
			});
			element.appendChild(checkbox);
			return;
		}

		const marker = document.createElement('span');
		marker.textContent = '—';
		if (category === 'review') {
			marker.title = labels.review_individual_only;
		}
		element.appendChild(marker);
	};

	const setExecutionState = (templateId, category, text = null, manual = null) => {
		const element = byId('ztum-execution-' + templateId);
		if (element === null) {
			return;
		}

		element.replaceChildren();
		if (category === 'review') {
			const note = document.createElement('span');
			note.textContent = manual?.eligible === true
				? labels.review_batch_eligible + ' · '
				: labels.review_individual_only + ' · ';
			element.appendChild(note);

			const link = document.createElement('a');
			link.href = config.compareUrl + '&templateid=' + encodeURIComponent(templateId);
			link.textContent = labels.review_details;
			element.appendChild(link);
			return;
		}

		element.textContent = text ?? (labels[category] || labels.blocked);
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

	const applyItem = (templateId, item) => {
		let category = ['ready', 'review', 'conflict', 'blocked'].includes(item.category)
			? item.category
			: 'blocked';
		let readinessStatus = item.readiness_status || '—';
		let reason = item.reason || '—';
		const evidence = normalizeEvidence(item.evidence_sha256 || '');
		const manualEvidence = normalizeEvidence(item.manual_evidence_sha256 || '');
		const manualEligible = item.batch_manual_eligible === true && isValidEvidence(manualEvidence);

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
		const manualState = {
			eligible: manualEligible,
			evidence: manualEvidence
		};
		setReviewedSelection(templateId, category, manualState);
		setExecutionState(
			templateId,
			category,
			category === 'ready' ? labels.execution_ready : (labels[category] || labels.blocked),
			manualState
		);

		requestFailures.delete(templateId);
		reviewEvidence.delete(templateId);
		if (category === 'ready') {
			readyEvidence.set(templateId, evidence);
		}
		else {
			readyEvidence.delete(templateId);
		}
		if (category === 'review' && manualEligible) {
			reviewEvidence.set(templateId, {
				evidence: manualEvidence,
				reasons: Array.isArray(item.manual_reasons) ? item.manual_reasons : []
			});
		}
		updateReviewedSelectAll();
	};

	const applyRequestFailure = (templateId, error) => {
		counts.blocked++;
		requestFailures.add(templateId);
		readyEvidence.delete(templateId);
		reviewEvidence.delete(templateId);
		setText('ztum-readiness-' + templateId, 'request_failed');
		setText('ztum-category-' + templateId, labels.blocked);
		setText('ztum-reason-' + templateId, error?.message || labels.request_failed);
		setReviewedSelection(templateId, 'blocked');
		setExecutionState(templateId, 'blocked', labels.blocked);
		updateReviewedSelectAll();
	};

	const prepareOne = async (templateId) => {
		const body = new FormData();
		body.append(config.csrfName, config.prepareCsrfToken);
		body.append('templateid', templateId);
		const startedAt = performance.now();

		const response = await fetch(config.prepareOneUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
			headers: {'X-Requested-With': 'XMLHttpRequest'}
		});

		if (!response.ok) {
			const elapsedSeconds = ((performance.now() - startedAt) / 1000).toFixed(1);
			throw new Error('HTTP ' + response.status + ' after ' + elapsedSeconds + 's');
		}

		const payload = await response.json();
		if (!payload || payload.ok !== true || !payload.item) {
			throw new Error(payload?.error || labels.request_failed);
		}

		return payload.item;
	};

	const executeOne = async (templateId, evidence, manualOverride = false) => {
		const body = new FormData();
		body.append(config.csrfName, config.executeCsrfToken);
		body.append('templateid', templateId);
		body.append('evidence_sha256', evidence);
		body.append('confirm', '1');
		if (manualOverride) {
			body.append('manual_override', '1');
			body.append('confirm_manual_override', '1');
		}

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

	const updateReviewedSelectAll = () => {
		const master = byId('ztum-reviewed-select-all');
		if (master === null) {
			return;
		}

		const eligible = Array.from(reviewEvidence.keys())
			.map((templateId) => byId('ztum-review-select-' + templateId))
			.filter((checkbox) => checkbox !== null && !checkbox.disabled);
		const selected = eligible.filter((checkbox) => checkbox.checked).length;

		master.disabled = executionStarted || retryInProgress;
		if (autoSelectReviewed && !master.disabled) {
			master.checked = true;
			master.indeterminate = false;
		}
		else {
			master.checked = eligible.length > 0 && selected === eligible.length;
			master.indeterminate = selected > 0 && selected < eligible.length;
		}
		master.title = labels.select_all_reviewed;
	};

	const selectedReviewedEntries = () => {
		const entries = [];
		for (const [templateId, review] of reviewEvidence.entries()) {
			const checkbox = byId('ztum-review-select-' + templateId);
			if (checkbox !== null && checkbox.checked) {
				entries.push({
					templateId,
					evidence: review.evidence,
					manualOverride: true
				});
			}
		}
		return entries;
	};

	const executionEntries = () => {
		const reviewed = new Map(selectedReviewedEntries().map((entry) => [entry.templateId, entry]));
		const entries = [];
		for (const templateId of config.templateIds) {
			if (readyEvidence.has(templateId)) {
				entries.push({
					templateId,
					evidence: readyEvidence.get(templateId),
					manualOverride: false
				});
			}
			else if (reviewed.has(templateId)) {
				entries.push(reviewed.get(templateId));
			}
		}
		return entries;
	};

	const updateExecutionState = () => {
		const confirm = byId('ztum-batch-confirm');
		const submit = byId('ztum-batch-update-submit');
		const retry = byId('ztum-batch-retry-failed');
		const selectedReviewed = selectedReviewedEntries().length;
		const executionCount = readyEvidence.size + selectedReviewed;
		const canExecute = fullyPrepared && executionCount > 0 && !executionStarted && !retryInProgress;
		const canRetry = fullyPrepared && requestFailures.size > 0 && !executionStarted && !retryInProgress;

		if (!executionStarted) {
			if (retryInProgress) {
				setText('ztum-batch-execution-state', labels.retrying_failed);
				setText('ztum-batch-exec-status', labels.retrying_failed);
			}
			else if (!fullyPrepared) {
				const state = stopRequested ? labels.execution_stopped : labels.execution_waiting;
				setText('ztum-batch-execution-state', state);
				setText('ztum-batch-exec-status', state);
			}
			else if (executionCount > 0) {
				const state = labels.execution_available
					.replace('{ready}', String(readyEvidence.size))
					.replace('{review}', String(selectedReviewed));
				setText('ztum-batch-execution-state', state);
				setText('ztum-batch-exec-status', labels.execution_ready);
				setText('ztum-batch-exec-not-attempted', String(executionCount));
			}
			else if (counts.review > 0) {
				setText('ztum-batch-execution-state', labels.execution_review_only);
				setText('ztum-batch-exec-status', labels.execution_review_only);
				setText('ztum-batch-exec-not-attempted', '0');
			}
			else {
				setText('ztum-batch-execution-state', labels.execution_none);
				setText('ztum-batch-exec-status', labels.execution_none);
				setText('ztum-batch-exec-not-attempted', '0');
			}
		}

		confirm.disabled = !canExecute;
		if (!canExecute && !executionStarted) {
			confirm.checked = false;
		}
		submit.disabled = !(canExecute && confirm.checked);
		retry.disabled = !canRetry;
		updateReviewedSelectAll();
	};

	const runExecution = async () => {
		const entries = executionEntries();
		if (executionStarted || !fullyPrepared || entries.length === 0
				|| !byId('ztum-batch-confirm').checked) {
			return;
		}

		executionStarted = true;
		byId('ztum-batch-confirm').disabled = true;
		byId('ztum-batch-update-submit').disabled = true;
		for (const templateId of reviewEvidence.keys()) {
			const checkbox = byId('ztum-review-select-' + templateId);
			if (checkbox !== null) {
				checkbox.disabled = true;
			}
		}
		updateReviewedSelectAll();
		let updated = 0;
		let failed = 0;
		let notAttempted = entries.length;
		let anyWrite = false;
		let stopped = false;

		setText('ztum-batch-execution-state', labels.execution_running);
		setText('ztum-batch-exec-status', labels.execution_running);
		setText('ztum-batch-exec-updated', '0');
		setText('ztum-batch-exec-failed', '0');
		setText('ztum-batch-exec-not-attempted', String(notAttempted));
		setText('ztum-batch-exec-write', labels.no);

		for (let index = 0; index < entries.length; index++) {
			const {templateId, evidence, manualOverride} = entries[index];
			setText('ztum-execution-' + templateId, labels.executing);

			try {
				const result = await executeOne(templateId, evidence, manualOverride);

				if (result.write_performed) {
					anyWrite = true;
					setText('ztum-batch-exec-write', labels.yes);
				}

				if (result.status !== 'updated') {
					failed++;
					notAttempted = entries.length - index - 1;
					const detail = result.reason ? ': ' + result.reason : '';
					setText('ztum-execution-' + templateId,
						labels.failed + (result.status ? ' (' + result.status + ')' : '') + detail);

					for (let pending = index + 1; pending < entries.length; pending++) {
						setText('ztum-execution-' + entries[pending].templateId, labels.not_attempted);
					}

					stopped = true;
					break;
				}

				updated++;
				notAttempted = entries.length - index - 1;
				const version = result.candidate?.vendor_version || '';
				const validation = result.validation?.status || 'validated';
				setText('ztum-execution-' + templateId,
					labels.updated
						+ (version !== '' ? ' (' + version + ')' : '')
						+ (validation !== '' ? ' [' + validation + ']' : ''));
			}
			catch (error) {
				failed++;
				notAttempted = entries.length - index - 1;
				setText('ztum-execution-' + templateId,
					labels.request_failed + (error?.message ? ': ' + error.message : ''));

				for (let pending = index + 1; pending < entries.length; pending++) {
					setText('ztum-execution-' + entries[pending].templateId, labels.not_attempted);
				}

				stopped = true;
				break;
			}

			setText('ztum-batch-exec-updated', String(updated));
			setText('ztum-batch-exec-failed', String(failed));
			setText('ztum-batch-exec-not-attempted', String(notAttempted));
		}

		setText('ztum-batch-exec-updated', String(updated));
		setText('ztum-batch-exec-failed', String(failed));
		setText('ztum-batch-exec-not-attempted', String(notAttempted));
		setText('ztum-batch-exec-write', anyWrite ? labels.yes : labels.no);
		setText('ztum-batch-exec-status',
			stopped ? labels.execution_failed : labels.execution_completed);
		setText('ztum-batch-execution-state',
			stopped ? labels.execution_failed : labels.execution_completed);
	};

	const retryFailedPreparation = async () => {
		if (retryInProgress || executionStarted || !fullyPrepared || requestFailures.size === 0) {
			return;
		}

		retryInProgress = true;
		updateExecutionState();

		const failedIds = Array.from(requestFailures);
		for (const templateId of failedIds) {
			if (!requestFailures.has(templateId)) {
				continue;
			}

			requestFailures.delete(templateId);
			counts.blocked = Math.max(0, counts.blocked - 1);
			setText('ztum-readiness-' + templateId, labels.processing);
			setText('ztum-category-' + templateId, labels.processing);
			setText('ztum-reason-' + templateId, '—');
			setText('ztum-select-' + templateId, '—');
			setText('ztum-execution-' + templateId, labels.pending);
			updateSummary();

			try {
				applyItem(templateId, await prepareOne(templateId));
			}
			catch (error) {
				applyRequestFailure(templateId, error);
			}

			updateSummary();
		}

		retryInProgress = false;
		progress(completed, labels.retry_complete);
		updateExecutionState();
	};

	byId('ztum-reviewed-select-all').addEventListener('change', (event) => {
		const checked = event.currentTarget.checked;
		autoSelectReviewed = checked;
		for (const templateId of reviewEvidence.keys()) {
			const checkbox = byId('ztum-review-select-' + templateId);
			if (checkbox !== null && !checkbox.disabled) {
				checkbox.checked = checked;
			}
		}
		updateReviewedSelectAll();
		updateExecutionState();
	});

	byId('ztum-batch-confirm').addEventListener('change', updateExecutionState);
	byId('ztum-batch-update-submit').addEventListener('click', (event) => {
		event.preventDefault();
		runExecution();
	});
	byId('ztum-batch-retry-failed').addEventListener('click', (event) => {
		event.preventDefault();
		retryFailedPreparation();
	});

	const stopButton = byId('ztum-batch-stop');
	stopButton.addEventListener('click', () => {
		stopRequested = true;
		stopButton.disabled = true;
		progress(completed, labels.stopping);
	});

	const run = async () => {
		updateSummary();
		updateReviewedSelectAll();

		for (let index = 0; index < config.templateIds.length; index++) {
			if (stopRequested) {
				break;
			}

			const templateId = config.templateIds[index];
			progress(index + 1);
			setText('ztum-readiness-' + templateId, labels.processing);
			setText('ztum-category-' + templateId, labels.processing);
			setText('ztum-reason-' + templateId, '—');
			setText('ztum-select-' + templateId, '—');
			setText('ztum-execution-' + templateId, labels.pending);

			try {
				applyItem(templateId, await prepareOne(templateId));
			}
			catch (error) {
				applyRequestFailure(templateId, error);
			}

			completed++;
			updateSummary();
		}

		stopButton.disabled = true;
		fullyPrepared = !stopRequested && completed === config.templateIds.length;
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
	->addItem(new CScriptTag($script))
	->show();
