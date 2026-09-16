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
		'Selection scopes review only. Every template must still pass its own comparison, backup verification, fresh preflight and explicit confirmation before any configuration import can occur.'
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
		_('Next step')
	]);

foreach ($data['templates'] as $template) {
	$compareUrl = (new CUrl('zabbix.php'))
		->setArgument('action', 'ztum.template.compare')
		->setArgument('templateid', $template['templateid']);

	$eligible = ($template['upstream_status'] ?? null) === 'official_match'
		&& ($template['version_status'] ?? null) === 'update_available';

	$table->addRow([
		$template['name'],
		$template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
		($template['upstream_vendor_version'] ?? '') !== '' ? $template['upstream_vendor_version'] : '—',
		$versionLabels[$template['version_status'] ?? 'not_applicable'] ?? _('Unknown'),
		$upstreamLabels[$template['upstream_status'] ?? 'repository_unavailable'] ?? _('Unknown'),
		(int) $template['host_count'],
		$eligible ? new CLink(_('Review update'), $compareUrl) : _('No longer eligible for selected update review')
	]);
}

$page
	->addItem(new CTag('h4', true, _('Selected update candidates')))
	->addItem($table)
	->addItem(new CTag('p', true, _(
		'This page performs no bulk import. A future batch executor will reuse the same per-template safety boundary and stop on the first ambiguous write; the current beta keeps writes explicit per template while the selection workflow is field-validated.'
	)))
	->show();
