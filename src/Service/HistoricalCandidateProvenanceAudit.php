<?php
namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Provenance audit only. Integrity here means the isolated source hash was
 * rechecked in memory; it does not establish original installation provenance.
 */
final class HistoricalCandidateProvenanceAudit {
    public static function evaluate(array $candidates, bool $historyTruncated): array {
        $report = ['status' => 'inconclusive', 'candidate_count' => count($candidates),
            'integrity_verified' => 0, 'representation_candidates' => 0,
            'exact_native_candidates' => 0, 'history_truncated' => $historyTruncated];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate) || !is_string($candidate['source'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/', (string) ($candidate['source_sha256'] ?? ''))
                || !hash_equals($candidate['source_sha256'], hash('sha256', $candidate['source']))) {
                $report['status'] = 'integrity_failed';
                return $report;
            }
            $report['integrity_verified']++;
            if (($candidate['semantic_distance'] ?? null) === 0) {
                $report['exact_native_candidates']++;
            }
            if (($candidate['dashboard_correlation']['semantic_reconciliation']['status'] ?? '') === 'representation_only_candidate') {
                $report['representation_candidates']++;
            }
        }
        if (!$historyTruncated && $report['candidate_count'] > 1
            && $report['representation_candidates'] === 1
            && $report['exact_native_candidates'] === 0) {
            $report['status'] = 'single_representational_candidate_not_proven';
        }
        return $report;
    }
}
