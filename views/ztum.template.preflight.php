<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

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
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CLink(_('Back to template comparison'), $backUrl))
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['preflight_error'] !== null) {
	$page
		->addItem(FrontendUi::message((string) $data['preflight_error'], FrontendUi::DANGER))
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
		FrontendUi::status(
			$statusLabels[$status] ?? _('Unknown'),
			$status === 'passed' ? FrontendUi::SUCCESS : FrontendUi::DANGER
		),
		FrontendUi::reason(($preflight['reason'] ?? null) !== null ? (string) $preflight['reason'] : null),
		FrontendUi::reason((string) ($preflight['next_step'] ?? '')),
		FrontendUi::yesNo(!empty($preflight['write_enabled']))
	]);

$page
	->addItem(FrontendUi::section(_('Update preflight')))
	->addItem($stateTable)
	->addItem(FrontendUi::description(_(
		'This preflight was recomputed from the current template, current validated upstream source and newest verified rollback backup.'
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

	$page->addItem(FrontendUi::section(_('Template')))->addItem($templateTable);
}

if ($candidate !== []) {
	$candidateTable = (new CTableInfo())
		->setHeader([
			_('Available version'),
			_('Vendor'),
			_('Upstream commit'),
			_('Official source path'),
			_('Raw source SHA-256'),
			_('Template content SHA-256')
		])
		->addRow([
			(string) ($candidate['vendor_version'] ?? '—'),
			(string) ($candidate['vendor_name'] ?? '—'),
			isset($candidate['commit']) ? FrontendUi::fingerprint((string) $candidate['commit'], 16) : '—',
			(string) ($candidate['path'] ?? '—'),
			isset($candidate['source_sha256']) ? FrontendUi::fingerprint((string) $candidate['source_sha256'], 20) : '—',
			isset($candidate['content_sha256']) ? FrontendUi::fingerprint((string) $candidate['content_sha256'], 20) : '—'
		]);

	$page->addItem(FrontendUi::section(_('Official candidate')))->addItem($candidateTable);
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
			isset($rollback['sha256']) ? FrontendUi::fingerprint((string) $rollback['sha256'], 20) : '—',
			isset($rollback['current_export_sha256'])
				? FrontendUi::fingerprint((string) $rollback['current_export_sha256'], 20)
				: '—'
		]);

	$page->addItem(FrontendUi::section(_('Rollback protection')))->addItem($rollbackTable);
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
		->addItem(FrontendUi::section(_('Manual review')))
		->addItem(FrontendUi::message(
			_('This update requires explicit manual review before import.'),
			FrontendUi::WARNING
		))
		->addItem(FrontendUi::description(implode('; ', $labels)));
}

if ($evidenceSha !== '') {
	$page
		->addItem(FrontendUi::section(_('Evidence fingerprint')))
		->addItem(FrontendUi::description(FrontendUi::fingerprint($evidenceSha, 24)));
}

if ($status === 'passed') {
	$page->addItem(FrontendUi::message(
		_('Preflight passed. The same checks will run again immediately before import.'),
		FrontendUi::SUCCESS
	));

	if (!empty($data['can_update']) && $evidenceSha !== '' && $template !== []) {
		$updateAction = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.update')
			->getUrl();
		$confirmationList = (new CFormList())
			->addRow(
				_('Confirmation'),
				(new CCheckBox('confirm', '1'))->setLabel(_(
					'I reviewed the candidate and verified rollback evidence and want to update this template.'
				))
			);

		$hiddenItems = [
			(new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.template.update')))->removeId(),
			(new CVar('templateid', (string) $template['templateid']))->removeId(),
			(new CVar('evidence_sha256', $evidenceSha))->removeId()
		];

		if ($manualOverride) {
			$hiddenItems[] = (new CVar('manual_override', '1'))->removeId();
			$confirmationList->addRow(
				_('Reviewed override'),
				(new CCheckBox('confirm_manual_override', '1'))->setLabel(_(
					'I explicitly accept the reviewed technical-risk conditions above.'
				))
			);

			if (in_array('local_customization_overwrite', $manualReasons, true)) {
				$confirmationList->addRow(
					_('Local customization overwrite'),
					(new CCheckBox('confirm_local_overwrite', '1'))->setLabel(_(
						'I explicitly accept overwriting or removing the local customizations identified above.'
					))
				);
			}
		}

		$updateForm = (new CForm('post'))
			->setId('ztum-template-update-form')
			->setAction($updateAction)
			->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
			->addItem($hiddenItems)
			->addItem($confirmationList)
			->addItem(makeFormFooter(
				new CSubmitButton(
					$manualOverride
						? _('Update official template with reviewed override')
						: _('Update official template')
				)
			));

		$page
			->addItem(FrontendUi::section(_('Update confirmation')))
			->addItem(FrontendUi::message(
				_('This operation writes Zabbix configuration. The verified rollback backup is retained after the update.'),
				FrontendUi::WARNING
			))
			->addItem($updateForm);
	}
	else {
		$page->addItem(FrontendUi::message(
			_('A Zabbix Super Admin is required to perform the controlled update.'),
			FrontendUi::WARNING
		));
	}
}
else {
	$page->addItem(FrontendUi::message(
		_('Update remains blocked. Resolve the reported prerequisite and run preflight again.'),
		FrontendUi::WARNING
	));
}

$page->show();
