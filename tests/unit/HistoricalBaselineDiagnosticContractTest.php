<?php

$root = dirname(__DIR__, 2);
$analysis = (string) file_get_contents($root.'/src/Service/TemplateUpdateAnalysisService.php');
$history = (string) file_get_contents($root.'/src/Service/HistoricalTemplateBaselineService.php');

function assertHistoryDiagnostic(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach ([
	'Historical baseline unresolved for template',
	'commits_examined',
	'distinct_candidate_count',
	'history_truncated',
	'cacheStatus'
] as $evidence) {
	assertHistoryDiagnostic(strpos($analysis, $evidence) !== false,
		'Missing read-only historical diagnostic: '.$evidence);
}

assertHistoryDiagnostic(strpos($analysis, "($baseline['status'] ?? null) !== 'found'") !== false,
	'Unresolved historical states must receive diagnostics.');
assertHistoryDiagnostic(strpos($analysis, "($baseline['status'] ?? null) === 'found'") !== false,
	'Only found historical baselines may be analyzed as valid comparisons.');
assertHistoryDiagnostic(strpos($history, "'status' => 'ambiguous'") !== false
		&& strpos($history, "'status' => 'time_budget_reached'") !== false,
	'Insufficient baseline evidence must remain distinguishable and fail-closed.');

echo "Historical baseline diagnostics contract tests passed.\n";
