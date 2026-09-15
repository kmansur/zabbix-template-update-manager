<?php

$statusLabels = [
	'blocked_preflight' => _('Blocked — fresh preflight did not pass'),
	'blocked_evidence_changed' => _('Blocked — preflight evidence changed'),
	'updated' => _('Updated and validated'),
	'validation_failed' => _('Update imported but validation failed')
];

$compareUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.compare')
	->setArgument('templateid', $data['templateid']);
$backupsUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.backups')
	->setArgument('templateid', $data['templateid']);

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem([
		new CLink(_('Back to template comparison'), $compareUrl),
		' | ',
		new CLink(_('Rollback backup history'), $backupsUrl)
	]);

if ($data['operation_error'] !== null) {
	$page
		->addItem(new CTag('h4', true, _('Controlled update error')))
		->addItem(new CTag('p', true, $data['operation_error']))
		->addItem(new CTag('p', true, _(
			'No automatic retry is performed. Re-open the comparison page and verify the installed state before attempting another update.'
		)))
		->show();
	return;
}

$result = is_array($data['result']) ? $data['result'] : [];
$status = (string) ($result['status'] ?? 'blocked_preflight');
$writePerformed = !empty($result['write_performed']);

$summaryTable = (new CTableInfo())
	->setHeader([
		_('Result'),
		_('Configuration write performed'),
		_('Reason'),
		_('Fresh preflight state')
	])
	->addRow([
		$statusLabels[$status] ?? _('Unknown'),
		$writePerformed ? _('Yes') : _('No'),
		($result['reason'] ?? null) !== null ? (string) $result['reason'] : '—',
		(string) ($result['preflight_status'] ?? '—')
	]);

$page
	->addItem(new CTag('h4', true, _('Controlled update result')))
	->addItem($summaryTable);

if (is_array($result['candidate'] ?? null)) {
	$candidate = $result['candidate'];
	$candidateTable = (new CTableInfo())
		->setHeader([
			_('Installed target version'),
			_('Upstream commit'),
			_('Source path'),
			_('Canonical SHA-256'),
			_('Import SHA-256')
		])
		->addRow([
			(string) ($candidate['vendor_version'] ?? '—'),
			isset($candidate['commit']) ? substr((string) $candidate['commit'], 0, 16) : '—',
			(string) ($candidate['path'] ?? '—'),
			isset($candidate['canonical_sha256']) ? substr((string) $candidate['canonical_sha256'], 0, 20) : '—',
			isset($candidate['import_sha256']) ? substr((string) $candidate['import_sha256'], 0, 20) : '—'
		]);
	$page->addItem(new CTag('h4', true, _('Imported candidate')))->addItem($candidateTable);
}

if (isset($result['preflight_evidence_sha256'])) {
	$page
		->addItem(new CTag('h4', true, _('Confirmed preflight evidence')))
		->addItem(new CTag('p', true, (string) $result['preflight_evidence_sha256']));
}

if (isset($result['rollback_sha256']) && (string) $result['rollback_sha256'] !== '') {
	$page
		->addItem(new CTag('h4', true, _('Rollback artifact used as prerequisite')))
		->addItem(new CTag('p', true, (string) $result['rollback_sha256']));
}

if (is_array($result['validation'] ?? null)) {
	$validation = $result['validation'];
	$validationTable = (new CTableInfo())
		->setHeader([
			_('Validation state'),
			_('Expected version'),
			_('Installed version'),
			_('Content state'),
			_('Remaining differences')
		])
		->addRow([
			(string) ($validation['status'] ?? '—'),
			(string) ($validation['expected_version'] ?? '—'),
			(string) ($validation['installed_version'] ?? '—'),
			(string) ($validation['content_status'] ?? '—'),
			(int) ($validation['remaining_changes'] ?? -1)
		]);
	$page->addItem(new CTag('h4', true, _('Post-import validation')))->addItem($validationTable);

	if (($validation['reasons'] ?? []) !== []) {
		$page->addItem(new CTag('p', true, _(
			'Validation reasons: '.implode(', ', array_map('strval', $validation['reasons']))
		)));
	}
}

switch ($status) {
	case 'updated':
		$page->addItem(new CTag('p', true, _(
			'The official template import completed and a fresh post-import comparison confirms that the installed template now matches the bound upstream candidate.'
		)));
		break;

	case 'validation_failed':
		$page->addItem(new CTag('p', true, _(
			'The configuration import returned successfully, but the fresh post-import validation did not prove an exact current-upstream match. Do not retry blindly; inspect the comparison page and keep the verified rollback artifact available.'
		)));
		break;

	case 'blocked_evidence_changed':
		$page->addItem(new CTag('p', true, _(
			'The authoritative state changed after the confirmation page was rendered. The module refused to write. Rerun preflight and review the new evidence.'
		)));
		break;

	default:
		$page->addItem(new CTag('p', true, _(
			'The fresh server-side preflight did not satisfy the controlled update gate, so no configuration import was attempted.'
		)));
}

$page->show();
