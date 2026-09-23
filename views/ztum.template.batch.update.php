<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(
					_('Back to template updates'),
					(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['error'] !== null || !is_array($data['result'])) {
	$page->addItem(FrontendUi::message(
		(string) ($data['error'] ?? _('Batch execution returned no result.')),
		FrontendUi::DANGER
	))->show();
	return;
}

$result = $data['result'];
$updated = is_array($result['updated'] ?? null) ? $result['updated'] : [];
$failed = is_array($result['failed'] ?? null) ? $result['failed'] : null;
$notAttempted = is_array($result['not_attempted'] ?? null) ? $result['not_attempted'] : [];

$summary = (new CTableInfo())
	->setHeader([_('Status'), _('Updated'), _('Failed'), _('Not attempted'), _('Any configuration write')])
	->addRow([
		FrontendUi::status(
			FrontendUi::reason((string) ($result['status'] ?? 'unknown')),
			($result['status'] ?? '') === 'completed' ? FrontendUi::SUCCESS : FrontendUi::DANGER
		),
		count($updated),
		$failed !== null ? 1 : 0,
		count($notAttempted),
		FrontendUi::yesNo(!empty($result['write_performed']))
	]);

$page
	->addItem(FrontendUi::section(_('Batch result')))
	->addItem($summary);

if ($updated !== []) {
	$table = (new CTableInfo())
		->setHeader([_('Template ID'), _('Result'), _('Installed version'), _('Validation')]);
	foreach ($updated as $item) {
		$table->addRow([
			(string) ($item['templateid'] ?? '—'),
			_('Updated'),
			(string) ($item['available_version'] ?? '') !== '' ? (string) $item['available_version'] : '—',
			FrontendUi::reason((string) ($item['validation_status'] ?? ''))
		]);
	}
	$page->addItem(FrontendUi::section(_('Updated templates')))->addItem($table);
}

if ($failed !== null) {
	$failure = (new CTableInfo())
		->setHeader([_('Template ID'), _('Status'), _('Reason'), _('Write performed for failed template')])
		->addRow([
			(string) ($failed['templateid'] ?? '—'),
			FrontendUi::reason((string) ($failed['status'] ?? 'unknown')),
			FrontendUi::reason((string) ($failed['reason'] ?? '')),
			FrontendUi::yesNo(!empty($failed['write_performed']))
		]);

	$page
		->addItem(FrontendUi::section(_('Execution stopped')))
		->addItem($failure)
		->addItem(FrontendUi::message(
			_('Execution stopped at the first failed template. Inspect that template and its rollback backup before retrying. No automatic rollback is performed.'),
			FrontendUi::WARNING
		));
}

if ($notAttempted !== []) {
	$table = (new CTableInfo())->setHeader([_('Template ID'), _('State')]);
	foreach ($notAttempted as $templateId) {
		$table->addRow([(string) $templateId, _('Not attempted')]);
	}
	$page->addItem(FrontendUi::section(_('Not attempted')))->addItem($table);
}

$page->show();
