<?php

$backUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.backups')
	->setArgument('templateid', $data['templateid']);

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(_('Back to rollback backup history'), $backUrl));

if ($data['operation_error'] !== null) {
	$page
		->addItem(new CTag('p', true, $data['operation_error']))
		->addItem(new CTag('p', true, _(
			'Because the exact point of failure may be after configuration.import started, inspect the installed template and frontend logs before taking another write action.'
		)))
		->show();
	return;
}

$result = is_array($data['result']) ? $data['result'] : [];
$status = (string) ($result['status'] ?? 'unknown');
$writePerformed = !empty($result['write_performed']);

$statusLabels = [
	'rolled_back' => _('Rollback completed and validated'),
	'validation_failed' => _('Rollback import completed, but validation failed'),
	'validation_error' => _('Rollback import completed, but validation could not be completed'),
	'blocked_preflight' => _('Rollback blocked by fresh preflight'),
	'blocked_evidence_changed' => _('Rollback blocked because evidence changed'),
	'blocked_current_changed' => _('Rollback blocked because current state changed before import')
];

$stateTable = (new CTableInfo())
	->setHeader([_('Result'), _('Configuration write performed'), _('Reason')])
	->addRow([
		$statusLabels[$status] ?? $status,
		$writePerformed ? _('Yes') : _('No'),
		($result['reason'] ?? null) !== null ? (string) $result['reason'] : '—'
	]);

$page
	->addItem(new CTag('h4', true, _('Rollback operation')))
	->addItem($stateTable);

$recovery = is_array($result['recovery_backup'] ?? null) ? $result['recovery_backup'] : [];
if ($recovery !== []) {
	$sha = (string) ($recovery['sha256'] ?? '');
	$recoveryTable = (new CTableInfo())
		->setHeader([_('Created'), _('Bytes'), _('SHA-256'), _('Manifest'), _('YAML')])
		->addRow([
			(string) ($recovery['created_at'] ?? '—'),
			(int) ($recovery['bytes'] ?? 0),
			$sha !== '' ? substr($sha, 0, 20) : '—',
			(string) ($recovery['manifest_file'] ?? '—'),
			(string) ($recovery['source_file'] ?? '—')
		]);
	$page
		->addItem(new CTag('h4', true, _('Recovery backup created before rollback')))
		->addItem($recoveryTable);
}

$target = is_array($result['target'] ?? null) ? $result['target'] : [];
if ($target !== []) {
	$sha = (string) ($target['sha256'] ?? '');
	$targetTable = (new CTableInfo())
		->setHeader([_('Target backup'), _('Vendor version'), _('Bytes'), _('SHA-256'), _('Manifest')])
		->addRow([
			(string) ($target['created_at'] ?? '—'),
			(string) ($target['vendor_version'] ?? '—'),
			(int) ($target['bytes'] ?? 0),
			$sha !== '' ? substr($sha, 0, 20) : '—',
			(string) ($target['manifest_file'] ?? '—')
		]);
	$page->addItem(new CTag('h4', true, _('Rollback target')))->addItem($targetTable);
}

$validation = is_array($result['validation'] ?? null) ? $result['validation'] : null;
if ($validation !== null) {
	$validationTable = (new CTableInfo())
		->setHeader([
			_('Validation'),
			_('Expected version'),
			_('Installed version'),
			_('Remaining differences'),
			_('Reasons')
		])
		->addRow([
			!empty($validation['valid']) ? _('Passed') : _('Failed'),
			(string) ($validation['expected_version'] ?? '—'),
			(string) ($validation['installed_version'] ?? '—'),
			(int) ($validation['remaining_changes'] ?? -1),
			($validation['reasons'] ?? []) !== [] ? implode(', ', $validation['reasons']) : '—'
		]);
	$page->addItem(new CTag('h4', true, _('Post-rollback validation')))->addItem($validationTable);
}

if ($status === 'rolled_back') {
	$page->addItem(new CTag('p', true, _(
		'The selected rollback artifact was imported and a fresh configuration.importcompare found no remaining differences. The recovery backup and rollback target remain stored.'
	)));
}
elseif ($writePerformed) {
	$page->addItem(new CTag('p', true, _(
		'A configuration write occurred, but the final state was not proven valid. Do not retry automatically. Inspect the template and use the newly created recovery backup only through a fresh explicit rollback review.'
	)));
}
else {
	$page->addItem(new CTag('p', true, _(
		'No Zabbix configuration write was performed. Re-open rollback review to obtain fresh evidence before trying again.'
	)));
}

$page->show();
