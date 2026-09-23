<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

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
	'ambiguous' => _('Historical baseline ambiguous'),
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

$readinessStatusLabels = [
	'not_applicable' => _('Not applicable'),
	'blocked_baseline' => _('Blocked — historical baseline required'),
	'blocked_unresolved' => _('Blocked — analysis unresolved'),
	'blocked_conflict' => _('Blocked — conflict detected'),
	'blocked_local_overwrite' => _('Blocked — local customization overwrite risk'),
	'review_high' => _('Manual high-risk review required'),
	'review_medium' => _('Manual review required'),
	'review_required' => _('Manual review path — rollback backup required'),
	'review_backup_verified' => _('Manual review path — rollback backup verified'),
	'candidate_for_backup' => _('Candidate for backup and continued review'),
	'backup_verified' => _('Rollback backup verified')
];

$readinessNextStepLabels = [
	'none' => _('None'),
	'resolve_update_preview' => _('Resolve update-preview identities'),
	'resolve_historical_baseline' => _('Resolve historical official baseline'),
	'resolve_three_way_analysis' => _('Resolve three-way comparison'),
	'resolve_conflicts' => _('Resolve BASE / LOCAL / UPSTREAM conflicts'),
	'protect_local_customizations' => _('Protect or reconcile local customizations'),
	'resolve_risk_analysis' => _('Resolve risk analysis'),
	'manual_high_risk_review' => _('Perform manual high-risk change review'),
	'manual_change_review' => _('Perform manual change review'),
	'create_and_verify_backup' => _('Create and verify rollback backup'),
	'run_controlled_preflight' => _('Run fresh controlled update preflight'),
	'run_manual_preflight' => _('Run explicit reviewed update preflight')
];

$backupVerificationLabels = [
	'no_backup' => _('No rollback backup found'),
	'repository_unavailable' => _('Backup repository unavailable'),
	'latest_invalid' => _('Newest rollback backup is invalid'),
	'current_mismatch' => _('Rollback backup does not match current installed export'),
	'current_match' => _('Rollback backup matches current installed export')
];

$versionTones = [
	'current' => FrontendUi::SUCCESS,
	'update_available' => FrontendUi::WARNING,
	'installed_newer' => FrontendUi::INFO,
	'installed_version_missing' => FrontendUi::DANGER,
	'upstream_version_missing' => FrontendUi::DANGER,
	'version_uncomparable' => FrontendUi::WARNING,
	'not_applicable' => FrontendUi::MUTED
];

$contentTones = [
	'matches_current_upstream' => FrontendUi::SUCCESS,
	'local_modifications_detected' => FrontendUi::WARNING,
	'update_available_no_local_modifications' => FrontendUi::INFO,
	'update_available_local_modifications' => FrontendUi::WARNING,
	'preview_against_newer_upstream' => FrontendUi::INFO,
	'historical_baseline_required' => FrontendUi::WARNING,
	'not_available' => FrontendUi::DANGER
];

$baselineTones = [
	'found' => FrontendUi::SUCCESS,
	'ambiguous' => FrontendUi::WARNING,
	'not_found' => FrontendUi::WARNING,
	'history_limit_reached' => FrontendUi::WARNING
];

$threeWayTones = [
	'conflict_detected' => FrontendUi::DANGER,
	'needs_review' => FrontendUi::WARNING,
	'local_overwrite_risk' => FrontendUi::WARNING,
	'compatible_overlap' => FrontendUi::SUCCESS,
	'upstream_only' => FrontendUi::INFO,
	'no_changes' => FrontendUi::SUCCESS
];

$riskTones = [
	'none' => FrontendUi::SUCCESS,
	'low' => FrontendUi::SUCCESS,
	'medium' => FrontendUi::WARNING,
	'high' => FrontendUi::WARNING,
	'conflict' => FrontendUi::DANGER,
	'unknown' => FrontendUi::MUTED
];

$backupTones = [
	'no_backup' => FrontendUi::WARNING,
	'repository_unavailable' => FrontendUi::DANGER,
	'latest_invalid' => FrontendUi::DANGER,
	'current_mismatch' => FrontendUi::WARNING,
	'current_match' => FrontendUi::SUCCESS
];

