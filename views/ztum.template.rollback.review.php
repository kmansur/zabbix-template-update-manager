<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$statusLabels = [
	'ready' => _('Ready for explicit rollback confirmation'),
	'already_restored' => _('Selected backup already matches the installed template'),
	'blocked_identity' => _('Blocked — template identity mismatch')
];

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

if ($data['preflight_error'] !== null) {
	$page
		->addItem(FrontendUi::message((string) $data['preflight_error'], FrontendUi::DANGER))
		->show();
	return;
}

$preflight = is_array($data['preflight']) ? $data['preflight'] : [];
$status = (string) ($preflight['status'] ?? 'blocked_identity');
$template = is_array($preflight['template'] ?? null) ? $preflight['template'] : [];
$target = is_array($preflight['target'] ?? null) ? $preflight['target'] : [];
$summary = is_array($preflight['comparison_summary'] ?? null) ? $preflight['comparison_summary'] : null;
$evidenceSha = (string) ($preflight['evidence_sha256'] ?? '');

$stateTable = (new CTableInfo())
	->setHeader([_('Rollback preflight'), _('Reason'), _('Configuration write enabled')])
	->addRow([
		FrontendUi::status(
			$statusLabels[$status] ?? _('Unknown'),
			$status === 'ready'
				? FrontendUi::SUCCESS
				: ($status === 'already_restored' ? FrontendUi::INFO : FrontendUi::DANGER)
		),
		FrontendUi::reason(($preflight['reason'] ?? null) !== null ? (string) $preflight['reason'] : null),
		FrontendUi::yesNo(!empty($preflight['write_enabled']))
	]);

$page
	->addItem(FrontendUi::section(_('Rollback preflight')))
	->addItem($stateTable)
	->addItem(FrontendUi::description(_(
		'The selected backup and current template are revalidated before rollback. This page is read-only.'
	)));

if ($template !== []) {
	$templateTable = (new CTableInfo())
		->setHeader([_('Current template'), _('Technical name'), _('Current vendor version'), _('UUID')])
		->addRow([
			(string) ($template['name'] ?? '—'),
			(string) ($template['technical_name'] ?? '—'),
			(string) ($template['vendor_version'] ?? '—'),
			(string) ($template['uuid'] ?? '—')
		]);
	$page->addItem(FrontendUi::section(_('Current template')))->addItem($templateTable);
}

if ($target !== []) {
	$targetSha = (string) ($target['sha256'] ?? '');
	$targetTable = (new CTableInfo())
		->setHeader([
			_('Backup created'),
			_('Target vendor version'),
			_('Bytes'),
			_('SHA-256'),
			_('Manifest')
		])
		->addRow([
			(string) ($target['created_at'] ?? '—'),
			(string) ($target['vendor_version'] ?? '—'),
			(int) ($target['bytes'] ?? 0),
			$targetSha !== '' ? FrontendUi::fingerprint($targetSha, 20) : '—',
			(string) ($target['manifest_file'] ?? '—')
		]);
	$page->addItem(FrontendUi::section(_('Rollback target')))->addItem($targetTable);
}

if ($summary !== null) {
	$summaryTable = (new CTableInfo())
		->setHeader([_('Added'), _('Updated'), _('Removed'), _('Total changes')])
		->addRow([
			(int) ($summary['added'] ?? 0),
			(int) ($summary['updated'] ?? 0),
			(int) ($summary['removed'] ?? 0),
			(int) ($summary['total'] ?? 0)
		]);
	$page->addItem(FrontendUi::section(_('Import preview')))->addItem($summaryTable);
}

if ($status === 'ready' && $evidenceSha !== '') {
	$rollbackAction = (new CUrl('zabbix.php'))
		->setArgument('action', 'ztum.template.rollback')
		->getUrl();

	$form = (new CForm('post'))
		->setId('ztum-template-rollback-form')
		->setAction($rollbackAction)
		->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
		->addItem([
			(new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.template.rollback')))->removeId(),
			(new CVar('templateid', $data['templateid']))->removeId(),
			(new CVar('manifest_file', $data['manifest_file']))->removeId(),
			(new CVar('evidence_sha256', $evidenceSha))->removeId()
		])
		->addItem(
			(new CFormList())->addRow(
				_('Confirmation'),
				(new CCheckBox('confirm', '1'))->setLabel(_(
					'I reviewed the rollback target and want to restore this template.'
				))
			)
		)
		->addItem(makeFormFooter(new CSubmitButton(_('Rollback template'))));

	$page
		->addItem(FrontendUi::section(_('Rollback confirmation')))
		->addItem(FrontendUi::message(
			_('Rollback writes Zabbix configuration. A fresh recovery backup and preflight are created immediately before import; rollback is never retried automatically.'),
			FrontendUi::WARNING
		))
		->addItem($form);
}
elseif ($status === 'already_restored') {
	$page->addItem(FrontendUi::message(
		_('The selected backup already matches the installed template. No rollback is required.'),
		FrontendUi::INFO
	));
}
else {
	$page->addItem(FrontendUi::message(
		_('Rollback remains blocked. Resolve the reported issue and reopen the review.'),
		FrontendUi::WARNING
	));
}

$page->show();
