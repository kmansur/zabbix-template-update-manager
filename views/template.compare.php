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

$threeWayStatusLabels = [
	'conflict_detected' => _('Conflict detected'),
	'needs_review' => _('Needs review'),
	'local_overwrite_risk' => _('Local customization overwrite risk'),
	'compatible_overlap' => _('Compatible / converged changes'),
	'upstream_only' => _('Upstream-only changes'),
	'no_changes' => _('No three-way differences')
];

$threeWayClassLabels = [
	'upstream_only' => _('Upstream only'),
	'local_only_overwrite' => _('Local only — current upstream would overwrite it'),
	'converged' => _('Converged to the same result'),
	'conflict' => _('Conflict'),
	'unresolved' => _('Unresolved')
];

$riskLevelLabels = [
	'none' => _('None'),
	'low' => _('Low'),
	'medium' => _('Medium'),
	'high' => _('High'),
	'conflict' => _('Conflict'),
	'unknown' => _('Unknown')
];

$riskCoverageLabels = [
	'complete' => _('Complete'),
	'incomplete' => _('Incomplete')
];

$entityLabels = [
	'templates' => _('Templates'),
	'items' => _('Items'),
	'item_prototypes' => _('Item prototypes'),
	'discovery_rules' => _('Discovery rules'),
	'discoveryRules' => _('Discovery rules'),
	'triggers' => _('Triggers'),
	'trigger_prototypes' => _('Trigger prototypes'),
	'graph_prototypes' => _('Graph prototypes'),
	'host_prototypes' => _('Host prototypes'),
	'graphs' => _('Graphs'),
	'dashboards' => _('Template dashboards'),
	'httptests' => _('Web scenarios'),
	'valuemaps' => _('Value maps'),
	'template_dashboards' => _('Template dashboards'),
	'templateDashboards' => _('Template dashboards'),
	'template_linkage' => _('Template linkage'),
	'templateLinkage' => _('Template linkage'),
	'value_maps' => _('Value maps'),
	'valueMaps' => _('Value maps'),
	'template_groups' => _('Template groups'),
	'host_groups' => _('Host groups')
];

$formatThreeWayValue = static function ($value): string {
	if (is_array($value) && ($value['__state'] ?? null) === 'missing') {
		return '∅';
	}
	if ($value === null) {
		return 'null';
	}
	if (is_bool($value)) {
		return $value ? 'true' : 'false';
	}
	if (is_scalar($value)) {
		$text = (string) $value;
	}
	else {
		$encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$text = is_string($encoded) ? $encoded : _('Unable to display value');
	}

	$limit = 240;
	return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
};

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

if (is_array($data['three_way_analysis'])) {
	$analysis = $data['three_way_analysis'];
	$threeWaySummary = $analysis['summary'];
	$threeWayStatus = (string) ($analysis['status'] ?? 'needs_review');

	$threeWayStatusTable = (new CTableInfo())
		->setHeader([
			_('Three-way status'),
			_('Upstream only'),
			_('Local overwrite risk'),
			_('Converged'),
			_('Conflicts'),
			_('Unresolved'),
			_('Affected entities')
		])
		->addRow([
			$threeWayStatusLabels[$threeWayStatus] ?? _('Unknown'),
			$threeWaySummary['upstream_only'],
			$threeWaySummary['local_only_overwrite'],
			$threeWaySummary['converged'],
			$threeWaySummary['conflict'],
			$threeWaySummary['unresolved'],
			$threeWaySummary['entities_affected']
		]);

	$page
		->addItem(new CTag('h4', true, _('Three-way change analysis')))
		->addItem($threeWayStatusTable)
		->addItem(new CTag('p', true, _(
			'BASE is the official historical template matching the installed vendor version, LOCAL is the currently installed template and UPSTREAM is the current official template.'
		)));

	if (($analysis['details'] ?? []) !== []) {
		$detailsTable = (new CTableInfo())
			->setHeader([
				_('Entity type'),
				_('Entity'),
				_('Field'),
				_('Classification'),
				_('BASE'),
				_('LOCAL'),
				_('UPSTREAM')
			]);

		foreach ($analysis['details'] as $detail) {
			$detailsTable->addRow([
				$entityLabels[$detail['entity_type']] ?? $detail['entity_type'],
				$detail['entity'],
				$detail['field'],
				$threeWayClassLabels[$detail['classification']] ?? _('Unknown'),
				$formatThreeWayValue($detail['base']),
				$formatThreeWayValue($detail['local']),
				$formatThreeWayValue($detail['upstream'])
			]);
		}

		$page->addItem(new CTag('h4', true, _('Three-way field details')))->addItem($detailsTable);

		if (!empty($analysis['details_truncated'])) {
			$page->addItem(new CTag('p', true, sprintf(
				_('Only the first %1$d detailed changes are displayed; summary counts include all analyzed changes.'),
				(int) ($analysis['detail_limit'] ?? 0)
			)));
		}
	}

	$page->addItem(new CTag('p', true, _(
		'A conflict means the same normalized field has different BASE, LOCAL and UPSTREAM values. A local overwrite risk means UPSTREAM still has the BASE value while LOCAL was customized, so importing the official template would tend to replace that customization. These classifications are review signals, not an automatic update-safety decision.'
	)));
}

