<?php
use Modules\ZabbixTemplateUpdateManager\Service\HistoricalCandidateProvenanceAudit as Audit;
require_once dirname(__DIR__, 2).'/src/Service/HistoricalCandidateProvenanceAudit.php';
function assertAudit(mixed $expected, mixed $actual, string $label): void {
    if ($expected !== $actual) {
        throw new RuntimeException($label);
    }
}
$sourceA = '{"id":"first"}';
$sourceB = '{"id":"second"}';
$candidates = [
    ['source' => $sourceA, 'source_sha256' => hash('sha256', $sourceA),
        'semantic_distance' => 2, 'dashboard_correlation' => ['semantic_reconciliation' => ['status' => 'representation_only_candidate']]],
    ['source' => $sourceB, 'source_sha256' => hash('sha256', $sourceB),
        'semantic_distance' => 5, 'dashboard_correlation' => ['semantic_reconciliation' => ['status' => 'unverified']]]
];
$report = Audit::evaluate($candidates, false);
assertAudit('single_representational_candidate_not_proven', $report['status'], 'Single representational result is not installation provenance');
assertAudit(2, $report['integrity_verified'], 'Both digests checked');
assertAudit(1, $report['representation_candidates'], 'One candidate correlated');
assertAudit('inconclusive', Audit::evaluate($candidates, true)['status'], 'Truncated history never proves uniqueness');
$candidates[1]['source_sha256'] = str_repeat('0', 64);
assertAudit('integrity_failed', Audit::evaluate($candidates, false)['status'], 'Invalid hash must fail closed');
assertAudit(false, isset($report['source']), 'No source data disclosed');
echo "HistoricalCandidateProvenanceAudit tests passed.\n";
