<?php

use Modules\ZabbixTemplateUpdateManager\Service\ThreeWayChangeAnalyzer;

require_once dirname(__DIR__, 2).'/src/Service/ThreeWayChangeAnalyzer.php';

function assertThreeWay($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function templateDiff(string $baseValue, string $localValue, ?string $upstreamValue = null): array {
	$uuid = str_repeat('a', 32);
	$before = ['uuid' => $uuid, 'template' => 'T', 'name' => 'T', 'description' => $localValue];
	$after = ['uuid' => $uuid, 'template' => 'T', 'name' => 'T', 'description' => $upstreamValue ?? $baseValue];
	return ['templates' => ['updated' => [['before' => $before, 'after' => $after]]]];
}

// A=base, B=local, C=upstream all differ: real overlap conflict.
$result = ThreeWayChangeAnalyzer::analyze(
	templateDiff('base', 'local'),
	templateDiff('ignored', 'local', 'upstream')
);
assertThreeWay(1, $result['summary']['conflict'], 'Different local and upstream edits of the same field must conflict.');
assertThreeWay('conflict_detected', $result['status'], 'Conflict must dominate overall status.');

// A=C, B differs: local-only customization would be overwritten by importing current upstream.
$result = ThreeWayChangeAnalyzer::analyze(
	templateDiff('base', 'local'),
	templateDiff('ignored', 'local', 'base')
);
assertThreeWay(1, $result['summary']['local_only_overwrite'], 'Local-only change must be marked as overwrite risk.');
assertThreeWay('local_overwrite_risk', $result['status'], 'Local overwrite must require review.');

// A=B, C differs: pure upstream change.
$result = ThreeWayChangeAnalyzer::analyze(
	[],
	templateDiff('ignored', 'base', 'upstream')
);
assertThreeWay(1, $result['summary']['upstream_only'], 'A pure upstream field change must be classified upstream-only.');
assertThreeWay('upstream_only', $result['status'], 'Pure upstream changes must not be labeled as local overlap.');

// A differs, B=C: local customization independently converged with upstream.
$result = ThreeWayChangeAnalyzer::analyze(
	templateDiff('base', 'final'),
	[]
);
assertThreeWay(1, $result['summary']['converged'], 'Matching local/upstream final value must be classified as converged.');
assertThreeWay('compatible_overlap', $result['status'], 'Converged changes must not be called a conflict.');

// Entity existence: A exists, B missing, C exists unchanged => local removal would be restored/overwritten.
$uuid = str_repeat('b', 32);
$historical = ['items' => ['added' => [[
	'after' => ['uuid' => $uuid, 'name' => 'X', 'key' => 'x']
]]]];
$current = ['items' => ['added' => [[
	'after' => ['uuid' => $uuid, 'name' => 'X', 'key' => 'x']
]]]];
$result = ThreeWayChangeAnalyzer::analyze($historical, $current);
assertThreeWay(1, $result['summary']['local_only_overwrite'], 'A locally removed unchanged upstream entity must be an overwrite/restore risk.');

// Local state must be identical in both native previews; otherwise analysis fails closed.
$historical = templateDiff('base', 'local-one');
$current = templateDiff('ignored', 'local-two', 'upstream');
$result = ThreeWayChangeAnalyzer::analyze($historical, $current);
assertThreeWay(1, $result['summary']['unresolved'], 'Different LOCAL pivots must be unresolved rather than guessed.');
assertThreeWay('needs_review', $result['status'], 'Unresolved state must require review.');

echo "ThreeWayChangeAnalyzer tests passed.\n";