$readinessTones = [
	'not_applicable' => FrontendUi::MUTED,
	'blocked_baseline' => FrontendUi::DANGER,
	'blocked_unresolved' => FrontendUi::DANGER,
	'blocked_conflict' => FrontendUi::DANGER,
	'blocked_local_overwrite' => FrontendUi::WARNING,
	'review_high' => FrontendUi::WARNING,
	'review_medium' => FrontendUi::WARNING,
	'review_required' => FrontendUi::WARNING,
	'review_backup_verified' => FrontendUi::WARNING,
	'candidate_for_backup' => FrontendUi::INFO,
	'backup_verified' => FrontendUi::SUCCESS
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
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CLink(_('Back to template updates'), $backUrl))
		))->setAttribute('aria-label', _('Content controls'))
	);

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
			FrontendUi::status(
				$versionLabels[$template['version_status'] ?? 'not_applicable'] ?? _('Unknown'),
				$versionTones[$template['version_status'] ?? 'not_applicable'] ?? FrontendUi::MUTED
			),
			FrontendUi::status(
				$contentLabels[$data['content_status']] ?? _('Unknown'),
				$contentTones[$data['content_status']] ?? FrontendUi::MUTED
			),
			$data['source_path'] !== '' ? $data['source_path'] : '—',
			$sourceCommit !== '' ? substr($sourceCommit, 0, 12) : '—'
		]);

	$page->addItem(new CTag('h4', true, _('Comparison target')))->addItem($metadataTable);
}

if ($data['comparison_error'] !== null) {
	$page->addItem(new CTag('p', true, FrontendUi::status(
		$data['comparison_error'],
		FrontendUi::DANGER
	)));
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
			_('Commits examined'),
			_('Same-version commits'),
			_('Distinct official contents'),
			_('Exact LOCAL matches'),
			_('Closest LOCAL differences')
		])
		->addRow([
			FrontendUi::status(
				$baselineLabels[$baselineStatus] ?? _('Unknown'),
				$baselineTones[$baselineStatus] ?? FrontendUi::MUTED
			),
			(string) ($baseline['vendor_version'] ?? '—'),
			($baseline['commit'] ?? '') !== '' ? substr((string) $baseline['commit'], 0, 12) : '—',
			(int) ($baseline['commits_examined'] ?? 0),
			(int) ($baseline['candidate_count'] ?? 0),
			(int) ($baseline['distinct_candidate_count'] ?? 0),
			(int) ($baseline['exact_match_count'] ?? 0),
			isset($baseline['closest_changes']) ? (int) $baseline['closest_changes']
				: (isset($baseline['semantic_distance']) ? (int) $baseline['semantic_distance'] : '—')
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
	$page->addItem(new CTag('p', true, FrontendUi::status(
		$data['historical_error'],
		FrontendUi::DANGER
	)));
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
			FrontendUi::status(
				$threeWayStatusLabels[$threeWayStatus] ?? _('Unknown'),
				$threeWayTones[$threeWayStatus] ?? FrontendUi::MUTED
			),
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
			FrontendUi::status(
				$riskLevelLabels[$riskLevel] ?? _('Unknown'),
				$riskTones[$riskLevel] ?? FrontendUi::MUTED
			),
			FrontendUi::status(
				$riskLevelLabels[$technicalLevel] ?? _('Unknown'),
				$riskTones[$technicalLevel] ?? FrontendUi::MUTED
			),
			FrontendUi::status(
				$riskCoverageLabels[$coverage] ?? _('Unknown'),
				$coverage === 'complete' ? FrontendUi::SUCCESS : FrontendUi::WARNING
			),
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
			'The update preview contains at least one confirmed three-way conflict. Manual review is required before any controlled update can be considered.'
		)));
	}

	$page->addItem(new CTag('p', true, _(
		'The host impact count currently represents only hosts directly linked to this template. Inherited or indirect template impact is not yet included, and host count does not artificially change technical severity.'
	)));
}

