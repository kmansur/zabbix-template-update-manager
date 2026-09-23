<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$backUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.backups')
	->setArgument('templateid', $data['templateid']);

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CLink(_('Back to rollback backup history'), $backUrl))
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['operation_error'] !== null) {
	$page
		->addItem(FrontendUi::message((string) $data['operation_error'], FrontendUi::DANGER))
		->addItem(FrontendUi::description(_(
			'Inspect the installed template and frontend logs before taking another write action.'
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
		FrontendUi::status(
			$statusLabels[$status] ?? $status,
			$status === 'rolled_back'
				? FrontendUi::SUCCESS
				: (str_starts_with($status, 'blocked_') ? FrontendUi::WARNING : FrontendUi::DANGER)
		),
		FrontendUi::yesNo($writePerformed),
		FrontendUi::reason(($result['reason'] ?? null) !== null ? (string) $result['reason'] : null)
	]);

$page
	->addItem(FrontendUi::section(_('Rollback result')))
	->addItem($stateTable);

$recovery = is_array($result['recovery_backup'] ?? null) ? $result['recovery_backup'] : [];
if ($recovery !== []) {
	$sha = (string) ($recovery['sha256'] ?? '');
	$recoveryTable = (new CTableInfo())
		->setHeader([_('Created'), _('Bytes'), _('SHA-256'), _('Manifest'), _('YAML')])
		->addRow([
			(string) ($recovery['created_at'] ?? '—'),
			(int) ($recovery['bytes'] ?? 0),
			$sha !== '' ? FrontendUi::fingerprint($sha, 20) : '—',
			(string) ($recovery['manifest_file'] ?? '—'),
			(string) ($recovery['source_file'] ?? '—')
		]);
	$page
		->addItem(FrontendUi::section(_('Recovery backup')))
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
	$page->addItem(FrontendUi::section(_('Rollback target')))->addItem($targetTable);
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
			FrontendUi::reasonList(is_array($validation['reasons'] ?? null) ? $validation['reasons'] : [])
		]);
	$page->addItem(FrontendUi::section(_('Post-rollback validation')))->addItem($validationTable);
}

if ($status === 'rolled_back') {
	$page->addItem(FrontendUi::message(
		_('Rollback completed and validation found no remaining differences.'),
		FrontendUi::SUCCESS
	));
}
elseif ($writePerformed) {
	$page->addItem(FrontendUi::message(
		_('A configuration write occurred, but the final state was not proven valid. Inspect the template before any further write action.'),
		FrontendUi::DANGER
	));
}
else {
	$page->addItem(FrontendUi::message(
		_('No configuration write was performed. Reopen rollback review to obtain fresh evidence before retrying.'),
		FrontendUi::WARNING
	));
}

$page->show();
