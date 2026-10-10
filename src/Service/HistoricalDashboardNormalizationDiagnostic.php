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

	/** Only fixed vocabulary is returned, never raw values or field identifiers. */
	public static function describeUnknownPreview(array $preview): array {
		$results = [];
		foreach ((array) ($preview['details'] ?? []) as $detail) {
			if (!is_array($detail)
					|| ($detail['entity_type'] ?? null) !== 'dashboards'
					|| ($detail['field'] ?? null) !== 'auto_start'
					|| ($detail['change_type'] ?? null) !== 'updated'
					|| self::compare($detail['before'] ?? null, $detail['after'] ?? null) !== 'unknown') {
				continue;
			}
			$before = $detail['before'] ?? null;
			$after = $detail['after'] ?? null;
			$results[] = [
				'before_present' => array_key_exists('before', $detail),
				'after_present' => array_key_exists('after', $detail),
				'before_type' => self::safeType($before),
				'after_type' => self::safeType($after),
				'before_known' => self::canonical($before) !== null,
				'after_known' => self::canonical($after) !== null,
				'before_shape' => self::safeArrayShape($before),
				'after_shape' => self::safeArrayShape($after),
				'before_count' => is_array($before) ? min(count($before), 100) : 0,
				'after_count' => is_array($after) ? min(count($after), 100) : 0
			];
			if (count($results) >= 5) {
				break;
			}
		}
		return $results;
	}

	/** Classifies shape using fixed labels only; no arbitrary keys or values escape. */
	private static function safeArrayShape(mixed $value): string {
		if (!is_array($value)) {
			return 'not_array';
		}
		if ($value === ['__state' => 'missing']) {
			return 'missing_marker';
		}
		if ($value === []) {
			return 'empty_array';
		}
		if (array_is_list($value)) {
			return 'list';
		}
		return 'associative';
	}

	private static function safeType(mixed $value): string {
		return match (gettype($value)) {
			'integer' => 'integer',
			'string' => 'string',
			'boolean' => 'boolean',
			'array' => 'array',
			'NULL' => 'null',
			'double' => 'float',
			default => 'other'
		};
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
