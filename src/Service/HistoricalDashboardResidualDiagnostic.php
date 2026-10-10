<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Evidence-only reconciliation of a native importcompare snapshot omission.
 * Never modifies an importcompare result, baseline selection or readiness.
 */
final class HistoricalDashboardResidualDiagnostic {
    public static function assess(array $preview, array $correlation): array {
        $result = ['status' => 'unverified', 'direct_changes' => 0,
            'target_missing_to_no' => 0, 'other_changes' => 0,
            'details_truncated' => !empty($preview['details_truncated'])];
        if ($result['details_truncated'] || empty($correlation['complete'])) {
            return $result;
        }
        foreach (($preview['details'] ?? []) as $detail) {
            if (!is_array($detail)) {
                $result['other_changes']++;
                continue;
            }
            $result['direct_changes']++;
            if (($detail['entity_type'] ?? null) === 'dashboards'
                && ($detail['field'] ?? null) === 'auto_start'
                && ($detail['change_type'] ?? null) === 'updated'
                && ($detail['before'] ?? null) === ['__state' => 'missing']
                && in_array($detail['after'] ?? null, ['NO', '0', 0], true)) {
                $result['target_missing_to_no']++;
                continue;
            }
            $result['other_changes']++;
        }
        if ($result['target_missing_to_no'] === 1 && $result['other_changes'] === 0
            && $result['direct_changes'] === 1) {
            $result['status'] = 'dashboard_snapshot_omission_correlated';
        }
        return $result;
    }
}
