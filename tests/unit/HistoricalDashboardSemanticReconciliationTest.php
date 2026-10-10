<?php
use Modules\ZabbixTemplateUpdateManager\Service\HistoricalDashboardSemanticReconciliation as Recon;
require_once dirname(__DIR__, 2).'/src/Service/HistoricalDashboardSemanticReconciliation.php';
function verifyRecon(mixed $expected, mixed $actual, string $name): void {
    if ($expected !== $actual) {
        throw new RuntimeException($name);
    }
}
$detail = ['entity_type' => 'dashboards', 'field' => 'auto_start',
    'change_type' => 'updated', 'before' => ['__state' => 'missing'], 'after' => 'NO'];
$preview = ['details' => [$detail], 'details_truncated' => false];
$correlation = ['complete' => true, 'residual' => ['status' => 'dashboard_snapshot_omission_correlated']];
$good = Recon::assess(['total' => 2], $preview, $correlation);
verifyRecon('representation_only_candidate', $good['status'], 'Known representation residual');
verifyRecon(0, $good['remaining_operations'], 'No remaining direct operations in the preview');
verifyRecon('unverified', Recon::assess(['total' => 3], $preview, $correlation)['status'], 'Other native changes fail');
verifyRecon('unverified', Recon::assess(['total' => 2], ['details' => [$detail, $detail]], $correlation)['status'], 'Extra details fail');
verifyRecon('unverified', Recon::assess(['total' => 2], ['details' => [$detail], 'details_truncated' => true], $correlation)['status'], 'Truncation fails');
verifyRecon('unverified', Recon::assess(['total' => 2], $preview, ['complete' => false])['status'], 'Missing verification fails');
verifyRecon('unverified', Recon::assess(['total' => 2], $preview, ['complete' => true])['status'], 'Residual proof mandatory');
verifyRecon(false, isset($good['source']), 'No source returned');
verifyRecon(false, isset($good['baseline']), 'No baseline returned');
echo "HistoricalDashboardSemanticReconciliation tests passed.\n";
