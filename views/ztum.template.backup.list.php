<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

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
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CLink(_('Back to template updates'), $backUrl))
		))->setAttribute('aria-label', _('Content controls'))
	);

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

	$page->addItem(FrontendUi::section(_('Template')))->addItem($templateTable);
}

if ($data['error'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['error'], FrontendUi::DANGER));
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
		FrontendUi::status(
			$repositoryLabels[$data['repository_status']] ?? _('Unknown'),
			$data['repository_status'] === 'ok'
				? FrontendUi::SUCCESS
				: ($data['repository_status'] === 'no_backup' ? FrontendUi::MUTED : FrontendUi::DANGER)
		),
		$data['scanned'],
		$data['valid'],
		$data['invalid'],
		$data['truncated'] ? _('Yes') : _('No')
	]);

$page
	->addItem(FrontendUi::section(_('Rollback repository')))
	->addItem($summaryTable);

if ($data['repository_status'] === 'repository_unavailable') {
	$page
		->addItem(FrontendUi::message(
			_('The rollback repository is unavailable or cannot be inspected safely.'),
			FrontendUi::DANGER
		))
		->addItem(FrontendUi::description(_(
			'Check frontend logs, repository ownership and permissions before relying on rollback backups.'
		)))
		->show();
	return;
}

if ($data['artifacts'] === []) {
	$page
		->addItem(FrontendUi::message(
			_('No rollback backups are stored for this template.'),
			FrontendUi::INFO
		))
		->addItem(FrontendUi::description(_(
			'Create a rollback backup from the comparison page when the readiness gate allows it.'
		)))
		->show();
	return;
}

$headers = [
	_('Created at'),
	_('Integrity'),
	_('Vendor version'),
	_('Bytes'),
	_('SHA-256'),
	_('YAML file'),
	_('Manifest file'),
	_('Issue')
];
if (!empty($data['can_rollback'])) {
	$headers[] = _('Action');
}

$artifactTable = (new CTableInfo())->setHeader($headers);

foreach ($data['artifacts'] as $artifact) {
	$status = (string) ($artifact['status'] ?? 'invalid');
	$reason = (string) ($artifact['reason'] ?? '');
	$sha256 = (string) ($artifact['sha256'] ?? '');

	$row = [
		$artifact['created_at'] !== '' ? $artifact['created_at'] : '—',
		FrontendUi::status(
			$integrityLabels[$status] ?? _('Unknown'),
			$status === 'valid' ? FrontendUi::SUCCESS : FrontendUi::DANGER
		),
		$artifact['vendor_version'] !== '' ? $artifact['vendor_version'] : '—',
		$artifact['bytes'] !== null ? $artifact['bytes'] : '—',
		$sha256 !== '' ? FrontendUi::fingerprint($sha256, 16) : '—',
		$artifact['source_file'] !== '' ? $artifact['source_file'] : '—',
		$artifact['manifest_file'] !== '' ? $artifact['manifest_file'] : '—',
		$reason !== '' ? ($reasonLabels[$reason] ?? $reason) : '—'
	];

	if (!empty($data['can_rollback'])) {
		if ($status === 'valid' && $artifact['manifest_file'] !== '') {
			$reviewUrl = (new CUrl('zabbix.php'))
				->setArgument('action', 'ztum.template.rollback.review')
				->setArgument('templateid', (string) $data['template']['templateid'])
				->setArgument('manifest_file', $artifact['manifest_file']);
			$row[] = new CLink(_('Review rollback'), $reviewUrl);
		}
		else {
			$row[] = '—';
		}
	}

	$artifactTable->addRow($row);
}

$page
	->addItem(FrontendUi::section(_('Stored rollback backups')))
	->addItem($artifactTable)
	->addItem(FrontendUi::description(_(
		'Backups are listed newest first. Invalid backups cannot be selected for rollback. Rollback review is read-only until explicit confirmation.'
	)));

if ($data['truncated']) {
	$page->addItem(FrontendUi::description(_(
		'Only the newest 50 backup records are inspected and displayed. Older backups remain on disk.'
	)));
}

$page->show();
