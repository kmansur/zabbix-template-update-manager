<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Read-only eligibility evidence for future no-baseline reviewed updates.
 * This does not grant readiness, backup candidacy or import permission.
 */
final class BaselineFreeReviewAssessment {
    public static function evaluate(?array $baseline, ?array $preview): array {
        $result = [
            'status' => 'unverified',
            'baseline_verified' => is_array($baseline) && ($baseline['status'] ?? null) === 'found',
            'preview_complete' => false,
            'changes' => 0,
            'potential_local_customizations' => 'unknown',
            'write_enabled' => false
        ];
        if ($result['baseline_verified']) {
            $result['status'] = 'baseline_available';
            return $result;
        }
        if (!is_array($preview) || !is_array($preview['summary'] ?? null)
            || !is_array($preview['details'] ?? null)
            || !array_key_exists('details_truncated', $preview)
            || !array_key_exists('total', $preview['summary'])
            || !array_key_exists('unresolved', $preview['summary'])) {
            return $result;
        }
        $summary = $preview['summary'];
        if (!is_int($summary['total']) || $summary['total'] < 0
            || !is_int($summary['unresolved']) || $summary['unresolved'] !== 0
            || $preview['details_truncated'] !== false) {
            return $result;
        }
        $result['changes'] = $summary['total'];
        foreach ($preview['details'] as $detail) {
            if (!is_array($detail)
                || !in_array($detail['change_type'] ?? null, ['added', 'updated', 'removed'], true)
                || !is_string($detail['entity_type'] ?? null)
                || trim($detail['entity_type']) === ''
                || !is_string($detail['path'] ?? null)
                || trim($detail['path']) === ''
                || !is_string($detail['field'] ?? null)
                || !array_key_exists('before', $detail)
                || !array_key_exists('after', $detail)) {
                return $result;
            }
        }
        if (count($preview['details']) !== $summary['total']) {
            return $result;
        }
        $result['status'] = 'candidate_for_assisted_review';
        $result['preview_complete'] = true;
        return $result;
    }
}
