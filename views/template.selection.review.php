<?php

$versionLabels = [
	'current' => _('Current'),
	'update_available' => _('Update available'),
	'installed_newer' => _('Installed version is newer'),
	'installed_version_missing' => _('Installed version missing'),
	'upstream_version_missing' => _('Upstream version missing'),
	'version_uncomparable' => _('Version format cannot be compared'),
	'not_applicable' => _('Not applicable')
];

$upstreamLabels = [
	'official_match' => _('Official UUID match'),
	'not_found' => _('Not found upstream'),
	'no_uuid' => _('No UUID'),
	'invalid_uuid' => _('Invalid UUID'),
	'repository_unavailable' => _('Repository unavailable')
];

$backUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');
$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(_('Back to template updates'), $backUrl))
	->addItem(new CTag('p', true, sprintf(
		_('%1$d template(s) were explicitly selected. Only this subset is shown in the review below.'),
		(int) $data['selected_count']
	)))
	->addItem(new CTag('p', true, _(
		'Selection scopes review only. Every template still has to pass comparison, historical baseline, three-way analysis, risk evaluation, rollback verification and fresh preflight before any configuration import can occur.'
	)));

if ($data['error'] !== null) {
	$page
		->addItem(new CTag('p', true, $data['error']))
		->show();
	return;
}

$table = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Installed version'),
		_('Available version'),
		_('Version status'),
		_('Upstream identity'),
		_('Linked hosts'),
		_('Individual review')
	]);

$eligibleIds = [];
foreach ($data['templates'] as $template) {
	$compareUrl = (new CUrl('zabbix.php'))
		->setArgument('action', 'ztum.template.compare')
		->setArgument('templateid', $template['templateid']);

	$eligible = ($template['upstream_status'] ?? null) === 'official_match'
		&& ($template['version_status'] ?? null) === 'update_available';
	if ($eligible) {
		$eligibleIds[] = (string) $template['templateid'];
	}

	$table->addRow([
		$template['name'],
		$template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
		($template['upstream_vendor_version'] ?? '') !== '' ? $template['upstream_vendor_version'] : '—',
		$versionLabels[$template['version_status'] ?? 'not_applicable'] ?? _('Unknown'),
		$upstreamLabels[$template['upstream_status'] ?? 'repository_unavailable'] ?? _('Unknown'),
		(int) $template['host_count'],
		$eligible ? new CLink(_('Review update'), $compareUrl) : _('No longer eligible')
	]);
}

$page
	->addItem(new CTag('h4', true, _('Selected update candidates')))
	->addItem($table);

if ($eligibleIds !== [] && !empty($data['can_prepare'])) {
	$prepareAction = (new CUrl('zabbix.php'))
		->setArgument('action', 'ztum.templates.prepare_selected')
		->getUrl();
	$form = (new CForm('post'))
		->setId('ztum-batch-prepare-form')
		->setAction($prepareAction)
		->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.templates.prepare_selected')))->removeId());

	foreach ($eligibleIds as $index => $templateId) {
		$form->addItem((new CVar('templateids['.$index.']', $templateId))->removeId());
	}

	$form->addItem(new CSubmitButton(_('Prepare selected updates')));

	$page
		->addItem(new CTag('h4', true, _('Batch safety preparation')))
		->addItem(new CTag('p', true, _(
			'Preparation runs the full safety analysis for every selected candidate. For low-risk candidates it may create or refresh a persistent rollback backup and then run a fresh preflight. It does not import Zabbix configuration.'
		)))
		->addItem($form);
}
elseif ($eligibleIds !== [] && empty($data['can_prepare'])) {
	$page->addItem(new CTag('p', true, _(
		'A Zabbix super administrator is required to prepare selected candidates for controlled sequential update.'
	)));
}
else {
	$page->addItem(new CTag('p', true, _(
		'None of the selected templates is still an authoritative official update candidate.'
	)));
}

$page->show();
