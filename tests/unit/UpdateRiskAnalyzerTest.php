<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpdateRiskAnalyzer;

require_once dirname(__DIR__, 2).'/src/Service/UpdateRiskAnalyzer.php';

function assertUpdateRisk($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function previewDetail(
	string $path,
	string $entityType,
	string $field,
	string $changeType,
	$before = 'before',
	$after = 'after'
): array {
	return [
		'path' => $path,
		'entity_type' => $entityType,
		'entity' => 'Entity',
		'field' => $field,
		'change_type' => $changeType,
		'before' => $before,
		'after' => $after
	];
}

$threeWay = ['summary' => ['conflict' => 0, 'unresolved' => 0, 'local_only_overwrite' => 0], 'details' => []];

$preview = ['details' => [previewDetail('/i', 'items', 'key', 'updated')]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 42);
assertUpdateRisk('high', $risk['technical_level'], 'Item key changes must be technically high risk.');
assertUpdateRisk('high', $risk['level'], 'Complete three-way coverage should retain high technical risk.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Item key changes must never enter the standard path.');
assertUpdateRisk(42, $risk['direct_host_count'], 'Direct host impact must be preserved exactly.');

$preview = ['details' => [previewDetail('/t', 'templates', 'description', 'updated')]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 0);
assertUpdateRisk('low', $risk['level'], 'Description-only change may be low review priority.');
assertUpdateRisk(true, $risk['standard_path_eligible'], 'Low metadata-only changes may use the standard path.');

$preview = ['details' => [previewDetail('/tr', 'triggers', '[entity]', 'added')]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 1);
assertUpdateRisk('medium', $risk['level'], 'Adding a trigger should require medium review priority.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Adding a trigger must remain manual despite medium severity.');

// Real beta.17 field regression: APC UPS Symmetra RM by SNMP 7.0-3 -> 7.0-4.
// The official upstream change removed only DISCARD_UNCHANGED_HEARTBEAT 6h
// from one status item (plus vendor version metadata). This remains visible as
// medium technical impact but is a narrowly known standard-path candidate when
// three-way evidence proves it is upstream-only.
$apcPath = '/templates:uuid-a/items:uuid-b';
$preview = ['details' => [
	previewDetail(
		$apcPath,
		'items',
		'preprocessing',
		'updated',
		[[
			'type' => 'DISCARD_UNCHANGED_HEARTBEAT',
			'parameters' => ['6h']
		]],
		['__state' => 'missing']
	),
	previewDetail('/templates:uuid-a', 'templates', 'vendor', 'updated', ['version' => '7.0-3'], ['version' => '7.0-4'])
]];
$apcThreeWay = [
	'summary' => ['conflict' => 0, 'unresolved' => 0, 'local_only_overwrite' => 0],
	'details' => [[
		'path' => $apcPath,
		'field' => 'preprocessing',
		'classification' => 'upstream_only'
	]]
];
$risk = UpdateRiskAnalyzer::assess($preview, $apcThreeWay, 0);
assertUpdateRisk('medium', $risk['technical_level'], 'Discard-only preprocessing maintenance must remain visible as medium impact.');
assertUpdateRisk('medium', $risk['level'], 'Known bounded preprocessing maintenance should remain medium overall.');
assertUpdateRisk(true, $risk['standard_path_eligible'], 'Known discard-only preprocessing maintenance may use the standard controlled path.');
assertUpdateRisk(
	'bounded_preprocessing_discard_change',
	$risk['details'][0]['risk_reason'],
	'Bounded preprocessing maintenance must expose an explicit reason.'
);

// Unchanged functional preprocessing may coexist with the changed discard step.
// Only the symmetric difference is evaluated, so an unchanged JavaScript step
// does not falsely turn a discard-only maintenance change into high risk.
$preview = ['details' => [previewDetail(
	'/i2',
	'items',
	'preprocessing',
	'updated',
	[
		['type' => 'JAVASCRIPT', 'parameters' => ['return value;']],
		['type' => 'DISCARD_UNCHANGED_HEARTBEAT', 'parameters' => ['6h']]
	],
	[
		['type' => 'JAVASCRIPT', 'parameters' => ['return value;']]
	]
)]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 0);
assertUpdateRisk('medium', $risk['level'], 'Unchanged functional preprocessing must not taint a discard-only delta.');
assertUpdateRisk(true, $risk['standard_path_eligible'], 'Only the changed preprocessing steps should decide bounded eligibility.');

// Any changed JavaScript/regex/transformation step remains high and manual.
$preview = ['details' => [previewDetail(
	'/i3',
	'items',
	'preprocessing',
	'updated',
	[['type' => 'JAVASCRIPT', 'parameters' => ['return value;']]],
	[['type' => 'JAVASCRIPT', 'parameters' => ['return JSON.parse(value);']]]
)]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 0);
assertUpdateRisk('high', $risk['level'], 'Functional preprocessing changes must remain high risk.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Functional preprocessing changes must never enter the standard path.');
assertUpdateRisk('functional_preprocessing_change', $risk['details'][0]['risk_reason'], 'Unsafe preprocessing changes must explain why they are high.');

$preview = ['details' => [previewDetail('/x', 'items', 'delay', 'updated')]];
$threeWayConflict = [
	'summary' => ['conflict' => 1, 'unresolved' => 0, 'local_only_overwrite' => 0],
	'details' => [[
		'path' => '/x',
		'field' => 'delay',
		'classification' => 'conflict'
	]]
];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWayConflict, 1);
assertUpdateRisk('conflict', $risk['level'], 'Known three-way conflict must dominate risk.');
assertUpdateRisk('conflict', $risk['details'][0]['risk_level'], 'Matching preview detail must inherit conflict severity.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Conflict must never be standard-path eligible.');

$threeWayOverwrite = [
	'summary' => ['conflict' => 0, 'unresolved' => 0, 'local_only_overwrite' => 1],
	'details' => [[
		'path' => '/x',
		'field' => 'delay',
		'classification' => 'local_only_overwrite'
	]]
];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWayOverwrite, 1);
assertUpdateRisk('high', $risk['level'], 'Local overwrite risk must require high review priority.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Local overwrite must never enter the standard path.');

$risk = UpdateRiskAnalyzer::assess($preview, null, 1);
assertUpdateRisk('unknown', $risk['level'], 'Missing three-way coverage must prevent a definitive overall risk.');
assertUpdateRisk('medium', $risk['technical_level'], 'Technical severity can still be reported when overall coverage is incomplete.');
assertUpdateRisk('incomplete', $risk['coverage'], 'Missing three-way analysis must be explicit.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Missing three-way evidence must fail closed.');

$threeWayUnresolved = ['summary' => ['conflict' => 0, 'unresolved' => 1, 'local_only_overwrite' => 0], 'details' => []];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWayUnresolved, 1);
assertUpdateRisk('unknown', $risk['level'], 'Unresolved three-way identity/pivot must make overall risk unknown.');
assertUpdateRisk(false, $risk['standard_path_eligible'], 'Unresolved three-way evidence must fail closed.');

$emptyRisk = UpdateRiskAnalyzer::assess(['details' => []], $threeWay, 0);
assertUpdateRisk('none', $emptyRisk['level'], 'No effective update changes should produce no technical risk.');
assertUpdateRisk(true, $emptyRisk['standard_path_eligible'], 'No effective change can remain on the standard path.');

echo "UpdateRiskAnalyzer tests passed.\n";
