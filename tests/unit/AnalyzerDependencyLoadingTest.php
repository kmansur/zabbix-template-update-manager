<?php

use Modules\ZabbixTemplateUpdateManager\Service\ThreeWayChangeAnalyzer;
use Modules\ZabbixTemplateUpdateManager\Service\UpdatePreviewAnalyzer;

// These analyzers must be self-contained when loaded by a frontend service.
// Do not preload ImportCompareEntityExtractor here: this test exists to catch
// production-only Class not found failures hidden by unit-test setup order.
require_once dirname(__DIR__, 2).'/src/Service/UpdatePreviewAnalyzer.php';
require_once dirname(__DIR__, 2).'/src/Service/ThreeWayChangeAnalyzer.php';

if (!class_exists('Modules\\ZabbixTemplateUpdateManager\\Service\\ImportCompareEntityExtractor', false)) {
	fwrite(STDERR, "Analyzer dependency loading failed: ImportCompareEntityExtractor was not loaded.\n");
	exit(1);
}

$result = UpdatePreviewAnalyzer::analyze([
	'items' => [
		'updated' => [[
			'before' => ['uuid' => str_repeat('a', 32), 'name' => 'X', 'key' => 'x', 'delay' => '1m'],
			'after' => ['uuid' => str_repeat('a', 32), 'name' => 'X', 'key' => 'x', 'delay' => '30s']
		]]
	]
]);

if (($result['summary']['updated_fields'] ?? null) !== 1) {
	fwrite(STDERR, "Analyzer dependency loading failed: UpdatePreviewAnalyzer did not execute correctly.\n");
	exit(1);
}

$threeWay = ThreeWayChangeAnalyzer::analyze([], [
	'items' => [
		'updated' => [[
			'before' => ['uuid' => str_repeat('b', 32), 'name' => 'Y', 'key' => 'y', 'delay' => '1m'],
			'after' => ['uuid' => str_repeat('b', 32), 'name' => 'Y', 'key' => 'y', 'delay' => '30s']
		]]
	]
]);

if (($threeWay['summary']['upstream_only'] ?? null) !== 1) {
	fwrite(STDERR, "Analyzer dependency loading failed: ThreeWayChangeAnalyzer did not execute correctly.\n");
	exit(1);
}

echo "Analyzer dependency loading tests passed.\n";
