<?php

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(
		_('Back to template catalog'),
		(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
	));

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
		_('Reason')
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
			(new CSpan('—'))->setId('ztum-install-reason-'.$uuid)
		]))->setId('ztum-install-row-'.$uuid)
	);
}

$page
	->addItem(new CTag('h4', true, _('Prepared installation candidates')))
	->addItem($table);

$installAction = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.install_batch')
	->getUrl();

$readyInputs = (new CDiv())->setId('ztum-install-batch-ready-inputs');
$confirm = (new CCheckBox('confirm', '1'))
	->setId('ztum-install-batch-confirm')
	->setLabel(_('I reviewed the completed installation plan and want to install the Ready templates sequentially.'))
	->setEnabled(false);
$submit = (new CSubmitButton(_('Install ready templates')))
	->setId('ztum-install-batch-submit')
	->setEnabled(false);

$form = (new CForm('post'))
	->setId('ztum-install-batch-form')
	->setAction($installAction)
	->addItem((new CVar(
		CSRF_TOKEN_NAME,
		CCsrfTokenHelper::get('ztum.templates.install_batch')
	))->removeId())
	->addItem($readyInputs)
	->addItem([$confirm, $submit]);

$page
	->addItem(new CTag('h4', true, _('Controlled sequential installation')))
	->addItem(new CTag('p', true, _(
		'Only Ready candidates are submitted. Each candidate reruns the complete installation preflight immediately before import. Execution stops on the first failure and no automatic uninstall is performed.'
	)))
	->addItem($form);

$prepareOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.install_prepare_one')
	->getUrl();

$jsConfig = json_encode([
	'uuids' => array_values(array_map('strval', $data['uuids'])),
	'prepareOneUrl' => $prepareOneUrl,
	'csrfName' => CSRF_TOKEN_NAME,
	'csrfToken' => CCsrfTokenHelper::get('ztum.templates.install_prepare_one')
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
	'progress' => _('Preparing {current} of {total} installations...')
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

	const addReadyInput = (uuid, evidence) => {
		const container = byId('ztum-install-batch-ready-inputs');
		const index = readyEvidence.size;

		const uuidInput = document.createElement('input');
		uuidInput.type = 'hidden';
		uuidInput.name = 'uuids[' + index + ']';
		uuidInput.value = uuid;
		container.appendChild(uuidInput);

		const evidenceInput = document.createElement('input');
		evidenceInput.type = 'hidden';
		evidenceInput.name = 'evidence[' + uuid + ']';
		evidenceInput.value = evidence;
		container.appendChild(evidenceInput);

		readyEvidence.set(uuid, evidence);
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
		setText('ztum-install-reason-' + uuid, item.reason || '—');

		if (category === 'ready' && /^[a-f0-9]{64}$/.test(item.evidence_sha256 || '')) {
			addReadyInput(uuid, item.evidence_sha256);
		}
	};

	const applyRequestFailure = (uuid, error) => {
		counts.blocked++;
		setText('ztum-install-preflight-' + uuid, 'request_failed');
		setText('ztum-install-category-' + uuid, labels.blocked);
		setText('ztum-install-reason-' + uuid, error?.message || labels.request_failed);
	};

	const prepareOne = async (uuid) => {
		const body = new FormData();
		body.append(config.csrfName, config.csrfToken);
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

	const updateExecutionState = () => {
		const confirm = byId('ztum-install-batch-confirm');
		const submit = byId('ztum-install-batch-submit');
		const canExecute = fullyPrepared && readyEvidence.size > 0;

		confirm.disabled = !canExecute;
		if (!canExecute) {
			confirm.checked = false;
		}
		submit.disabled = !(canExecute && confirm.checked);
	};

	byId('ztum-install-batch-confirm').addEventListener('change', updateExecutionState);

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
	->addItem(new CScriptTag($script))
	->show();
