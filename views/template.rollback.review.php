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
		->addItem(new CTag('p', true, $data['preflight_error']))
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
		($preflight['reason'] ?? null) !== null ? (string) $preflight['reason'] : '—',
		FrontendUi::yesNo(!empty($preflight['write_enabled']))
	]);

$page
	->addItem(new CTag('h4', true, _('Fresh rollback preflight')))
	->addItem($stateTable)
	->addItem(new CTag('p', true, _(
		'This page revalidates the selected artifact, exports the currently installed template and runs configuration.importcompare. It does not modify Zabbix configuration.'
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
	$page->addItem(new CTag('h4', true, _('Current installed state')))->addItem($templateTable);
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
			$targetSha !== '' ? substr($targetSha, 0, 20) : '—',
			(string) ($target['manifest_file'] ?? '—')
		]);
	$page->addItem(new CTag('h4', true, _('Selected rollback target')))->addItem($targetTable);
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
	$page->addItem(new CTag('h4', true, _('Rollback import preview')))->addItem($summaryTable);
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
					'I understand that this will import the selected stored template and change Zabbix configuration.'
				))
			)
		)
		->addItem(makeFormFooter(new CSubmitButton(_('Rollback template'))));

	$page
		->addItem(new CTag('h4', true, _('Explicit rollback confirmation')))
		->addItem(new CTag('p', true, _(
			'Immediately before import, the module will rerun this preflight, create and verify a fresh recovery backup of the current state, revalidate the selected artifact again and refuse the rollback if any evidence changed.'
		)))
		->addItem(new CTag('p', true, _(
			'The rollback is never retried automatically. If the import succeeds but validation fails, manual inspection is required.'
		)))
		->addItem($form);
}
elseif ($status === 'already_restored') {
	$page->addItem(new CTag('p', true, _(
		'No rollback action is offered because configuration.importcompare reports no differences between the installed template and the selected stored artifact.'
	)));
}
else {
	$page->addItem(new CTag('p', true, _(
		'Rollback remains blocked. No configuration-write control is available for this state.'
	)));
}

$page->show();
