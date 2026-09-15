<?php

$compatibility = $data['zabbix_supported']
	? _('Supported')
	: _('Unsupported or undetected');

$upstreamLabels = [
	'official_match' => _('Official UUID match'),
	'not_found' => _('Not found upstream'),
	'no_uuid' => _('No UUID'),
	'invalid_uuid' => _('Invalid UUID'),
	'repository_unavailable' => _('Repository unavailable')
];

$versionLabels = [
	'current' => _('Current'),
	'update_available' => _('Update available'),
	'installed_newer' => _('Installed version is newer'),
	'installed_version_missing' => _('Installed version missing'),
	'upstream_version_missing' => _('Upstream version missing'),
	'version_uncomparable' => _('Version format cannot be compared'),
	'not_applicable' => _('Not applicable')
];

$summaryTable = (new CTableInfo())
	->setHeader([
		_('Visible templates'),
		_('Vendor: Zabbix'),
		_('Other vendors'),
		_('No vendor metadata'),
		_('Without vendor version'),
		_('Templates linked to hosts')
	])
	->addRow([
		$data['summary']['total'],
		$data['summary']['zabbix_vendor'],
		$data['summary']['other_vendor'],
		$data['summary']['unidentified_vendor'],
		$data['summary']['without_vendor_version'],
		$data['summary']['in_use']
	]);

$upstreamTable = (new CTableInfo())
	->setHeader([
		_('Official UUID match'),
		_('Not found upstream'),
		_('No UUID'),
		_('Invalid UUID'),
		_('Repository unavailable')
	])
	->addRow([
		$data['upstream_summary']['official_match'],
		$data['upstream_summary']['not_found'],
		$data['upstream_summary']['no_uuid'],
		$data['upstream_summary']['invalid_uuid'],
		$data['upstream_summary']['repository_unavailable']
	]);

$versionTable = (new CTableInfo())
	->setHeader([
		_('Current'),
		_('Updates available'),
		_('Installed newer'),
		_('Installed version missing'),
		_('Upstream version missing'),
		_('Cannot compare'),
		_('Not applicable')
	])
	->addRow([
		$data['version_summary']['current'],
		$data['version_summary']['update_available'],
		$data['version_summary']['installed_newer'],
		$data['version_summary']['installed_version_missing'],
		$data['version_summary']['upstream_version_missing'],
		$data['version_summary']['version_uncomparable'],
		$data['version_summary']['not_applicable']
	]);

$templateTable = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Vendor'),
		_('Installed version'),
		_('Available version'),
		_('Version status'),
		_('Upstream identity'),
		_('Template groups'),
		_('Linked hosts'),
		_('UUID')
	]);

foreach ($data['templates'] as $template) {
	$templateName = $template['name'];
	if ($template['technical_name'] !== '' && $template['technical_name'] !== $template['name']) {
		$templateName .= ' ('.$template['technical_name'].')';
	}

	$templateCell = $templateName;
	if ($data['can_compare'] && ($template['upstream_status'] ?? null) === 'official_match') {
		$compareUrl = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.compare')
			->setArgument('templateid', $template['templateid']);
		$templateCell = new CLink($templateName, $compareUrl);
	}

	$templateTable->addRow([
		$templateCell,
		$template['vendor_name'] !== '' ? $template['vendor_name'] : '—',
		$template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
		($template['upstream_vendor_version'] ?? '') !== '' ? $template['upstream_vendor_version'] : '—',
		$versionLabels[$template['version_status'] ?? 'not_applicable'] ?? _('Unknown'),
		$upstreamLabels[$template['upstream_status'] ?? 'repository_unavailable'] ?? _('Unknown'),
		$template['groups'] !== [] ? implode(', ', $template['groups']) : '—',
		$template['host_count'],
		$template['uuid'] !== '' ? $template['uuid'] : '—'
	]);
}

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(
		new CDiv([
			new CTag('p', true, _('Module version: ').$data['version']),
			new CTag('p', true, _('Zabbix version: ').$data['zabbix_version']),
			new CTag('p', true, _('Compatibility: ').$compatibility),
			new CTag('p', true, _('Status: ').$data['status'])
		])
	);

if ($data['inventory_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['inventory_error']));
	$page->show();
	return;
}

$page
	->addItem(new CTag('h4', true, _('Inventory summary')))
	->addItem($summaryTable);

if (is_array($data['upstream_source'])) {
	$sourceRef = (string) ($data['upstream_source']['ref'] ?? '');
	$sourceCommit = (string) ($data['upstream_source']['commit'] ?? '');
	$sourceLine = (string) ($data['upstream_source']['line'] ?? '');
	$cacheStatus = (string) ($data['upstream_runtime']['cache_status'] ?? 'unknown');

	$page->addItem(
		new CDiv([
			new CTag('p', true, _('Upstream line: ').$sourceLine),
			new CTag('p', true, _('Upstream ref: ').$sourceRef),
			new CTag('p', true, _('Upstream commit: ').($sourceCommit !== '' ? substr($sourceCommit, 0, 12) : '—')),
			new CTag('p', true, _('Index cache: ').$cacheStatus)
		])
	);
}

if ($data['upstream_warning'] !== null) {
	$page->addItem(new CTag('p', true, $data['upstream_warning']));
}

if ($data['upstream_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['upstream_error']));
}

$page
	->addItem(new CTag('h4', true, _('Upstream identity summary')))
	->addItem($upstreamTable)
	->addItem(new CTag('h4', true, _('Official template version summary')))
	->addItem($versionTable)
	->addItem(new CTag('p', true, _(
		'Version status compares official vendor versions only. It does not by itself determine update safety.'
	)));

if ($data['can_compare']) {
	$page->addItem(new CTag('p', true, _(
		'For templates with an official UUID match, select the template name to run a read-only content comparison.'
	)));
}

$page
	->addItem(new CTag('h4', true, _('Visible templates')))
	->addItem($templateTable)
	->show();
