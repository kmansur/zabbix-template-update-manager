<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Converts the current LOCAL -> UPSTREAM import comparison into field-level
 * update operations without reimplementing Zabbix import semantics.
 */
final class UpdatePreviewAnalyzer {

	public static function analyze(array $currentDiff, int $detailLimit = 500): array {
		$entities = ImportCompareEntityExtractor::extract($currentDiff);
		$summary = [
			'added' => 0,
			'removed' => 0,
			'updated_fields' => 0,
			'unresolved' => 0,
			'total' => 0,
			'entities_affected' => 0,
			'by_entity' => []
		];
		$details = [];
		$truncated = false;
		$affected = [];

		foreach ($entities as $path => $entity) {
			if (!$entity['identity_reliable']) {
				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					$entity,
					'[identity]',
					'unresolved',
					null,
					null
				);
				$affected[$path] = true;
				continue;
			}

			$beforeExists = (bool) $entity['before_exists'];
			$afterExists = (bool) $entity['after_exists'];

			if ($beforeExists !== $afterExists) {
				$changeType = $afterExists ? 'added' : 'removed';
				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					$entity,
					'[entity]',
					$changeType,
					$beforeExists ? $entity['before'] : ['__state' => 'missing'],
					$afterExists ? $entity['after'] : ['__state' => 'missing']
				);
				$affected[$path] = true;
				continue;
			}

			if (!$beforeExists) {
				continue;
			}

			$before = (array) $entity['before'];
			$after = (array) $entity['after'];
			$fields = array_unique(array_merge(array_keys($before), array_keys($after)));
			sort($fields, SORT_STRING);

			foreach ($fields as $field) {
				if ($field === 'uuid') {
					continue;
				}

				$beforeState = self::fieldState($before, $field);
				$afterState = self::fieldState($after, $field);
				if (self::stateEquals($beforeState, $afterState)) {
					continue;
				}

				self::record(
					$summary,
					$details,
					$truncated,
					$detailLimit,
					$entity,
					(string) $field,
					'updated',
					self::displayState($beforeState),
					self::displayState($afterState)
				);
				$affected[$path] = true;
			}
		}

		$summary['entities_affected'] = count($affected);
		ksort($summary['by_entity'], SORT_STRING);

		return [
			'summary' => $summary,
			'details' => $details,
			'details_truncated' => $truncated,
			'detail_limit' => $detailLimit
		];
	}

	private static function record(
		array &$summary,
		array &$details,
		bool &$truncated,
		int $detailLimit,
		array $entity,
		string $field,
		string $changeType,
		$before,
		$after
	): void {
		if ($changeType === 'added') {
			$summary['added']++;
		}
		elseif ($changeType === 'removed') {
			$summary['removed']++;
		}
		elseif ($changeType === 'updated') {
			$summary['updated_fields']++;
		}
		else {
			$changeType = 'unresolved';
			$summary['unresolved']++;
		}

		$summary['total']++;
		$entityType = (string) $entity['entity_type'];
		if (!array_key_exists($entityType, $summary['by_entity'])) {
			$summary['by_entity'][$entityType] = [
				'added' => 0,
				'removed' => 0,
				'updated' => 0,
				'unresolved' => 0
			];
		}
		$bucket = $changeType === 'updated' ? 'updated' : $changeType;
		$summary['by_entity'][$entityType][$bucket]++;

		$detail = [
			'path' => (string) $entity['path'],
			'entity_type' => $entityType,
			'entity' => (string) $entity['label'],
			'field' => $field,
			'change_type' => $changeType,
			'before' => $before,
			'after' => $after
		];

		if (count($details) < max(0, $detailLimit)) {
			$details[] = $detail;
		}
		else {
			$truncated = true;
		}
	}

	private static function fieldState(array $snapshot, string $field): array {
		return array_key_exists($field, $snapshot)
			? ['exists' => true, 'value' => $snapshot[$field]]
			: ['exists' => false, 'value' => null];
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
}
