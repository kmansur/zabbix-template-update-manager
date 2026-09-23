<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

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
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(new CLink(_('Back to template comparison'), $compareUrl))
				->addItem(new CLink(_('Rollback backup history'), $backupsUrl))
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['operation_error'] !== null) {
	$page
		->addItem(FrontendUi::section(_('Update error')))
		->addItem(FrontendUi::message((string) $data['operation_error'], FrontendUi::DANGER))
		->addItem(FrontendUi::description(_(
			'Automatic retry is disabled. Reopen the comparison page and verify the installed state before retrying.'
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
		FrontendUi::status(
			$statusLabels[$status] ?? _('Unknown'),
			$status === 'updated'
				? FrontendUi::SUCCESS
				: ($status === 'validation_failed' ? FrontendUi::DANGER : FrontendUi::WARNING)
		),
		FrontendUi::yesNo($writePerformed),
		FrontendUi::reason(($result['reason'] ?? null) !== null ? (string) $result['reason'] : null),
		FrontendUi::reason((string) ($result['preflight_status'] ?? ''))
	]);

$page
	->addItem(FrontendUi::section(_('Update result')))
	->addItem($summaryTable);

if (is_array($result['candidate'] ?? null)) {
	$candidate = $result['candidate'];
	$candidateTable = (new CTableInfo())
		->setHeader([
			_('Installed target version'),
			_('Upstream commit'),
			_('Source path'),
			_('Raw source SHA-256'),
			_('Template content SHA-256'),
			_('Import SHA-256')
		])
		->addRow([
			(string) ($candidate['vendor_version'] ?? '—'),
			isset($candidate['commit']) ? FrontendUi::fingerprint((string) $candidate['commit'], 16) : '—',
			(string) ($candidate['path'] ?? '—'),
			isset($candidate['source_sha256']) ? FrontendUi::fingerprint((string) $candidate['source_sha256'], 20) : '—',
			isset($candidate['content_sha256']) ? FrontendUi::fingerprint((string) $candidate['content_sha256'], 20) : '—',
			isset($candidate['import_sha256']) ? FrontendUi::fingerprint((string) $candidate['import_sha256'], 20) : '—'
		]);
	$page->addItem(FrontendUi::section(_('Imported candidate')))->addItem($candidateTable);
}

if (isset($result['preflight_evidence_sha256'])) {
	$page
		->addItem(FrontendUi::section(_('Confirmed evidence')))
		->addItem(FrontendUi::description(
			FrontendUi::fingerprint((string) $result['preflight_evidence_sha256'], 24)
		));
}

if (!empty($result['manual_override'])) {
	$manualReasons = is_array($result['manual_reasons'] ?? null)
		? FrontendUi::reasonList($result['manual_reasons'])
		: '—';
	$page
		->addItem(FrontendUi::section(_('Manual review')))
		->addItem(FrontendUi::description(
			_('Accepted review conditions').': '.$manualReasons
		));
}


if (isset($result['rollback_sha256']) && (string) $result['rollback_sha256'] !== '') {
	$page
		->addItem(FrontendUi::section(_('Rollback protection')))
		->addItem(FrontendUi::description(
			FrontendUi::fingerprint((string) $result['rollback_sha256'], 24)
		));
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
			FrontendUi::reason((string) ($validation['status'] ?? '')),
			(string) ($validation['expected_version'] ?? '—'),
			(string) ($validation['installed_version'] ?? '—'),
			FrontendUi::reason((string) ($validation['content_status'] ?? '')),
			(int) ($validation['remaining_changes'] ?? -1)
		]);
	$page->addItem(FrontendUi::section(_('Post-import validation')))->addItem($validationTable);

	if (($validation['reasons'] ?? []) !== []) {
		$page->addItem(FrontendUi::description(
			_('Validation reasons').': '.FrontendUi::reasonList($validation['reasons'])
		));
	}
}

switch ($status) {
	case 'updated':
		$page->addItem(FrontendUi::message(
			_('The template was updated and validated successfully.'),
			FrontendUi::SUCCESS
		));
		break;

	case 'validation_failed':
		$page->addItem(FrontendUi::message(
			_('The import completed, but validation did not prove the expected upstream state. Inspect the comparison and keep the verified rollback backup available.'),
			FrontendUi::DANGER
		));
		break;

	case 'blocked_evidence_changed':
		$page->addItem(FrontendUi::message(
			_('The authoritative state changed before import. No write was performed; run preflight again.'),
			FrontendUi::WARNING
		));
		break;

	default:
		$page->addItem(FrontendUi::message(
			_('Fresh preflight did not pass, so no configuration import was attempted.'),
			FrontendUi::WARNING
		));
}

$page->show();
