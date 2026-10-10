<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Strict, read-only diagnostic for the dashboard auto_start representation.
 * Never modifies importcompare results or authorizes a historical baseline.
 */
final class HistoricalDashboardNormalizationDiagnostic {

	public static function compare(mixed $before, mixed $after): string {
		$left = self::canonical($before);
		$right = self::canonical($after);
		if ($left === null || $right === null) {
			return 'unknown';
		}
		return $left === $right ? 'equivalent' : 'different';
	}

	private static function canonical(mixed $value): ?int {
		if ($value === 0 || $value === '0' || $value === 'NO') {
			return 0;
		}
		if ($value === 1 || $value === '1' || $value === 'YES') {
			return 1;
		}
		return null;
	}

	public static function summarizePreview(array $preview): array {
		$result = ['equivalent' => 0, 'different' => 0, 'unknown' => 0];
		foreach ((array) ($preview['details'] ?? []) as $detail) {
			if (!is_array($detail)
					|| ($detail['entity_type'] ?? null) !== 'dashboards'
					|| ($detail['field'] ?? null) !== 'auto_start'
					|| ($detail['change_type'] ?? null) !== 'updated') {
				continue;
			}
			$status = self::compare($detail['before'] ?? null, $detail['after'] ?? null);
			$result[$status]++;
		}
		$result['truncated'] = !empty($preview['details_truncated']) ? 1 : 0;
		return $result;
	}
}
