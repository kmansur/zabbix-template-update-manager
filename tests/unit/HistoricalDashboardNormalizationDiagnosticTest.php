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

$baselineService = (string) file_get_contents(dirname(__DIR__, 2).'/src/Service/HistoricalTemplateBaselineService.php');
assertDashboardDiagnostic(true, str_contains($baselineService, "'status' => 'ambiguous'"),
	'Ambiguous historical candidates must continue to fail closed');
echo "HistoricalDashboardNormalizationDiagnostic tests passed.\n";
