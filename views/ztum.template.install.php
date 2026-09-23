<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$statusLabels = [
	'blocked_preflight' => _('Blocked — fresh installation preflight did not pass'),
	'blocked_evidence_changed' => _('Blocked — installation evidence changed'),
	'import_failed' => _('Import failed — inspect state'),
	'installed' => _('Installed and validated'),
	'validation_failed' => _('Installed but post-install validation failed')
];

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(
					_('Back to template catalog'),
					(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['operation_error'] !== null) {
	$page
		->addItem(FrontendUi::section(_('Installation error')))
		->addItem(FrontendUi::message((string) $data['operation_error'], FrontendUi::DANGER))
		->addItem(FrontendUi::description(_(
			'Refresh the catalog and inspect the local template state before retrying.'
		)))
		->show();
	return;
}

$result = is_array($data['result']) ? $data['result'] : [];
$status = (string) ($result['status'] ?? 'blocked_preflight');

$page
	->addItem(FrontendUi::section(_('Installation result')))
	->addItem(
		(new CTableInfo())
			->setHeader([
				_('Result'),
				_('Import attempted'),
				_('Confirmed configuration write'),
				_('Write outcome'),
				_('Reason'),
				_('Fresh preflight state')
			])
			->addRow([
				FrontendUi::status(
					$statusLabels[$status] ?? $status,
					$status === 'installed'
						? FrontendUi::SUCCESS
						: (in_array($status, ['validation_failed', 'import_failed'], true)
							? FrontendUi::DANGER
							: FrontendUi::WARNING)
				),
				FrontendUi::yesNo(!empty($result['write_attempted'])),
				FrontendUi::yesNo(!empty($result['write_performed'])),
				FrontendUi::reason((string) ($result['write_outcome'] ?? 'none')),
				FrontendUi::reason(($result['reason'] ?? null) !== null ? (string) $result['reason'] : null),
				FrontendUi::reason((string) ($result['preflight_status'] ?? ''))
			])
	);

if ($status === 'import_failed') {
	$detail = trim((string) ($result['error_detail'] ?? ''));
	if ($detail !== '') {
		$page->addItem(FrontendUi::message(
			_('Zabbix rejected the controlled import: ').$detail,
			FrontendUi::DANGER
		));
	}

	$inspection = is_array($result['failure_inspection'] ?? null) ? $result['failure_inspection'] : [];
	if ($inspection !== []) {
		$page
			->addItem(FrontendUi::section(_('Post-failure inspection')))
			->addItem(
				(new CTableInfo())
					->setHeader([_('Target state'), _('Matches'), _('Template ID'), _('Observed version')])
					->addRow([
						FrontendUi::reason((string) ($inspection['state'] ?? 'state_unknown_after_failure')),
						(int) ($inspection['match_count'] ?? 0),
						(string) (($inspection['templateid'] ?? '') !== '' ? $inspection['templateid'] : '—'),
						(string) (($inspection['vendor_version'] ?? '') !== '' ? $inspection['vendor_version'] : '—')
					])
			);
	}
}

$candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : [];
if ($candidate !== []) {
	$page
		->addItem(FrontendUi::section(_('Official candidate')))
		->addItem(
			(new CTableInfo())
				->setHeader([_('Template'), _('Version'), _('UUID'), _('Source path'), _('Import SHA-256')])
				->addRow([
					(string) ($candidate['name'] ?? '—'),
					(string) ($candidate['vendor_version'] ?? '—'),
					(string) ($candidate['uuid'] ?? $data['uuid']),
					(string) ($candidate['path'] ?? '—'),
					isset($candidate['import_sha256'])
						? FrontendUi::fingerprint((string) $candidate['import_sha256'], 20)
						: '—'
				])
		);
}

$validation = is_array($result['validation'] ?? null) ? $result['validation'] : [];
if ($validation !== []) {
	$page
		->addItem(FrontendUi::section(_('Post-install validation')))
		->addItem(
			(new CTableInfo())
				->setHeader([
					_('Validation'),
					_('Template ID'),
					_('Expected version'),
					_('Installed version'),
					_('Content state'),
					_('Remaining differences'),
					_('Raw differences'),
					_('Ignored shared-group differences')
				])
				->addRow([
					FrontendUi::reason((string) ($validation['status'] ?? '')),
					(string) ($validation['templateid'] ?? '—'),
					(string) ($validation['expected_version'] ?? '—'),
					(string) ($validation['installed_version'] ?? '—'),
					FrontendUi::reason((string) ($validation['content_status'] ?? '')),
					(int) ($validation['remaining_changes'] ?? -1),
					(int) ($validation['raw_remaining_changes'] ?? ($validation['remaining_changes'] ?? -1)),
					(int) ($validation['ignored_shared_changes'] ?? 0)
				])
		);

	if (($validation['reasons'] ?? []) !== []) {
		$page->addItem(FrontendUi::description(
			_('Validation reasons').': '.FrontendUi::reasonList($validation['reasons'])
		));
	}

	if ((int) ($validation['ignored_shared_changes'] ?? 0) > 0) {
		$page->addItem(FrontendUi::message(
			_('Validation ignored only differences in pre-existing shared host/template groups. Template-owned differences still fail validation.'),
			FrontendUi::INFO
		));
	}
}

if ($status === 'installed') {
	$page->addItem(FrontendUi::message(
		_('The official template was installed and validated successfully.'),
		FrontendUi::SUCCESS
	));
}
elseif ($status === 'validation_failed') {
	$page->addItem(FrontendUi::message(
		_('The import succeeded, but post-install validation did not prove the expected state. ZTUM does not automatically uninstall a newly created template; inspect the local state before taking further action.'),
		FrontendUi::DANGER
	));
}
else {
	$page->addItem(FrontendUi::message(
		_('No installation write was performed. Return to the catalog and review fresh evidence.'),
		FrontendUi::WARNING
	));
}

$page->show();
