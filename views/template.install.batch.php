<?php

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(
		_('Back to template catalog'),
		(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
	));

if ($data['error'] !== null) {
	$page->addItem(new CTag('p', true, $data['error']))->show();
	return;
}

$result = is_array($data['result']) ? $data['result'] : [];
$installed = is_array($result['installed'] ?? null) ? $result['installed'] : [];
$failed = is_array($result['failed'] ?? null) ? $result['failed'] : null;
$notAttempted = is_array($result['not_attempted'] ?? null) ? $result['not_attempted'] : [];

$page
	->addItem(new CTag('h4', true, _('Sequential batch installation result')))
	->addItem(
		(new CTableInfo())
			->setHeader([_('Status'), _('Installed'), _('Failed'), _('Not attempted'), _('Any configuration write')])
			->addRow([
				(string) ($result['status'] ?? 'unknown'),
				count($installed),
				$failed !== null ? 1 : 0,
				count($notAttempted),
				!empty($result['write_performed']) ? _('Yes') : _('No')
			])
	);

if ($installed !== []) {
	$table = (new CTableInfo())
		->setHeader([_('Template'), _('Template ID'), _('Installed version'), _('UUID'), _('Validation')]);

	foreach ($installed as $item) {
		$table->addRow([
			(string) ($item['name'] ?? '—'),
			(string) ($item['templateid'] ?? '—'),
			(string) ($item['available_version'] ?? '—'),
			(string) ($item['uuid'] ?? '—'),
			(string) ($item['validation_status'] ?? '—')
		]);
	}

	$page->addItem(new CTag('h4', true, _('Installed successfully')))->addItem($table);
}

if ($failed !== null) {
	$page
		->addItem(new CTag('h4', true, _('Execution stopped')))
		->addItem(
			(new CTableInfo())
				->setHeader([_('UUID'), _('Status'), _('Reason'), _('Configuration write performed')])
				->addRow([
					(string) ($failed['uuid'] ?? '—'),
					(string) ($failed['status'] ?? 'unknown'),
					(string) ($failed['reason'] ?? '') !== '' ? (string) $failed['reason'] : '—',
					!empty($failed['write_performed']) ? _('Yes') : _('No')
				])
		)
		->addItem(new CTag('p', true, _(
			'Execution stopped at the first non-successful template. No automatic uninstall is performed. Inspect the failed candidate before retrying.'
		)));
}

if ($notAttempted !== []) {
	$table = (new CTableInfo())->setHeader([_('UUID'), _('State')]);
	foreach ($notAttempted as $uuid) {
		$table->addRow([(string) $uuid, _('Not attempted')]);
	}
	$page->addItem(new CTag('h4', true, _('Not attempted')))->addItem($table);
}

$page->show();
