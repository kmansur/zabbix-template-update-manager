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
	'update_available_no_local_modifications' => _('Update available — no local modifications detected'),
	'update_available_local_modifications' => _('Update available — local modifications detected'),
	'preview_against_newer_upstream' => _('Preview against newer upstream'),
	'historical_baseline_required' => _('Historical baseline required'),
	'not_available' => _('Not available')
];

$baselineLabels = [
	'found' => _('Historical baseline found'),
	'not_found' => _('Historical baseline not found'),
	'history_limit_reached' => _('Historical scan limit reached')
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
	->addItem(new CTag('h4', true, _('Current upstream update preview')))
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

	$page->addItem(new CTag('h4', true, _('Current-upstream changes by entity')))->addItem($entityTable);
}

if (is_array($data['historical_baseline'])) {
	$baseline = $data['historical_baseline'];
	$baselineStatus = (string) ($baseline['status'] ?? '');
	$baselineTable = (new CTableInfo())
		->setHeader([
			_('Baseline status'),
			_('Installed vendor version'),
			_('Baseline commit'),
			_('Commits examined')
		])
		->addRow([
			$baselineLabels[$baselineStatus] ?? _('Unknown'),
			(string) ($baseline['vendor_version'] ?? '—'),
			($baseline['commit'] ?? '') !== '' ? substr((string) $baseline['commit'], 0, 12) : '—',
			(int) ($baseline['commits_examined'] ?? 0)
		]);

	$page->addItem(new CTag('h4', true, _('Historical official baseline')))->addItem($baselineTable);

	if ($baselineStatus === 'found') {
		$historical = $data['historical_summary'];
		$historicalTable = (new CTableInfo())
			->setHeader([_('Added'), _('Updated'), _('Removed'), _('Total local differences')])
			->addRow([
				$historical['added'],
				$historical['updated'],
				$historical['removed'],
				$historical['total']
			]);
		$page->addItem(new CTag('h4', true, _('Installed content vs historical baseline')))->addItem($historicalTable);
	}
}

if ($data['historical_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['historical_error']));
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

	case 'update_available_no_local_modifications':
		$interpretation = _(
			'The installed template is older than upstream, and a historical official baseline matching the installed vendor version was found. The installed content matches that baseline, so no local modifications were detected. The changes in the current-upstream preview are upstream evolution, although operational update risk is not yet classified.'
		);
		break;

	case 'update_available_local_modifications':
		$interpretation = _(
			'The installed template is older than upstream, and it differs from the historical official baseline matching its installed vendor version. Local modifications are therefore present. The module does not yet classify whether those local changes conflict with the newer upstream changes.'
		);
		break;

	case 'preview_against_newer_upstream':
		$interpretation = _(
			'The installed template is older than the official upstream version. The differences above are an update preview. A matching historical baseline could not be established, so local customization is not inferred.'
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
		'This page uses configuration.importcompare only. Historical lookup and source retrieval are read-only and do not import, update or delete Zabbix configuration.'
	)))
	->show();
