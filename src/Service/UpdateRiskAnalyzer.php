<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Conservative review-priority classifier for a read-only update preview.
 *
 * Technical severity is intentionally separate from standard-path eligibility.
 * A narrowly known medium-impact change can remain visible as medium while
 * still using the standard controlled path when BASE/LOCAL/UPSTREAM evidence
 * proves there is no local overwrite or conflict.
 */
final class UpdateRiskAnalyzer {

	private const LEVEL_RANK = [
		'none' => 0,
		'low' => 1,
		'medium' => 2,
		'high' => 3,
		'conflict' => 4
	];

	private const VISUAL_ENTITIES = [
		'graphs',
		'graph_prototypes',
		'dashboards'
	];

	private const COLLECTION_ENTITIES = [
		'items',
		'item_prototypes',
		'discovery_rules',
		'host_prototypes',
		'httptests'
	];

	private const ALERT_ENTITIES = [
		'triggers',
		'trigger_prototypes'
	];

	private const HIGH_FIELDS_BY_ENTITY = [
		'items' => ['key', 'type', 'value_type', 'master_item', 'snmp_oid', 'params'],
		'item_prototypes' => ['key', 'type', 'value_type', 'master_item', 'snmp_oid', 'params'],
		'discovery_rules' => ['key', 'type', 'master_item', 'snmp_oid', 'params', 'filter'],
		'triggers' => ['expression', 'recovery_expression', 'dependencies'],
		'trigger_prototypes' => ['expression', 'recovery_expression', 'dependencies'],
		'host_prototypes' => ['host', 'templates', 'group_links', 'group_prototypes', 'interfaces'],
		'httptests' => ['steps', 'authentication', 'http_user', 'http_password', 'variables', 'headers'],
		'templates' => ['templates']
	];

	private const MEDIUM_FIELDS = [
		'delay',
		'timeout',
		'history',
		'trends',
		'status',
		'priority',
		'manual_close',
		'macros',
		'tags',
		'valuemaps',
		'inventory_mode',
		'units'
	];

	private const LOW_FIELDS = [
		'name',
		'description',
		'vendor'
	];

	private const BOUNDED_PREPROCESSING_TYPES = [
		'DISCARD_UNCHANGED',
		'DISCARD_UNCHANGED_HEARTBEAT'
	];

