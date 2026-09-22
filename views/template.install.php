<?php

$statusLabels = [
	'blocked_preflight' => _('Blocked — fresh installation preflight did not pass'),
	'blocked_evidence_changed' => _('Blocked — installation evidence changed'),
	'installed' => _('Installed and validated'),
	'validation_failed' => _('Installed but post-install validation failed')
];

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(
		_('Back to template catalog'),
		(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
	));

if ($data['operation_error'] !== null) {
	$page
		->addItem(new CTag('h4', true, _('Controlled installation error')))
		->addItem(new CTag('p', true, $data['operation_error']))
		->addItem(new CTag('p', true, _(
			'Do not retry blindly. Refresh the template catalog first and inspect whether the UUID now exists locally.'
		)))
		->show();
	return;
}

$result = is_array($data['result']) ? $data['result'] : [];
$status = (string) ($result['status'] ?? 'blocked_preflight');

$page
	->addItem(new CTag('h4', true, _('Controlled installation result')))
	->addItem(
		(new CTableInfo())
			->setHeader([_('Result'), _('Configuration write performed'), _('Reason'), _('Fresh preflight state')])
			->addRow([
				$statusLabels[$status] ?? $status,
				!empty($result['write_performed']) ? _('Yes') : _('No'),
				($result['reason'] ?? null) !== null ? (string) $result['reason'] : '—',
				(string) ($result['preflight_status'] ?? '—')
			])
	);

$candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : [];
if ($candidate !== []) {
	$page
		->addItem(new CTag('h4', true, _('Imported official candidate')))
		->addItem(
			(new CTableInfo())
				->setHeader([_('Template'), _('Version'), _('UUID'), _('Source path'), _('Import SHA-256')])
				->addRow([
					(string) ($candidate['name'] ?? '—'),
					(string) ($candidate['vendor_version'] ?? '—'),
					(string) ($candidate['uuid'] ?? $data['uuid']),
					(string) ($candidate['path'] ?? '—'),
					isset($candidate['import_sha256'])
						? substr((string) $candidate['import_sha256'], 0, 20)
						: '—'
				])
		);
}

$validation = is_array($result['validation'] ?? null) ? $result['validation'] : [];
if ($validation !== []) {
	$page
		->addItem(new CTag('h4', true, _('Post-install validation')))
		->addItem(
			(new CTableInfo())
				->setHeader([
					_('Validation'),
					_('Template ID'),
					_('Expected version'),
					_('Installed version'),
					_('Content state'),
					_('Remaining differences')
				])
				->addRow([
					(string) ($validation['status'] ?? '—'),
					(string) ($validation['templateid'] ?? '—'),
					(string) ($validation['expected_version'] ?? '—'),
					(string) ($validation['installed_version'] ?? '—'),
					(string) ($validation['content_status'] ?? '—'),
					(int) ($validation['remaining_changes'] ?? -1)
				])
		);

	if (($validation['reasons'] ?? []) !== []) {
		$page->addItem(new CTag('p', true, _(
			'Validation reasons: '.implode(', ', array_map('strval', $validation['reasons']))
		)));
	}
}

if ($status === 'installed') {
	$page->addItem(new CTag('p', true, _(
		'The official template was created and fresh validation confirms that the installed UUID/version/content match the bound upstream candidate.'
	)));
}
elseif ($status === 'validation_failed') {
	$page->addItem(new CTag('p', true, _(
		'The import returned successfully, but exact post-install state could not be proven. ZTUM does not automatically uninstall a newly created template; inspect the local state before taking further action.'
	)));
}
else {
	$page->addItem(new CTag('p', true, _(
		'No installation write was performed. Reopen the catalog and review fresh server-side evidence.'
	)));
}

$page->show();
