<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Diagnostic-only reconciliation. A reduced preview is never an authorization
 * to select a historical source or to perform an import.
 */
final class HistoricalDashboardSemanticReconciliation {
    public static function assess(array $nativeSummary, array $preview, array $correlation): array {
        $result = [
            'status' => 'unverified',
            'native_changes' => null,
            'preview_operations' => 0,
            'reconciled_operations' => 0,
            'remaining_operations' => null,
            'truncated' => !empty($preview['details_truncated'])
        ];
        $total = $nativeSummary['total'] ?? null;
        if (!is_int($total) || $total < 0 || !is_array($preview['details'] ?? null)) {
            return $result;
        }
        $result['native_changes'] = $total;
        $result['preview_operations'] = count($preview['details']);
        if ($result['truncated'] || ($correlation['complete'] ?? false) !== true
            || ($correlation['residual']['status'] ?? '') !== 'dashboard_snapshot_omission_correlated') {
            return $result;
        }
        if ($result['preview_operations'] !== 1 || $total !== 2) {
            return $result;
        }
        $detail = $preview['details'][0];
        if (!is_array($detail)
            || ($detail['entity_type'] ?? '') !== 'dashboards'
            || ($detail['field'] ?? '') !== 'auto_start'
            || ($detail['change_type'] ?? '') !== 'updated'
            || ($detail['before'] ?? null) !== ['__state' => 'missing']
            || !in_array($detail['after'] ?? null, ['NO', '0', 0], true)) {
            return $result;
        }
        // Two native changes include the template structural wrapper.
        // This is evidence about representation only, not historical provenance.
        $result['reconciled_operations'] = 1;
        $result['remaining_operations'] = 0;
        $result['status'] = 'representation_only_candidate';
        return $result;
    }
}