if ($data['three_way_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['three_way_error']));
}

if (is_array($data['update_risk']) && is_array($data['update_preview'])) {
	$risk = $data['update_risk'];
	$preview = $data['update_preview'];
	$previewSummary = $preview['summary'];
	$riskLevel = (string) ($risk['level'] ?? 'unknown');
	$technicalLevel = (string) ($risk['technical_level'] ?? 'unknown');
	$coverage = (string) ($risk['coverage'] ?? 'incomplete');

	$riskTable = (new CTableInfo())
		->setHeader([
			_('Overall review priority'),
			_('Technical severity'),
			_('Three-way coverage'),
			_('Directly linked hosts'),
			_('Affected entities'),
			_('Normalized changes')
		])
		->addRow([
			$riskLevelLabels[$riskLevel] ?? _('Unknown'),
			$riskLevelLabels[$technicalLevel] ?? _('Unknown'),
			$riskCoverageLabels[$coverage] ?? _('Unknown'),
			(int) ($risk['direct_host_count'] ?? 0),
			(int) ($previewSummary['entities_affected'] ?? 0),
			(int) ($previewSummary['total'] ?? 0)
		]);

	$operationTable = (new CTableInfo())
		->setHeader([
			_('Added entities'),
			_('Removed entities'),
			_('Updated fields'),
			_('Unresolved identities')
		])
		->addRow([
			(int) ($previewSummary['added'] ?? 0),
			(int) ($previewSummary['removed'] ?? 0),
			(int) ($previewSummary['updated_fields'] ?? 0),
			(int) ($previewSummary['unresolved'] ?? 0)
		]);

	$page
		->addItem(new CTag('h4', true, _('Update review priority and known impact')))
		->addItem($riskTable)
		->addItem($operationTable);

	if ($riskLevel === 'unknown') {
		$page->addItem(new CTag('p', true, _(
			'Overall review priority is unknown because local-overlap coverage is incomplete. Technical severity is shown separately and must not be interpreted as an update-safety decision.'
		)));
	}
	elseif ($riskLevel === 'conflict') {
		$page->addItem(new CTag('p', true, _(
			'The update preview contains at least one confirmed three-way conflict. Manual review is required before any future update action can be considered.'
		)));
	}

	$page->addItem(new CTag('p', true, _(
		'The host impact count currently represents only hosts directly linked to this template. Inherited or indirect template impact is not yet included, and host count does not artificially change technical severity.'
	)));
}

if ($data['update_risk_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['update_risk_error']));
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
			'The installed template is older than upstream, and a historical official baseline matching the installed vendor version was found. The installed content matches that baseline, so no local modifications were detected. The review-priority section classifies the proposed upstream changes technically, but it does not authorize an update.'
		);
		break;

	case 'update_available_local_modifications':
		$interpretation = _(
			'The installed template is older than upstream and differs from the historical official baseline. The three-way and review-priority sections show whether local differences would be overwritten, have converged, conflict with upstream or still require unresolved review.'
		);
		break;

	case 'preview_against_newer_upstream':
		$interpretation = _(
			'The installed template is older than the official upstream version. The differences above are an update preview. A matching historical baseline could not be established, so local customization is not inferred and overall review priority remains unknown.'
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
		'This page uses configuration.importcompare only. Historical lookup, three-way analysis, risk analysis and source retrieval are read-only and do not import, update or delete Zabbix configuration.'
	)))
	->show();
