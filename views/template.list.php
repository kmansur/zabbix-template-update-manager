<?php

$compatibility = $data['zabbix_supported']
	? _('Supported')
	: _('Unsupported or undetected');

$vendorLabels = [
	'zabbix_vendor' => _('Vendor: Zabbix'),
	'other_vendor' => _('Other vendor'),
	'unidentified_vendor' => _('No vendor metadata')
];

$summaryTable = (new CTableInfo())
	->setHeader([
		_('Installed'),
		_('Vendor: Zabbix'),
		_('Other vendors'),
		_('No vendor metadata'),
		_('Without vendor version'),
		_('Linked to hosts')
	])
	->addRow([
		$data['summary']['total'],
		$data['summary']['zabbix_vendor'],
		$data['summary']['other_vendor'],
		$data['summary']['unidentified_vendor'],
		$data['summary']['without_vendor_version'],
		$data['summary']['in_use']
	]);

$templateTable = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Vendor'),
		_('Vendor version'),
		_('Classification'),
		_('Template groups'),
		_('Linked hosts'),
		_('UUID')
	]);

foreach ($data['templates'] as $template) {
	$templateName = $template['name'];
	if ($template['technical_name'] !== '' && $template['technical_name'] !== $template['name']) {
		$templateName .= ' ('.$template['technical_name'].')';
	}

	$templateTable->addRow([
		$templateName,
		$template['vendor_name'] !== '' ? $template['vendor_name'] : '—',
		$template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
		$vendorLabels[$template['vendor_classification']] ?? _('Unknown'),
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
}
else {
	$page
		->addItem(new CTag('h4', true, _('Inventory summary')))
		->addItem($summaryTable)
		->addItem(new CTag('h4', true, _('Installed templates')))
		->addItem($templateTable);
}

$page->show();
