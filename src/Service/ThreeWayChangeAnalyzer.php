<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Reconstructs BASE / LOCAL / UPSTREAM states from two native Zabbix
 * configuration.importcompare results that share the same LOCAL state.
 *
 * Historical comparison direction: LOCAL (before) -> BASE (after)
 * Current comparison direction:    LOCAL (before) -> UPSTREAM (after)
 */
final class ThreeWayChangeAnalyzer {

	private const CLASSIFICATIONS = [
		'upstream_only',
		'local_only_overwrite',
		'converged',
		'conflict',
		'unresolved'
	];

	public static function analyze(array $historicalDiff, array $currentDiff, int $detailLimit = 250): array {
		$historical = ImportCompareEntityExtractor::extract($historicalDiff);
		$current = ImportCompareEntityExtractor::extract($currentDiff);
		$paths = array_unique(array_merge(array_keys($historical), array_keys($current)));
		sort($paths, SORT_STRING);

		$summary = array_fill_keys(self::CLASSIFICATIONS, 0);
		$summary['total'] = 0;
		$summary['entities_affected'] = 0;
		$details = [];
		$affectedEntities = [];
		$truncated = false;

		foreach ($paths as $path) {
			$historicalEntity = $historical[$path] ?? null;
			$currentEntity = $current[$path] ?? null;

			if (($historicalEntity !== null && !$historicalEntity['identity_reliable'])
					|| ($currentEntity !== null && !$currentEntity['identity_reliable'])) {
				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					self::detailFromEntity($path, $historicalEntity ?? $currentEntity, '[identity]', 'unresolved', null, null, null)
				);
				$affectedEntities[$path] = true;
				continue;
			}

			$states = self::resolveStates($historicalEntity, $currentEntity);
			if ($states === null) {
				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					self::detailFromEntity($path, $historicalEntity ?? $currentEntity, '[local state]', 'unresolved', null, null, null)
				);
				$affectedEntities[$path] = true;
				continue;
			}

			[$base, $local, $upstream] = $states;

			if (!$base['exists'] || !$local['exists'] || !$upstream['exists']) {
				if (self::stateEquals($base, $local) && self::stateEquals($local, $upstream)) {
					continue;
				}
				$classification = self::classify($base, $local, $upstream);
				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					self::detailFromEntity(
						$path,
						$historicalEntity ?? $currentEntity,
						'[entity]',
						$classification,
						self::displayState($base),
						self::displayState($local),
						self::displayState($upstream)
					)
				);
				$affectedEntities[$path] = true;
				continue;
			}

			$fields = array_unique(array_merge(
				array_keys($base['value']),
				array_keys($local['value']),
				array_keys($upstream['value'])
			));
			sort($fields, SORT_STRING);

			foreach ($fields as $field) {
				if ($field === 'uuid') {
					continue;
				}

				$baseField = self::fieldState($base['value'], $field);
				$localField = self::fieldState($local['value'], $field);
				$upstreamField = self::fieldState($upstream['value'], $field);

				if (self::stateEquals($baseField, $localField)
						&& self::stateEquals($localField, $upstreamField)) {
					continue;
				}

				$classification = self::classify($baseField, $localField, $upstreamField);
				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					self::detailFromEntity(
						$path,
						$historicalEntity ?? $currentEntity,
						(string) $field,
						$classification,
						self::displayState($baseField),
						self::displayState($localField),
						self::displayState($upstreamField)
					)
				);
				$affectedEntities[$path] = true;
			}
		}

		$summary['entities_affected'] = count($affectedEntities);

		return [
			'status' => self::overallStatus($summary),
			'summary' => $summary,
			'details' => $details,
			'details_truncated' => $truncated,
			'detail_limit' => $detailLimit
		];
	}

	private static function resolveStates(?array $historical, ?array $current): ?array {
		if ($historical === null && $current === null) {
			return null;
		}

		$historicalLocal = $historical !== null
			? self::entityState($historical, 'before')
			: null;
		$currentLocal = $current !== null
			? self::entityState($current, 'before')
			: null;

		if ($historicalLocal !== null && $currentLocal !== null
				&& !self::stateEquals($historicalLocal, $currentLocal)) {
			return null;
		}

		$local = $historicalLocal ?? $currentLocal;
		if ($local === null) {
			return null;
		}

		$base = $historical !== null
			? self::entityState($historical, 'after')
			: $local;
		$upstream = $current !== null
			? self::entityState($current, 'after')
			: $local;

		return [$base, $local, $upstream];
	}

	private static function entityState(array $entity, string $side): array {
		$existsKey = $side.'_exists';
		return [
			'exists' => (bool) ($entity[$existsKey] ?? false),
			'value' => (bool) ($entity[$existsKey] ?? false)
				? (array) ($entity[$side] ?? [])
				: null
		];
	}

	private static function fieldState(array $snapshot, string $field): array {
		return array_key_exists($field, $snapshot)
			? ['exists' => true, 'value' => $snapshot[$field]]
			: ['exists' => false, 'value' => null];
	}

	private static function classify(array $base, array $local, array $upstream): string {
		$baseLocal = self::stateEquals($base, $local);
		$baseUpstream = self::stateEquals($base, $upstream);
		$localUpstream = self::stateEquals($local, $upstream);

		if ($baseLocal && !$localUpstream) {
			return 'upstream_only';
		}
		if (!$baseLocal && $baseUpstream) {
			return 'local_only_overwrite';
		}
		if (!$baseLocal && $localUpstream) {
			return 'converged';
		}
		if (!$baseLocal && !$baseUpstream && !$localUpstream) {
			return 'conflict';
		}

		return 'unresolved';
	}

	private static function stateEquals(array $left, array $right): bool {
		if ($left['exists'] !== $right['exists']) {
			return false;
		}
		if (!$left['exists']) {
			return true;
		}
		return self::canonicalize($left['value']) === self::canonicalize($right['value']);
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

	private static function displayState(array $state) {
		return $state['exists'] ? $state['value'] : ['__state' => 'missing'];
	}

	private static function detailFromEntity(
		string $path,
		?array $entity,
		string $field,
		string $classification,
		$base,
		$local,
		$upstream
	): array {
		return [
			'path' => $path,
			'entity_type' => (string) ($entity['entity_type'] ?? ''),
			'entity' => (string) ($entity['label'] ?? $path),
			'field' => $field,
			'classification' => $classification,
			'base' => $base,
			'local' => $local,
			'upstream' => $upstream
		];
	}

	private static function record(
		array &$summary,
		array &$details,
		bool &$truncated,
		int $detailLimit,
		array $detail
	): void {
		$classification = $detail['classification'];
		if (!array_key_exists($classification, $summary)) {
			$classification = 'unresolved';
			$detail['classification'] = $classification;
		}

		$summary[$classification]++;
		$summary['total']++;

		if (count($details) < max(0, $detailLimit)) {
			$details[] = $detail;
		}
		else {
			$truncated = true;
		}
	}

	private static function overallStatus(array $summary): string {
		if ($summary['conflict'] > 0) {
			return 'conflict_detected';
		}
		if ($summary['unresolved'] > 0) {
			return 'needs_review';
		}
		if ($summary['local_only_overwrite'] > 0) {
			return 'local_overwrite_risk';
		}
		if ($summary['converged'] > 0) {
			return 'compatible_overlap';
		}
		if ($summary['upstream_only'] > 0) {
			return 'upstream_only';
		}
		return 'no_changes';
	}
}
