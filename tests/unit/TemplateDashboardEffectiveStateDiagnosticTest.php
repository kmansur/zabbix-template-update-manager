<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateDashboardEffectiveStateDiagnostic as Diagnostic;

require_once dirname(__DIR__, 2).'/src/Service/TemplateDashboardEffectiveStateDiagnostic.php';

function assertEffectiveState(mixed $expected, mixed $actual, string $description): void {
	if ($expected !== $actual) {
		throw new RuntimeException($description.': expected '.var_export($expected, true)
			.', got '.var_export($actual, true));
	}
}
$summary = Diagnostic::inspect('10773', static fn(string $id): array => [
	['dashboardid' => '402', 'templateid' => $id, 'uuid' => str_repeat('a', 32),
		'name' => 'SECRET-dashboard', 'auto_start' => '0', 'display_period' => '3600']
]);
assertEffectiveState(1, $summary['count'], 'Dashboard count');
assertEffectiveState(1, $summary['auto_start_no'], 'Effective 0');
assertEffectiveState(0, $summary['auto_start_yes'], 'Effective 1');
assertEffectiveState(1, $summary['display_period_known'], 'Known display period');
assertEffectiveState(true, $summary['identity_complete'], 'Valid dashboard UUID');
assertEffectiveState(false, str_contains(json_encode($summary), 'SECRET'), 'No dashboard details in diagnostic');
assertEffectiveState(1, Diagnostic::inspect('10773', static fn(string $id): array => [
	['templateid' => $id, 'uuid' => str_repeat('b', 32), 'auto_start' => '1']
])['auto_start_yes'], 'Effective 1');
try {
	Diagnostic::inspect('10773', static fn(string $id): array => [
		['templateid' => '999', 'auto_start' => '0']
	]);
	throw new RuntimeException('Invalid ownership must be rejected.');
}
catch (RuntimeException $e) {
	if ($e->getMessage() === 'Invalid ownership must be rejected.') {
		throw $e;
	}
}
echo "TemplateDashboardEffectiveStateDiagnostic tests passed.\n";