if ($data['update_risk_error'] !== null) {
	$page->addItem(new CTag('p', true, FrontendUi::status(
		$data['update_risk_error'],
		FrontendUi::DANGER
	)));
}

if (is_array($data['backup_verification'])) {
	$backupVerification = $data['backup_verification'];
	$backupStatus = (string) ($backupVerification['status'] ?? 'repository_unavailable');
	$latestBackup = is_array($backupVerification['latest'] ?? null) ? $backupVerification['latest'] : [];
	$latestSha = (string) ($latestBackup['sha256'] ?? '');
	$latestCreatedAt = (string) ($latestBackup['created_at'] ?? '');
	$latestBytes = array_key_exists('bytes', $latestBackup) ? (int) $latestBackup['bytes'] : null;

	$backupVerificationTable = (new CTableInfo())
		->setHeader([
			_('Verification state'),
			_('Latest backup'),
			_('SHA-256'),
			_('Bytes'),
			_('Scanned'),
			_('Valid'),
			_('Invalid')
		])
		->addRow([
			FrontendUi::status(
				$backupVerificationLabels[$backupStatus] ?? _('Unknown'),
				$backupTones[$backupStatus] ?? FrontendUi::MUTED
			),
			$latestCreatedAt !== '' ? $latestCreatedAt : '—',
			$latestSha !== '' ? substr($latestSha, 0, 16) : '—',
			$latestBytes !== null ? $latestBytes : '—',
			(int) ($backupVerification['scanned'] ?? 0),
			(int) ($backupVerification['valid'] ?? 0),
			(int) ($backupVerification['invalid'] ?? 0)
		]);

	$page
		->addItem(new CTag('h4', true, _('Rollback backup verification')))
		->addItem($backupVerificationTable);

	if ($backupStatus === 'current_match') {
		$page->addItem(new CTag('p', true, _(
			'The newest rollback artifact passed manifest, path, size, permission and SHA-256 validation and matches a fresh export of the currently installed template exactly.'
		)));
	}
	elseif ($backupStatus === 'current_mismatch') {
		$page->addItem(new CTag('p', true, _(
			'The newest intact rollback artifact does not match the current installed template export. It is treated as stale and does not satisfy the rollback prerequisite.'
		)));
	}
	elseif ($backupStatus === 'latest_invalid') {
		$page->addItem(new CTag('p', true, _(
			'The newest rollback artifact failed integrity validation. The module does not silently fall back to an older backup.'
		)));
	}
}

