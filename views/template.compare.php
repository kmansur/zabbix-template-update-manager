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

$contentLabels = [
	'matches_current_upstream' => _('Content matches current upstream'),
	'local_modifications_detected' => _('Local modifications detected'),
	'preview_against_newer_upstream' => _('Preview against newer upstream'),
	'historical_baseline_required' => _('Historical baseline required'),
	'not_available' => _('Not available')
];

$entityLabels = [
	'templates' => _('Templates'),
	'items' => _('Items'),
	'discovery_rules' => _('Discovery rules'),
	'discoveryRules' => _('Discovery rules'),
	'triggers' => _('Triggers'),
	'graphs' => _('Graphs'),
	'httptests' => _('Web scenarios'),
	'template_dashboards' => _('Template dashboards'),
	'templateDashboards' => _('Template dashboards'),
	'template_linkage' => _('Template linkage'),
	'templateLinkage' => _('Template linkage'),
	'value_maps' => _('Value maps'),
	'valueMaps' => _('Value maps'),
	'template_groups' => _('Template groups'),
	'host_groups' => _('Host groups')
];

$backUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');
$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CLink(_('Back to template updates'), $backUrl));

if (is_array($data['template'])) {
	$template = $data['template'];
	$sourceCommit = is_array($data['upstream_source'])
		? (string) ($data['upstream_source']['commit'] ?? '')
		: '';
	$metadataTable = (new CTableInfo())
		->setHeader([
			_('Template'),
			_('Installed version'),
			_('Available version'),
			_('Version status'),
			_('Content status'),
			_('Source path'),
			_('Source commit')
		])
		->addRow([
			$template['name'],
			$template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
			($template['upstream_vendor_version'] ?? '') !== '' ? $template['upstream_vendor_version'] : '—',
			$versionLabels[$template['version_status'] ?? 'not_applicable'] ?? _('Unknown'),
			$contentLabels[$data['content_status']] ?? _('Unknown'),
			$data['source_path'] !== '' ? $data['source_path'] : '—',
			$sourceCommit !== '' ? substr($sourceCommit, 0, 12) : '—'
		]);

	$page->addItem(new CTag('h4', true, _('Comparison target')))->addItem($metadataTable);
}

if ($data['comparison_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['comparison_error']));
	$page->show();
	return;
}

$summary = $data['comparison_summary'];
$summaryTable = (new CTableInfo())
	->setHeader([_('Added'), _('Updated'), _('Removed'), _('Total changes')])
	->addRow([$summary['added'], $summary['updated'], $summary['removed'], $summary['total']]);

$page
	->addItem(new CTag('h4', true, _('Read-only import comparison summary')))
	->addItem($summaryTable);

if ($summary['by_entity'] !== []) {
	$entityTable = (new CTableInfo())
		->setHeader([_('Entity'), _('Added'), _('Updated'), _('Removed')]);

	foreach ($summary['by_entity'] as $entity => $counts) {
		$entityTable->addRow([
			$entityLabels[$entity] ?? $entity,
			$counts['added'],
			$counts['updated'],
			$counts['removed']
		]);
	}

	$page->addItem(new CTag('h4', true, _('Changes by entity')))->addItem($entityTable);
}

switch ($data['content_status']) {
	case 'matches_current_upstream':
		$interpretation = _(
			'The installed template uses the same official vendor version and Zabbix import comparison found no content differences.'
		);
		break;

	case 'local_modifications_detected':
		$interpretation = _(
			'The installed template uses the same official vendor version, but Zabbix import comparison found differences. These differences are treated as local modifications.'
		);
		break;

	case 'preview_against_newer_upstream':
		$interpretation = _(
			'The installed template is older than the official upstream version. The differences above are a preview of what the newer upstream content would change; they do not yet prove local customization because a historical baseline of the installed version is required.'
		);
		break;

	case 'historical_baseline_required':
		$interpretation = _(
			'A historical official baseline matching the installed vendor version is required before local modifications can be classified safely.'
		);
		break;

	default:
		$interpretation = _('Content comparison is not available for this template state.');
}

$page
	->addItem(new CTag('h4', true, _('Interpretation')))
	->addItem(new CTag('p', true, $interpretation))
	->addItem(new CTag('p', true, _(
		'This page uses configuration.importcompare only. It does not import, update or delete Zabbix configuration.'
	)))
	->show();
