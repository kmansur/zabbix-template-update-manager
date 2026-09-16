<?php

$categoryLabels = [
	'ready' => _('Ready'),
	'review' => _('Manual review'),
	'conflict' => _('Conflict / local overwrite'),
	'blocked' => _('Blocked')
];

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(
		_('Back to template updates'),
		(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
	));

if ($data['error'] !== null || !is_array($data['plan'])) {
	$page->addItem(new CTag('p', true, $data['error'] ?? _('Batch preparation returned no plan.')))->show();
	return;
}

$plan = $data['plan'];
$summary = is_array($plan['summary'] ?? null) ? $plan['summary'] : [];
$summaryTable = (new CTableInfo())
	->setHeader([_('Selected'), _('Ready'), _('Manual review'), _('Conflict'), _('Blocked')])
	->addRow([
		(int) ($summary['selected'] ?? 0),
		(int) ($summary['ready'] ?? 0),
		(int) ($summary['review'] ?? 0),
		(int) ($summary['conflict'] ?? 0),
		(int) ($summary['blocked'] ?? 0)
	]);

$page
	->addItem(new CTag('h4', true, _('Batch preparation summary')))
	->addItem($summaryTable)
	->addItem(new CTag('p', true, _(
		'Preparation may create persistent rollback artifacts, but it does not import Zabbix configuration. Only templates with a freshly passed per-template preflight become Ready.'
	)));

$table = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Installed'),
		_('Available'),
		_('Linked hosts'),
		_('Readiness'),
		_('Batch class'),
		_('Reason')
	]);

$readyIds = [];
$evidence = [];
foreach ($plan['items'] as $item) {
	$category = (string) ($item['category'] ?? 'blocked');
	if ($category === 'ready') {
		$templateId = (string) $item['templateid'];
		$readyIds[] = $templateId;
		$evidence[$templateId] = (string) ($item['evidence_sha256'] ?? '');
	}

	$table->addRow([
		(string) ($item['name'] ?? $item['templateid']),
		(string) ($item['installed_version'] ?? '') !== '' ? (string) $item['installed_version'] : '—',
		(string) ($item['available_version'] ?? '') !== '' ? (string) $item['available_version'] : '—',
		(int) ($item['host_count'] ?? 0),
		(string) ($item['readiness_status'] ?? '—'),
		$categoryLabels[$category] ?? _('Blocked'),
		(string) ($item['reason'] ?? '') !== '' ? (string) $item['reason'] : '—'
	]);
}

$page->addItem(new CTag('h4', true, _('Prepared templates')))->addItem($table);

if ($readyIds !== []) {
	$action = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates.batch_update')->getUrl();
	$form = (new CForm('post'))
		->setId('ztum-batch-update-form')
		->setAction($action)
		->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.templates.batch_update')))->removeId());

	foreach ($readyIds as $index => $templateId) {
		$form->addItem((new CVar('templateids['.$index.']', $templateId))->removeId());
		$form->addItem((new CVar('evidence['.$templateId.']', $evidence[$templateId]))->removeId());
	}

	$form->addItem([
		(new CCheckBox('confirm', '1'))->setLabel(sprintf(
			_('I reviewed the batch plan and want to update the %1$d Ready template(s) sequentially.'),
			count($readyIds)
		)),
		new CSubmitButton(_('Update ready templates'))
	]);

	$page
		->addItem(new CTag('h4', true, _('Controlled sequential execution')))
		->addItem(new CTag('p', true, _(
			'Each template reruns the complete preflight immediately before its own import. Execution stops on the first failure, evidence change, ambiguous state or validation problem. Templates after that point are not attempted.'
		)))
		->addItem($form);
}
else {
	$page->addItem(new CTag('p', true, _(
		'No selected template is currently eligible for controlled batch execution. Resolve manual-review, conflict or blocked states individually.'
	)));
}

$page->show();