if ($data['backup_verification_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['backup_verification_error']));
}

if (is_array($data['update_readiness'])
		&& ($data['update_readiness']['status'] ?? 'not_applicable') !== 'not_applicable') {
	$readiness = $data['update_readiness'];
	$readinessStatus = (string) ($readiness['status'] ?? 'blocked_unresolved');
	$nextStep = (string) ($readiness['next_step'] ?? 'none');

	$readinessTable = (new CTableInfo())
		->setHeader([
			_('Readiness state'),
			_('Required next step'),
			_('Directly linked hosts'),
			_('Update writes enabled')
		])
		->addRow([
			FrontendUi::status(
				$readinessStatusLabels[$readinessStatus] ?? _('Unknown'),
				$readinessTones[$readinessStatus] ?? FrontendUi::MUTED
			),
			$readinessNextStepLabels[$nextStep] ?? _('Unknown'),
			(int) ($readiness['direct_host_count'] ?? 0),
			FrontendUi::yesNo(!empty($readiness['write_enabled']))
		]);

	$page
		->addItem(new CTag('h4', true, _('Update readiness gate')))
		->addItem($readinessTable);

	switch ($readinessStatus) {
		case 'backup_verified':
			$readinessText = _(
				'The comparison evidence is complete and the newest rollback artifact exactly matches the current installed template export. The next step is a fresh controlled preflight; any configuration write still requires explicit super-administrator confirmation and another fresh preflight immediately before import.'
			);
			break;

		case 'candidate_for_backup':
			$readinessText = _(
				'The comparison gate has enough authoritative evidence to advance to creating and verifying a rollback backup. This is not an update authorization, and no configuration import is enabled at this state.'
			);
			break;

		case 'review_required':
			$readinessText = _(
				'The comparison is authoritative and has no unresolved identities or three-way conflicts, but one or more known local-overwrite and/or medium/high technical-risk conditions require explicit manual review. A rollback backup may be created next; no configuration write is authorized yet.'
			);
			break;

		case 'review_backup_verified':
			$readinessText = _(
				'The reviewed manual-update path has an exact rollback backup matching the current installed template. The next step is a fresh reviewed preflight; the final import still requires a second explicit super-administrator acknowledgement of the reported overwrite/risk conditions.'
			);
			break;

		case 'review_high':
			$readinessText = _(
				'The comparison evidence is complete, but the proposed upstream change has high technical review priority.'
			);
			break;

		case 'review_medium':
			$readinessText = _(
				'The comparison evidence is complete, but the proposed upstream change requires manual review.'
			);
			break;

		case 'blocked_conflict':
			$readinessText = _(
				'The default update path is blocked because at least one BASE / LOCAL / UPSTREAM conflict is confirmed. The conflict must be reviewed and resolved deliberately.'
			);
			break;

		case 'blocked_local_overwrite':
			$readinessText = _(
				'The default update path is blocked because current official content would overwrite one or more known local customizations. Those customizations must be preserved, reconciled or explicitly retired first.'
			);
			break;

		case 'blocked_baseline':
			$readinessText = _(
				'The update path is blocked because an authoritative historical official baseline matching the installed vendor version has not been established.'
			);
			break;

		default:
			$readinessText = _(
				'The update path is blocked because one or more comparison identities or analysis stages remain unresolved. The module fails closed rather than inferring update readiness.'
			);
	}

	$page
		->addItem(new CTag('p', true, $readinessText))
		->addItem(new CTag('p', true, _(
			'This comparison page does not call configuration.import. Controlled writes are available only through the separate preflight and confirmation flow after all safety gates pass.'
		)));

	if (!empty($readiness['candidate_for_backup']) && is_array($data['template'])) {
		$backupAction = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.backup')
			->getUrl();
		$backupForm = (new CForm('post'))
			->setId('ztum-template-backup-form')
			->setAction($backupAction)
			->addItem([
				(new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.template.backup')))->removeId(),
				(new CVar('templateid', (string) $data['template']['templateid']))->removeId(),
				new CSubmitButton(_('Create rollback backup'))
			]);

		$page
			->addItem(new CTag('h4', true, _('Rollback backup')))
			->addItem(new CTag('p', true, _(
				'This action exports the currently installed template and stores a private persistent rollback artifact. It does not change the template.'
			)))
			->addItem($backupForm);
	}
	elseif (!empty($readiness['backup_verified']) && is_array($data['template'])) {
		$preflightAction = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.preflight')
			->getUrl();
		$preflightForm = (new CForm('post'))
			->setId('ztum-template-preflight-form')
			->setAction($preflightAction)
			->addItem(array_values(array_filter([
				(new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.template.preflight')))->removeId(),
				(new CVar('templateid', (string) $data['template']['templateid']))->removeId(),
				!empty($readiness['manual_confirmation_required'])
					? (new CVar('manual_override', '1'))->removeId()
					: null,
				new CSubmitButton(
					!empty($readiness['manual_confirmation_required'])
						? _('Run reviewed controlled preflight')
						: _('Run controlled preflight')
				)
			], static fn($item): bool => $item !== null)));

		$page
			->addItem(new CTag('h4', true, _('Controlled update preflight')))
			->addItem(new CTag('p', true, _(
				'The preflight recomputes the complete authoritative state and rollback match before showing any super-administrator update confirmation.'
			)))
			->addItem($preflightForm);
	}
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
			'The installed template is older than upstream, and a historical official baseline matching the installed vendor version was found. The installed content matches that baseline, so no local modifications were detected. The review-priority section classifies the proposed upstream changes technically; only low/none-risk candidates can advance through the automatic backup/preflight gate.'
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
		'This comparison page uses configuration.importcompare and configuration.export only. A controlled configuration.import is performed only by the separate super-administrator update action after fresh preflight, explicit confirmation and immutable source verification.'
	)))
	->show();