'use strict';

window.ZTUMInstallBatchInit = (config, labels) => {
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

	const setStateText = (id, value, tone = 'muted') => {
		const element = byId(id);
		if (element === null) {
			return;
		}

		element.textContent = value;
		for (const className of Object.values(config.statusClasses || {})) {
			if (className) {
				element.classList.remove(className);
			}
		}
		const className = config.statusClasses?.[tone] || '';
		if (className) {
			element.classList.add(className);
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

	const formatCode = (value) => {
		if (typeof value !== 'string' || value.trim() === '') {
			return '—';
		}
		const key = value.trim();
		const translated = labels['reason_' + key];
		if (translated) {
			return translated;
		}
		return key.replaceAll('_', ' ').replaceAll('-', ' ')
			.replace(/^./, (first) => first.toUpperCase());
	};

	const validationDetail = (result) => {
		const validation = result?.validation && typeof result.validation === 'object'
			? result.validation
			: {};
		const reasons = Array.isArray(validation.reasons)
			? validation.reasons.filter((reason) => typeof reason === 'string' && reason !== '')
			: [];
		const parts = [];

		if (reasons.length > 0) {
			parts.push(labels.validation_reasons + ': ' + reasons.map(formatCode).join(', '));
		}

		const remaining = Number(validation.remaining_changes);
		if (Number.isInteger(remaining) && remaining >= 0) {
			parts.push(labels.remaining_differences + ': ' + remaining);
		}

		const rawRemaining = Number(validation.raw_remaining_changes);
		if (Number.isInteger(rawRemaining) && rawRemaining >= 0 && rawRemaining !== remaining) {
			parts.push(labels.raw_differences + ': ' + rawRemaining);
		}

		const ignored = Number(validation.ignored_shared_changes);
		if (Number.isInteger(ignored) && ignored > 0) {
			parts.push(labels.ignored_shared_differences + ': ' + ignored);
		}

		return parts.length > 0
			? parts.join(' | ')
			: formatCode(result?.reason || 'post_install_validation_failed');
	};

	const applyItem = (uuid, item) => {
		const category = item.category === 'ready' ? 'ready' : 'blocked';
		counts[category]++;

		setText('ztum-install-name-' + uuid, item.name || uuid);
		setText('ztum-install-version-' + uuid, item.available_version || '—');
		const preflightStatus = item.preflight_status || '—';
		setStateText(
			'ztum-install-preflight-' + uuid,
			formatCode(preflightStatus),
			category === 'ready' ? 'success' : 'danger'
		);
		setStateText(
			'ztum-install-category-' + uuid,
			labels[category],
			category === 'ready' ? 'success' : 'danger'
		);
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
			referenceIssues.length > 0 ? referenceIssues.map(formatCode).join(' | ') : formatCode(item.reason || '')
		);
		setStateText(
			'ztum-install-execution-' + uuid,
			category === 'ready' ? labels.ready : labels.blocked,
			category === 'ready' ? 'success' : 'danger'
		);

		if (category === 'ready' && /^[a-f0-9]{64}$/.test(item.evidence_sha256 || '')) {
			readyEvidence.set(uuid, item.evidence_sha256);
		}
	};

	const applyRequestFailure = (uuid, error) => {
		counts.blocked++;
		setStateText('ztum-install-preflight-' + uuid, labels.request_failed, 'danger');
		setStateText('ztum-install-category-' + uuid, labels.blocked, 'danger');
		setText('ztum-install-reason-' + uuid, error?.message || labels.request_failed);
		setStateText('ztum-install-execution-' + uuid, labels.blocked, 'danger');
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
			setStateText('ztum-install-exec-status', labels.execution_ready, 'success');
			setText('ztum-install-exec-not-attempted', String(readyEvidence.size));
			setText('ztum-install-no-ready-message', '');
		}
		else if (fullyPrepared && readyEvidence.size === 0 && !executionStarted) {
			setStateText('ztum-install-exec-status', labels.execution_unavailable, 'muted');
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
		let uncertain = 0;
		let stopped = false;
		let stoppedUncertain = false;

		setStateText('ztum-install-exec-status', labels.execution_running, 'info');
		setText('ztum-install-exec-installed', '0');
		setText('ztum-install-exec-failed', '0');
		setText('ztum-install-exec-not-attempted', String(notAttempted));
		setText('ztum-install-exec-write', labels.no);
		setText('ztum-install-exec-uncertain', '0');
		setText('ztum-install-exec-notice', '');

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

					if (result.status === 'import_failed') {
						uncertain++;
						stoppedUncertain = true;
						const inspection = formatCode(result.failure_inspection?.state || 'state_unknown_after_failure');
						const detail = result.error_detail || result.reason || labels.failed;
						setStateText('ztum-install-execution-' + uuid, labels.import_failed, 'danger');
						setText('ztum-install-reason-' + uuid, detail + ' [' + inspection + ']');
						setStateText('ztum-install-exec-notice', labels.import_failure_notice, 'danger');
					}
					else if (result.status === 'validation_failed') {
						setStateText('ztum-install-execution-' + uuid, labels.validation_failed, 'danger');
						setText('ztum-install-reason-' + uuid, validationDetail(result));
					}
					else {
						setStateText(
							'ztum-install-execution-' + uuid,
							labels.failed + (result.status ? ' (' + result.status + ')' : ''),
							'danger'
						);
						if (result.reason) {
							setText('ztum-install-reason-' + uuid, formatCode(result.reason));
						}
					}

					for (let pending = index + 1; pending < entries.length; pending++) {
						setStateText('ztum-install-execution-' + entries[pending][0], labels.not_attempted, 'muted');
					}

					stopped = true;
					break;
				}

				installed++;
				notAttempted = entries.length - index - 1;
				const validation = result.validation?.status || 'validated';
				setStateText('ztum-install-execution-' + uuid, labels.installed + ' (' + validation + ')', 'success');
				if (Number(result.validation?.ignored_shared_changes || 0) > 0) {
					setText(
						'ztum-install-reason-' + uuid,
						labels.create_only_validated + ' | ' + validationDetail(result)
					);
				}
			}
			catch (error) {
				failed++;
				uncertain++;
				stoppedUncertain = true;
				notAttempted = entries.length - index - 1;
				const detail = error?.message || labels.request_failed;
				setStateText('ztum-install-execution-' + uuid, labels.request_failed, 'danger');
				setText('ztum-install-reason-' + uuid, detail);
				setStateText('ztum-install-exec-notice', labels.request_failure_notice, 'danger');

				for (let pending = index + 1; pending < entries.length; pending++) {
					setStateText('ztum-install-execution-' + entries[pending][0], labels.not_attempted, 'muted');
				}

				stopped = true;
				break;
			}

			setText('ztum-install-exec-installed', String(installed));
			setText('ztum-install-exec-failed', String(failed));
			setText('ztum-install-exec-not-attempted', String(notAttempted));
			setText('ztum-install-exec-uncertain', String(uncertain));
		}

		setText('ztum-install-exec-installed', String(installed));
		setText('ztum-install-exec-failed', String(failed));
		setText('ztum-install-exec-not-attempted', String(notAttempted));
		setText('ztum-install-exec-write', anyWrite ? labels.yes : labels.no);
		setText('ztum-install-exec-uncertain', String(uncertain));
		setStateText(
			'ztum-install-exec-status',
			stopped
				? (stoppedUncertain ? labels.execution_stopped_uncertain : labels.execution_stopped)
				: labels.execution_completed,
			stopped ? 'danger' : 'success'
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
};
