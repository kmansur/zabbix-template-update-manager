<?php

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
	$page->addItem(new CTag('p', true, $data['error'] ?? _('Batch execution returned no result.')))->show();
	return;
}

$result = $data['result'];
$updated = is_array($result['updated'] ?? null) ? $result['updated'] : [];
$failed = is_array($result['failed'] ?? null) ? $result['failed'] : null;
$notAttempted = is_array($result['not_attempted'] ?? null) ? $result['not_attempted'] : [];

$summary = (new CTableInfo())
	->setHeader([_('Status'), _('Updated'), _('Failed'), _('Not attempted'), _('Any configuration write')])
	->addRow([
		(string) ($result['status'] ?? 'unknown'),
		count($updated),
		$failed !== null ? 1 : 0,
		count($notAttempted),
		!empty($result['write_performed']) ? _('Yes') : _('No')
	]);

$page
	->addItem(new CTag('h4', true, _('Sequential batch result')))
	->addItem($summary);

if ($updated !== []) {
	$table = (new CTableInfo())
		->setHeader([_('Template ID'), _('Result'), _('Installed version'), _('Validation')]);
	foreach ($updated as $item) {
		$table->addRow([
			(string) ($item['templateid'] ?? '—'),
			_('Updated'),
			(string) ($item['available_version'] ?? '') !== '' ? (string) $item['available_version'] : '—',
			(string) ($item['validation_status'] ?? '') !== '' ? (string) $item['validation_status'] : '—'
		]);
	}
	$page->addItem(new CTag('h4', true, _('Updated successfully')))->addItem($table);
}

if ($failed !== null) {
	$failure = (new CTableInfo())
		->setHeader([_('Template ID'), _('Status'), _('Reason'), _('Write performed for failed template')])
		->addRow([
			(string) ($failed['templateid'] ?? '—'),
			(string) ($failed['status'] ?? 'unknown'),
			(string) ($failed['reason'] ?? '') !== '' ? (string) $failed['reason'] : '—',
			!empty($failed['write_performed']) ? _('Yes') : _('No')
		]);

	$page
		->addItem(new CTag('h4', true, _('Execution stopped')))
		->addItem($failure)
		->addItem(new CTag('p', true, _(
			'Execution stopped at the first non-successful template. Inspect the failed template and its rollback artifact before retrying. No automatic rollback is performed.'
		)));
}

if ($notAttempted !== []) {
	$table = (new CTableInfo())->setHeader([_('Template ID'), _('State')]);
	foreach ($notAttempted as $templateId) {
		$table->addRow([(string) $templateId, _('Not attempted')]);
	}
	$page->addItem(new CTag('h4', true, _('Not attempted')))->addItem($table);
}

$page->show();
