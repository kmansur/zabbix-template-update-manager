<?php

$statusLabels = [
	'blocked_analysis' => _('Blocked — analysis unavailable'),
	'blocked_readiness' => _('Blocked — readiness prerequisite not met'),
	'blocked_candidate' => _('Blocked — upstream candidate identity unresolved'),
	'blocked_backup' => _('Blocked — rollback evidence unresolved'),
	'passed' => _('Passed — eligible for controlled update confirmation')
];

$backUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.compare')
	->setArgument('templateid', $data['templateid']);

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(_('Back to template comparison'), $backUrl));

if ($data['preflight_error'] !== null) {
	$page
		->addItem(new CTag('p', true, $data['preflight_error']))
		->show();
	return;
}

$preflight = is_array($data['preflight']) ? $data['preflight'] : [];
$status = (string) ($preflight['status'] ?? 'blocked_analysis');
$template = is_array($preflight['template'] ?? null) ? $preflight['template'] : [];
$candidate = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];
$rollback = is_array($preflight['rollback'] ?? null) ? $preflight['rollback'] : [];
$evidenceSha = (string) ($preflight['evidence_sha256'] ?? '');
$manualOverride = !empty($preflight['manual_override']);
$manualReasons = is_array($preflight['manual_reasons'] ?? null)
	? array_values(array_map('strval', $preflight['manual_reasons']))
	: [];

$stateTable = (new CTableInfo())
	->setHeader([
		_('Preflight state'),
		_('Reason'),
		_('Next step'),
		_('Preflight write enabled')
	])
	->addRow([
		$statusLabels[$status] ?? _('Unknown'),
		($preflight['reason'] ?? null) !== null ? (string) $preflight['reason'] : '—',
		(string) ($preflight['next_step'] ?? '—'),
		!empty($preflight['write_enabled']) ? _('Yes') : _('No')
	]);

$page
	->addItem(new CTag('h4', true, _('Fresh server-side preflight')))
	->addItem($stateTable)
	->addItem(new CTag('p', true, _(
		'This result was recomputed from the current Zabbix template, the current validated official upstream source and the newest rollback artifact. It does not trust an earlier page state.'
	)));

if ($template !== []) {
	$templateTable = (new CTableInfo())
		->setHeader([
			_('Template'),
			_('Template ID'),
			_('UUID'),
			_('Installed version'),
			_('Available version'),
			_('Directly linked hosts')
		])
		->addRow([
			(string) ($template['name'] ?? '—'),
			(string) ($template['templateid'] ?? '—'),
			(string) ($template['uuid'] ?? '—'),
			(string) ($template['installed_version'] ?? '—'),
			(string) ($template['available_version'] ?? '—'),
			(int) ($preflight['direct_host_count'] ?? 0)
		]);

	$page->addItem(new CTag('h4', true, _('Template identity')))->addItem($templateTable);
}

if ($candidate !== []) {
	$candidateTable = (new CTableInfo())
		->setHeader([
			_('Available version'),
			_('Vendor'),
			_('Upstream commit'),
			_('Official source path')
		])
		->addRow([
			(string) ($candidate['vendor_version'] ?? '—'),
			(string) ($candidate['vendor_name'] ?? '—'),
			isset($candidate['commit']) ? substr((string) $candidate['commit'], 0, 16) : '—',
			(string) ($candidate['path'] ?? '—')
		]);

	$page->addItem(new CTag('h4', true, _('Bound upstream candidate')))->addItem($candidateTable);
}

if ($rollback !== []) {
	$rollbackTable = (new CTableInfo())
		->setHeader([
			_('Created'),
			_('Bytes'),
			_('Stored SHA-256'),
			_('Fresh export SHA-256')
		])
		->addRow([
			(string) ($rollback['created_at'] ?? '—'),
			(int) ($rollback['bytes'] ?? 0),
			isset($rollback['sha256']) ? substr((string) $rollback['sha256'], 0, 20) : '—',
			isset($rollback['current_export_sha256'])
				? substr((string) $rollback['current_export_sha256'], 0, 20)
				: '—'
		]);

	$page->addItem(new CTag('h4', true, _('Verified rollback evidence')))->addItem($rollbackTable);
}

if ($manualOverride) {
	$manualReasonLabels = [
		'local_customization_overwrite' => _('Known local customization would be overwritten or removed'),
		'medium_technical_risk' => _('Medium technical review priority'),
		'high_technical_risk' => _('High technical review priority')
	];
	$labels = [];
	foreach ($manualReasons as $reason) {
		$labels[] = $manualReasonLabels[$reason] ?? $reason;
	}

	$page
		->addItem(new CTag('h4', true, _('Explicit manual-review path')))
		->addItem(new CTag('p', true, _(
			'This candidate is not eligible for unattended update. The following reviewed conditions are bound into the preflight evidence and require an additional explicit acknowledgement before import:'
		)))
		->addItem(new CTag('p', true, implode('; ', $labels)));
}

if ($evidenceSha !== '') {
	$page
		->addItem(new CTag('h4', true, _('Preflight evidence fingerprint')))
		->addItem(new CTag('p', true, $evidenceSha));
}

if ($status === 'passed') {
	$page->addItem(new CTag('p', true, _(
		'All implemented safety prerequisites passed for this selected path. The update action will rerun this complete preflight immediately before configuration.import and will refuse the write if this evidence or the selected review mode changes.'
	)));

	if (!empty($data['can_update']) && $evidenceSha !== '' && $template !== []) {
		$updateAction = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.update')
			->getUrl();
		$confirmationItems = [
			(new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.template.update')))->removeId(),
			(new CVar('templateid', (string) $template['templateid']))->removeId(),
			(new CVar('evidence_sha256', $evidenceSha))->removeId(),
			(new CCheckBox('confirm', '1'))->setLabel(_(
				'I reviewed the candidate and verified rollback evidence and want to update this template.'
			))
		];

		if ($manualOverride) {
			$confirmationItems[] = (new CVar('manual_override', '1'))->removeId();
			$confirmationItems[] = (new CCheckBox('confirm_manual_override', '1'))->setLabel(_(
				'I explicitly accept the reviewed local-overwrite and/or technical-risk conditions above. I understand that the official import may remove or replace those local differences.'
			));
		}

		$confirmationItems[] = new CSubmitButton(
			$manualOverride ? _('Update official template with reviewed override') : _('Update official template')
		);

		$updateForm = (new CForm('post'))
			->setId('ztum-template-update-form')
			->setAction($updateAction)
			->addItem($confirmationItems);

		$page
			->addItem(new CTag('h4', true, _('Controlled update confirmation')))
			->addItem(new CTag('p', true, _(
				'This operation writes Zabbix configuration. It is restricted to super administrators. The stored rollback backup is not deleted after the update.'
			)))
			->addItem($updateForm);
	}
	else {
		$page->addItem(new CTag('p', true, _(
			'A Zabbix super administrator is required for the controlled configuration import.'
		)));
	}
}
else {
	$page->addItem(new CTag('p', true, _(
		'The update workflow remains blocked. Return to the comparison page, resolve the reported prerequisite and rerun preflight.'
	)));
}

$page->show();
