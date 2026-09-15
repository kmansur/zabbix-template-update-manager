<?php

$integrityLabels = [
	'valid' => _('Valid'),
	'invalid' => _('Invalid')
];

$reasonLabels = [
	'manifest_unreadable' => _('Manifest unreadable or unsafe'),
	'manifest_size_invalid' => _('Manifest size invalid'),
	'manifest_json_invalid' => _('Manifest JSON invalid'),
	'manifest_schema_invalid' => _('Manifest schema unsupported'),
	'manifest_created_at_invalid' => _('Manifest creation time invalid'),
	'manifest_template_mismatch' => _('Template ID mismatch'),
	'manifest_uuid_invalid' => _('Template UUID invalid'),
	'manifest_identity_invalid' => _('Template identity invalid'),
	'manifest_export_invalid' => _('Export metadata invalid'),
	'source_unreadable' => _('YAML source unreadable or unsafe'),
	'source_size_mismatch' => _('YAML byte count mismatch'),
	'source_hash_mismatch' => _('YAML SHA-256 mismatch'),
	'artifact_permissions_insecure' => _('Artifact file permissions are not private')
];

$backUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');
$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(_('Back to template updates'), $backUrl));

if (is_array($data['template'])) {
	$template = $data['template'];
	$templateTable = (new CTableInfo())
		->setHeader([
			_('Template'),
			_('Technical name'),
			_('Installed vendor version'),
			_('UUID')
		])
		->addRow([
			$template['name'],
			$template['technical_name'] !== '' ? $template['technical_name'] : '—',
			$template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
			$template['uuid'] !== '' ? $template['uuid'] : '—'
		]);

	$page->addItem(new CTag('h4', true, _('Template')))->addItem($templateTable);
}

if ($data['error'] !== null) {
	$page->addItem(new CTag('p', true, $data['error']));
	$page->show();
	return;
}

$repositoryLabels = [
	'ok' => _('Available'),
	'no_backup' => _('No rollback backups'),
	'repository_unavailable' => _('Repository unavailable')
];

$summaryTable = (new CTableInfo())
	->setHeader([
		_('Repository state'),
		_('Artifacts scanned'),
		_('Valid'),
		_('Invalid'),
		_('Result truncated')
	])
	->addRow([
		$repositoryLabels[$data['repository_status']] ?? _('Unknown'),
		$data['scanned'],
		$data['valid'],
		$data['invalid'],
		$data['truncated'] ? _('Yes') : _('No')
	]);

$page
	->addItem(new CTag('h4', true, _('Rollback repository summary')))
	->addItem($summaryTable);

if ($data['artifacts'] === []) {
	$page
		->addItem(new CTag('p', true, _(
			'No stored rollback artifacts are available for this template.'
		)))
		->addItem(new CTag('p', true, _(
			'This page is read-only. Backup creation remains available only from the comparison workflow when its readiness gate allows it.'
		)))
		->show();
	return;
}

$artifactTable = (new CTableInfo())
	->setHeader([
		_('Created at'),
		_('Integrity'),
		_('Vendor version'),
		_('Bytes'),
		_('SHA-256'),
		_('YAML file'),
		_('Manifest file'),
		_('Issue')
	]);

foreach ($data['artifacts'] as $artifact) {
	$status = (string) ($artifact['status'] ?? 'invalid');
	$reason = (string) ($artifact['reason'] ?? '');
	$sha256 = (string) ($artifact['sha256'] ?? '');

	$artifactTable->addRow([
		$artifact['created_at'] !== '' ? $artifact['created_at'] : '—',
		$integrityLabels[$status] ?? _('Unknown'),
		$artifact['vendor_version'] !== '' ? $artifact['vendor_version'] : '—',
		$artifact['bytes'] !== null ? $artifact['bytes'] : '—',
		$sha256 !== '' ? substr($sha256, 0, 16) : '—',
		$artifact['source_file'] !== '' ? $artifact['source_file'] : '—',
		$artifact['manifest_file'] !== '' ? $artifact['manifest_file'] : '—',
		$reason !== '' ? ($reasonLabels[$reason] ?? $reason) : '—'
	]);
}

$page
	->addItem(new CTag('h4', true, _('Stored rollback artifacts')))
	->addItem($artifactTable)
	->addItem(new CTag('p', true, _(
		'Artifacts are listed newest first. This view intentionally exposes metadata only; it does not provide download, delete, restore or rollback actions.'
	)));

if ($data['truncated']) {
	$page->addItem(new CTag('p', true, _(
		'Only the newest 50 manifest records are inspected and displayed. Older artifacts remain on disk but are not shown in this bounded view.'
	)));
}

$page->show();