	public static function assess(array $preview, ?array $threeWayAnalysis, int $directHostCount, ?array $hostImpact = null): array {
		$directHostCount = max(0, $directHostCount);
		$hostImpactComplete = is_array($hostImpact) && ($hostImpact['status'] ?? null) === 'complete';
		$totalHostCount = $hostImpactComplete
			? max($directHostCount, (int) ($hostImpact['total_host_count'] ?? 0))
			: $directHostCount;
		$indirectHostCount = $hostImpactComplete
			? max(0, $totalHostCount - $directHostCount)
			: 0;
		$dependentTemplateCount = $hostImpactComplete
			? max(0, (int) ($hostImpact['dependent_template_count'] ?? 0))
			: 0;
		$overlap = self::overlapMap($threeWayAnalysis);
		$details = [];
		$counts = [
			'none' => 0,
			'low' => 0,
			'medium' => 0,
			'high' => 0,
			'conflict' => 0
		];
		$technicalLevel = 'none';
		$standardPathEligible = true;
		$riskReasons = [];

		foreach (($preview['details'] ?? []) as $detail) {
			if (!is_array($detail)) {
				$standardPathEligible = false;
				$riskReasons['invalid_preview_detail'] = true;
				continue;
			}

			$key = self::detailKey($detail);
			$overlapClassification = $overlap[$key] ?? null;
			$assessment = self::detailAssessment($detail, $overlapClassification);
			$level = $assessment['level'];

			$counts[$level]++;
			$technicalLevel = self::maxLevel($technicalLevel, $level);
			$standardPathEligible = $standardPathEligible && $assessment['standard_path_eligible'];
			$riskReasons[$assessment['reason']] = true;

			$detail['overlap_classification'] = $overlapClassification;
			$detail['risk_level'] = $level;
			$detail['standard_path_eligible'] = $assessment['standard_path_eligible'];
			$detail['risk_reason'] = $assessment['reason'];
			$details[] = $detail;
		}

		$threeWaySummary = is_array($threeWayAnalysis) && is_array($threeWayAnalysis['summary'] ?? null)
			? $threeWayAnalysis['summary']
			: null;
		$coverage = 'complete';
		$level = $technicalLevel;

		if ($threeWaySummary === null) {
			$coverage = 'incomplete';
			$level = 'unknown';
			$standardPathEligible = false;
			$riskReasons['three_way_unavailable'] = true;
		}
		else {
			$conflicts = max(0, (int) ($threeWaySummary['conflict'] ?? 0));
			$unresolved = max(0, (int) ($threeWaySummary['unresolved'] ?? 0));
			$overwrite = max(0, (int) ($threeWaySummary['local_only_overwrite'] ?? 0));

			if ($conflicts > 0) {
				$level = 'conflict';
				$standardPathEligible = false;
				$riskReasons['three_way_conflict'] = true;
			}
			elseif ($unresolved > 0) {
				$coverage = 'incomplete';
				$level = 'unknown';
				$standardPathEligible = false;
				$riskReasons['three_way_unresolved'] = true;
			}
			elseif ($overwrite > 0) {
				$level = self::maxLevel($technicalLevel, 'high');
				$standardPathEligible = false;
				$riskReasons['local_customization_overwrite'] = true;
			}
		}

		if ($level === 'high' || $level === 'conflict' || $level === 'unknown') {
			$standardPathEligible = false;
		}

		return [
			'level' => $level,
			'technical_level' => $technicalLevel,
			'coverage' => $coverage,
			'standard_path_eligible' => $standardPathEligible,
			'risk_reasons' => array_values(array_keys($riskReasons)),
			'direct_host_count' => $directHostCount,
			'indirect_host_count' => $indirectHostCount,
			'total_host_count' => $totalHostCount,
			'dependent_template_count' => $dependentTemplateCount,
			'host_impact_complete' => $hostImpactComplete,
			'has_direct_host_impact' => $directHostCount > 0,
			'has_host_impact' => $totalHostCount > 0,
			'counts' => $counts,
			'details' => $details,
			'three_way_summary' => $threeWaySummary
		];
	}

	private static function overlapMap(?array $threeWayAnalysis): array {
		if (!is_array($threeWayAnalysis)) {
			return [];
		}

		$map = [];
		foreach (($threeWayAnalysis['details'] ?? []) as $detail) {
			if (!is_array($detail)) {
				continue;
			}
			$map[self::detailKey($detail)] = (string) ($detail['classification'] ?? '');
		}
		return $map;
	}

	private static function detailKey(array $detail): string {
		return (string) ($detail['path'] ?? '')."\x1f".(string) ($detail['field'] ?? '');
	}

