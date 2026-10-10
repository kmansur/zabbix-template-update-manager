<?php

use Modules\ZabbixTemplateUpdateManager\Service\HistoricalDashboardNormalizationDiagnostic as Diagnostic;

require_once dirname(__DIR__, 2).'/src/Service/HistoricalDashboardNormalizationDiagnostic.php';

function assertDashboardDiagnostic($expected, $actual, string $label): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $label.': expected '.var_export($expected, true).', got '.var_export($actual, true)."\n");
		exit(1);
	}
}

assertDashboardDiagnostic('equivalent', Diagnostic::compare('NO', 0), 'NO vs 0');
assertDashboardDiagnostic('equivalent', Diagnostic::compare(0, '0'), '0 vs string zero');
assertDashboardDiagnostic('equivalent', Diagnostic::compare('YES', 1), 'YES vs 1');
assertDashboardDiagnostic('different', Diagnostic::compare('NO', 1), 'NO vs 1');
assertDashboardDiagnostic('unknown', Diagnostic::compare('no', 0), 'Unverified value must not normalize');
assertDashboardDiagnostic('unknown', Diagnostic::compare(null, 0), 'Missing value must not normalize');
assertDashboardDiagnostic('unknown', Diagnostic::compare(['secret' => 'value'], 0), 'Array must not normalize');

$summary = Diagnostic::summarizePreview([
	'details' => [
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated', 'before' => 'NO', 'after' => 0],
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated', 'before' => 'YES', 'after' => 0],
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated', 'before' => 'UNKNOWN', 'after' => 0],
		['entity_type' => 'templates', 'field' => 'auto_start', 'change_type' => 'updated', 'before' => 'NO', 'after' => 0],
		['entity_type' => 'dashboards', 'field' => 'password', 'change_type' => 'updated', 'before' => 'secret', 'after' => 'other']
	],
	'details_truncated' => true
]);
assertDashboardDiagnostic(1, $summary['equivalent'], 'Equivalent dashboard only');
assertDashboardDiagnostic(1, $summary['different'], 'Real dashboard difference');
assertDashboardDiagnostic(1, $summary['unknown'], 'Unknown dashboard representation');
assertDashboardDiagnostic(1, $summary['truncated'], 'Truncated evidence');
assertDashboardDiagnostic(false, isset($summary['details']), 'No configuration values in report');

$shapes = Diagnostic::describeUnknownPreview([
	'details' => [
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated', 'before' => ['secret' => 'value'], 'after' => 'NO'],
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated', 'before' => 'UNRECOGNIZED_SECRET', 'after' => 0]
	]
]);
assertDashboardDiagnostic(2, count($shapes), 'Only unknown dashboard values should be diagnosed');
assertDashboardDiagnostic('array', $shapes[0]['before_type'], 'Array representation type');
assertDashboardDiagnostic('string', $shapes[0]['after_type'], 'String representation type');
assertDashboardDiagnostic(false, $shapes[0]['before_known'], 'Unknown array must remain unknown');
assertDashboardDiagnostic(true, $shapes[0]['after_known'], 'NO should be recognized');
assertDashboardDiagnostic(false, $shapes[1]['before_known'], 'Unknown string must remain unknown');
assertDashboardDiagnostic(false, isset($shapes[0]['before']), 'Raw values must not appear in shape diagnostic');
assertDashboardDiagnostic(false, str_contains(json_encode($shapes), 'UNRECOGNIZED_SECRET'), 'No raw secret in diagnostic');

$missingShapes = Diagnostic::describeUnknownPreview([
	'details' => [
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated',
			'before' => ['__state' => 'missing'], 'after' => 'NO'],
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated',
			'before' => ['other' => 'secret'], 'after' => 'NO'],
		['entity_type' => 'dashboards', 'field' => 'auto_start', 'change_type' => 'updated',
			'before' => ['secret'], 'after' => 'NO']
	]
]);
assertDashboardDiagnostic('missing_marker', $missingShapes[0]['before_shape'], 'Sentinel must be detected exactly');
assertDashboardDiagnostic('associative', $missingShapes[1]['before_shape'], 'Unknown associative arrays stay unknown');
assertDashboardDiagnostic('list', $missingShapes[2]['before_shape'], 'Lists stay unknown');
assertDashboardDiagnostic(1, $missingShapes[0]['before_count'], 'Safe bounded element count');
assertDashboardDiagnostic(false, str_contains(json_encode($missingShapes), 'secret'), 'Unknown values must not leak');
assertDashboardDiagnostic('unknown', Diagnostic::compare(['__state' => 'missing'], 'NO'), 'Missing cannot be treated as equivalent to NO');

$baselineService = (string) file_get_contents(dirname(__DIR__, 2).'/src/Service/HistoricalTemplateBaselineService.php');
assertDashboardDiagnostic(true, str_contains($baselineService, "'status' => 'ambiguous'"),
	'Ambiguous historical candidates must continue to fail closed');
echo "HistoricalDashboardNormalizationDiagnostic tests passed.\n";
