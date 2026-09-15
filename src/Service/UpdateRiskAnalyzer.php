<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Conservative review-priority classifier for a read-only update preview.
 *
 * This is deliberately not an "update is safe" decision. Technical severity
 * and three-way coverage are returned separately so missing historical context
 * cannot be hidden behind a low-looking score.
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
		'items' => ['key', 'type', 'value_type', 'master_item', 'snmp_oid', 'params', 'preprocessing'],
		'item_prototypes' => ['key', 'type', 'value_type', 'master_item', 'snmp_oid', 'params', 'preprocessing'],
		'discovery_rules' => ['key', 'type', 'master_item', 'snmp_oid', 'params', 'preprocessing', 'filter'],
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

	public static function assess(array $preview, ?array $threeWayAnalysis, int $directHostCount): array {
		$directHostCount = max(0, $directHostCount);
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

		foreach (($preview['details'] ?? []) as $detail) {
			$key = self::detailKey($detail);
			$overlapClassification = $overlap[$key] ?? null;
			$level = self::detailLevel($detail, $overlapClassification);
			$counts[$level]++;
			$technicalLevel = self::maxLevel($technicalLevel, $level);
			$detail['overlap_classification'] = $overlapClassification;
			$detail['risk_level'] = $level;
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
		}
		else {
			$conflicts = max(0, (int) ($threeWaySummary['conflict'] ?? 0));
			$unresolved = max(0, (int) ($threeWaySummary['unresolved'] ?? 0));
			$overwrite = max(0, (int) ($threeWaySummary['local_only_overwrite'] ?? 0));

			if ($conflicts > 0) {
				$level = 'conflict';
			}
			elseif ($unresolved > 0) {
				$coverage = 'incomplete';
				$level = 'unknown';
			}
			elseif ($overwrite > 0) {
				$level = self::maxLevel($technicalLevel, 'high');
			}
		}

		return [
			'level' => $level,
			'technical_level' => $technicalLevel,
			'coverage' => $coverage,
			'direct_host_count' => $directHostCount,
			'has_direct_host_impact' => $directHostCount > 0,
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

	private static function detailLevel(array $detail, ?string $overlapClassification): string {
		if ($overlapClassification === 'conflict') {
			return 'conflict';
		}
		if ($overlapClassification === 'local_only_overwrite' || $overlapClassification === 'unresolved') {
			return 'high';
		}

		$changeType = (string) ($detail['change_type'] ?? 'unresolved');
		$entityType = (string) ($detail['entity_type'] ?? '');
		$field = (string) ($detail['field'] ?? '');

		if ($changeType === 'unresolved') {
			return 'high';
		}

		if ($changeType === 'removed') {
			if (in_array($entityType, self::VISUAL_ENTITIES, true)
					|| in_array($entityType, ['template_groups', 'host_groups'], true)) {
				return 'medium';
			}
			return 'high';
		}

		if ($changeType === 'added') {
			if (in_array($entityType, self::VISUAL_ENTITIES, true)
					|| in_array($entityType, ['template_groups', 'host_groups', 'valuemaps'], true)) {
				return 'low';
			}
			if (in_array($entityType, self::COLLECTION_ENTITIES, true)
					|| in_array($entityType, self::ALERT_ENTITIES, true)) {
				return 'medium';
			}
			return 'medium';
		}

		if ($changeType !== 'updated') {
			return 'high';
		}

		if (in_array($field, self::HIGH_FIELDS_BY_ENTITY[$entityType] ?? [], true)) {
			return 'high';
		}
		if (in_array($field, self::MEDIUM_FIELDS, true)) {
			return 'medium';
		}
		if (in_array($field, self::LOW_FIELDS, true)) {
			return 'low';
		}

		if (in_array($entityType, self::VISUAL_ENTITIES, true)) {
			return 'low';
		}

		// Unknown functional fields are deliberately not treated as cosmetic.
		return 'medium';
	}

	private static function maxLevel(string $left, string $right): string {
		return (self::LEVEL_RANK[$right] ?? -1) > (self::LEVEL_RANK[$left] ?? -1)
			? $right
			: $left;
	}
}
