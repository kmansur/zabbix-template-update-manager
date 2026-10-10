<?php
use Modules\ZabbixTemplateUpdateManager\Service\HistoricalDashboardResidualDiagnostic as Diagnostic;
require_once dirname(__DIR__, 2).'/src/Service/HistoricalDashboardResidualDiagnostic.php';
function checkResidual(mixed $expected, mixed $actual, string $label): void {
    if ($expected !== $actual) {
        throw new RuntimeException($label);
    }
}
$detail = ['entity_type' => 'dashboards', 'field' => 'auto_start',
    'change_type' => 'updated', 'before' => ['__state' => 'missing'], 'after' => 'NO'];
$preview = ['details' => [$detail], 'details_truncated' => false];
$good = Diagnostic::assess($preview, ['complete' => true]);
checkResidual('dashboard_snapshot_omission_correlated', $good['status'], 'Known correlated omission');
checkResidual('unverified', Diagnostic::assess($preview, ['complete' => false])['status'], 'Correlation is required');
checkResidual('unverified', Diagnostic::assess(['details' => [$detail], 'details_truncated' => true], ['complete' => true])['status'], 'Truncated fails closed');
$changed = $detail;
$changed['after'] = 'YES';
checkResidual('unverified', Diagnostic::assess(['details' => [$changed]], ['complete' => true])['status'], 'YES must not match NO');
$withOther = ['details' => [$detail, ['entity_type' => 'items', 'field' => 'name', 'change_type' => 'updated']]];
checkResidual('unverified', Diagnostic::assess($withOther, ['complete' => true])['status'], 'Other difference fails closed');
checkResidual(false, isset($good['details']), 'No raw fields or values exposed');
echo "HistoricalDashboardResidualDiagnostic tests passed.\n";
