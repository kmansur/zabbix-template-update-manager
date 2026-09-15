<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpdateRiskAnalyzer;

require_once dirname(__DIR__, 2).'/src/Service/UpdateRiskAnalyzer.php';

function assertUpdateRisk($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function previewDetail(string $path, string $entityType, string $field, string $changeType): array {
	return [
		'path' => $path,
		'entity_type' => $entityType,
		'entity' => 'Entity',
		'field' => $field,
		'change_type' => $changeType,
		'before' => 'before',
		'after' => 'after'
	];
}

$preview = ['details' => [previewDetail('/i', 'items', 'key', 'updated')]];
$threeWay = ['summary' => ['conflict' => 0, 'unresolved' => 0, 'local_only_overwrite' => 0], 'details' => []];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 42);
assertUpdateRisk('high', $risk['technical_level'], 'Item key changes must be technically high risk.');
assertUpdateRisk('high', $risk['level'], 'Complete three-way coverage should retain high technical risk.');
assertUpdateRisk(42, $risk['direct_host_count'], 'Direct host impact must be preserved exactly.');

$preview = ['details' => [previewDetail('/t', 'templates', 'description', 'updated')]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 0);
assertUpdateRisk('low', $risk['level'], 'Description-only change may be low review priority.');

$preview = ['details' => [previewDetail('/tr', 'triggers', '[entity]', 'added')]];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWay, 1);
assertUpdateRisk('medium', $risk['level'], 'Adding a trigger should require medium review priority.');

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

$risk = UpdateRiskAnalyzer::assess($preview, null, 1);
assertUpdateRisk('unknown', $risk['level'], 'Missing three-way coverage must prevent a definitive overall risk.');
assertUpdateRisk('medium', $risk['technical_level'], 'Technical severity can still be reported when overall coverage is incomplete.');
assertUpdateRisk('incomplete', $risk['coverage'], 'Missing three-way analysis must be explicit.');

$threeWayUnresolved = ['summary' => ['conflict' => 0, 'unresolved' => 1, 'local_only_overwrite' => 0], 'details' => []];
$risk = UpdateRiskAnalyzer::assess($preview, $threeWayUnresolved, 1);
assertUpdateRisk('unknown', $risk['level'], 'Unresolved three-way identity/pivot must make overall risk unknown.');

$emptyRisk = UpdateRiskAnalyzer::assess(['details' => []], $threeWay, 0);
assertUpdateRisk('none', $emptyRisk['level'], 'No effective update changes should produce no technical risk.');

echo "UpdateRiskAnalyzer tests passed.\n";
