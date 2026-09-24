<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

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

$page->addItem(FrontendUi::description(_(
	'This is supplemental local operator history. Controlled preflight evidence and Zabbix configuration state remain authoritative.'
)));

if ($data['error'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['error'], FrontendUi::DANGER))->show();
	return;
}

$table = (new CTableInfo())
	->setNoDataMessage(
		_('No ZTUM operations have been recorded yet.'),
		_('Controlled updates, installations, rollbacks and policy changes will appear here.')
	)
	->setHeader([
		_('Time (UTC)'),
		_('Operation'),
		_('Subject'),
		_('Status'),
		_('Configuration write'),
		_('User ID'),
		_('Detail')
	])
	->setPageNavigation($data['paging']);

foreach ($data['entries'] as $entry) {
	$writePerformed = $entry['write_performed'] ?? null;
	$table->addRow([
		(string) ($entry['created_at'] ?? ''),
		ucfirst(str_replace(['_', '-'], ' ', (string) ($entry['operation'] ?? ''))),
		(string) ($entry['subject'] ?? ''),
		FrontendUi::status(
			ucfirst(str_replace(['_', '-'], ' ', (string) ($entry['status'] ?? 'unknown'))),
			in_array((string) ($entry['status'] ?? ''), ['updated', 'installed', 'rolled_back', 'completed'], true)
				? FrontendUi::SUCCESS
				: (str_starts_with((string) ($entry['status'] ?? ''), 'blocked')
					? FrontendUi::WARNING
					: FrontendUi::MUTED)
		),
		$writePerformed === null ? '—' : FrontendUi::yesNo((bool) $writePerformed),
		(string) ($entry['actor_userid'] ?? '') !== '' ? (string) $entry['actor_userid'] : '—',
		(string) ($entry['detail'] ?? '') !== '' ? (string) $entry['detail'] : '—'
	]);
}

$page
	->addItem(FrontendUi::section(_('Recent controlled operations')))
	->addItem($table)
	->show();