	private static function detailAssessment(array $detail, ?string $overlapClassification): array {
		if ($overlapClassification === 'conflict') {
			return self::assessment('conflict', false, 'three_way_conflict');
		}
		if ($overlapClassification === 'local_only_overwrite') {
			return self::assessment('high', false, 'local_customization_overwrite');
		}
		if ($overlapClassification === 'unresolved') {
			return self::assessment('high', false, 'three_way_unresolved');
		}

		$changeType = (string) ($detail['change_type'] ?? 'unresolved');
		$entityType = (string) ($detail['entity_type'] ?? '');
		$field = (string) ($detail['field'] ?? '');

		if ($changeType === 'unresolved') {
			return self::assessment('high', false, 'unresolved_change');
		}

		if ($changeType === 'removed') {
			if (in_array($entityType, self::VISUAL_ENTITIES, true)
					|| in_array($entityType, ['template_groups', 'host_groups'], true)) {
				return self::assessment('medium', false, 'entity_removed');
			}
			return self::assessment('high', false, 'functional_entity_removed');
		}

		if ($changeType === 'added') {
			if (in_array($entityType, self::VISUAL_ENTITIES, true)
					|| in_array($entityType, ['template_groups', 'host_groups', 'valuemaps'], true)) {
				return self::assessment('low', true, 'low_impact_entity_added');
			}
			if (in_array($entityType, self::COLLECTION_ENTITIES, true)
					|| in_array($entityType, self::ALERT_ENTITIES, true)) {
				return self::assessment('medium', false, 'functional_entity_added');
			}
			return self::assessment('medium', false, 'entity_added');
		}

		if ($changeType !== 'updated') {
			return self::assessment('high', false, 'unknown_change_type');
		}

		if ($field === 'preprocessing'
				&& in_array($entityType, ['items', 'item_prototypes', 'discovery_rules'], true)) {
			if (self::isBoundedPreprocessingChange($detail['before'] ?? null, $detail['after'] ?? null)) {
				return self::assessment('medium', true, 'bounded_preprocessing_discard_change');
			}
			return self::assessment('high', false, 'functional_preprocessing_change');
		}

		if (in_array($field, self::HIGH_FIELDS_BY_ENTITY[$entityType] ?? [], true)) {
			return self::assessment('high', false, 'high_impact_functional_field');
		}
		if (in_array($field, self::MEDIUM_FIELDS, true)) {
			return self::assessment('medium', false, 'medium_impact_functional_field');
		}
		if (in_array($field, self::LOW_FIELDS, true)) {
			return self::assessment('low', true, 'metadata_change');
		}

		if (in_array($entityType, self::VISUAL_ENTITIES, true)) {
			return self::assessment('low', true, 'visual_change');
		}

		return self::assessment('medium', false, 'unknown_functional_field');
	}

	private static function assessment(string $level, bool $standardPathEligible, string $reason): array {
		return [
			'level' => $level,
			'standard_path_eligible' => $standardPathEligible,
			'reason' => $reason
		];
	}

	private static function isBoundedPreprocessingChange($before, $after): bool {
		$beforeSteps = self::preprocessingSteps($before);
		$afterSteps = self::preprocessingSteps($after);

		if ($beforeSteps === null || $afterSteps === null) {
			return false;
		}

		$beforeMap = self::stepMultiset($beforeSteps);
		$afterMap = self::stepMultiset($afterSteps);
		$keys = array_unique(array_merge(array_keys($beforeMap), array_keys($afterMap)));
		$changed = false;

		foreach ($keys as $key) {
			$beforeCount = (int) ($beforeMap[$key]['count'] ?? 0);
			$afterCount = (int) ($afterMap[$key]['count'] ?? 0);
			if ($beforeCount === $afterCount) {
				continue;
			}

			$changed = true;
			$step = $beforeMap[$key]['step'] ?? $afterMap[$key]['step'] ?? null;
			if (!is_array($step)) {
				return false;
			}

			$type = strtoupper(trim((string) ($step['type'] ?? '')));
			if (!in_array($type, self::BOUNDED_PREPROCESSING_TYPES, true)) {
				return false;
			}
		}

		return $changed;
	}

	private static function preprocessingSteps($value): ?array {
		if (is_array($value) && ($value['__state'] ?? null) === 'missing') {
			return [];
		}
		if (!is_array($value)) {
			return null;
		}
		if ($value === []) {
			return [];
		}
		if (array_is_list($value)) {
			foreach ($value as $step) {
				if (!is_array($step)) {
					return null;
				}
			}
			return $value;
		}
		if (array_key_exists('type', $value)) {
			return [$value];
		}

		return null;
	}

	private static function stepMultiset(array $steps): array {
		$result = [];

		foreach ($steps as $step) {
			$canonical = self::canonicalize($step);
			$key = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if (!is_string($key)) {
				$key = serialize($canonical);
			}

			if (!array_key_exists($key, $result)) {
				$result[$key] = [
					'count' => 0,
					'step' => $step
				];
			}
			$result[$key]['count']++;
		}

		return $result;
	}

	private static function canonicalize($value) {
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			return array_map([self::class, 'canonicalize'], $value);
		}

		ksort($value, SORT_STRING);
		foreach ($value as &$child) {
			$child = self::canonicalize($child);
		}
		unset($child);
		return $value;
	}

	private static function maxLevel(string $left, string $right): string {
		return (self::LEVEL_RANK[$right] ?? -1) > (self::LEVEL_RANK[$left] ?? -1)
			? $right
			: $left;
	}
}
