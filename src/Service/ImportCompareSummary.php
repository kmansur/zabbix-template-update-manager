<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

final class ImportCompareSummary {

	private const CHANGE_KEYS = ['added', 'updated', 'removed'];

	public static function summarize(array $diff): array {
		$summary = [
			'added' => 0,
			'updated' => 0,
			'removed' => 0,
			'total' => 0,
			'by_entity' => []
		];

		self::walk($diff, null, $summary);
		$summary['total'] = $summary['added'] + $summary['updated'] + $summary['removed'];
		ksort($summary['by_entity'], SORT_NATURAL | SORT_FLAG_CASE);

		return $summary;
	}

	private static function walk(array $node, ?string $entity, array &$summary): void {
		foreach ($node as $key => $value) {
			if ($key === 'before' || $key === 'after' || !is_array($value)) {
				continue;
			}

			if (is_string($key) && in_array($key, self::CHANGE_KEYS, true)) {
				$count = count($value);
				$summary[$key] += $count;

				$entityKey = $entity ?? 'root';
				if (!isset($summary['by_entity'][$entityKey])) {
					$summary['by_entity'][$entityKey] = [
						'added' => 0,
						'updated' => 0,
						'removed' => 0
					];
				}
				$summary['by_entity'][$entityKey][$key] += $count;

				foreach ($value as $change) {
					if (is_array($change)) {
						self::walk($change, null, $summary);
					}
				}
				continue;
			}

			$nextEntity = is_string($key) ? $key : $entity;
			self::walk($value, $nextEntity, $summary);
		}
	}
}
