<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

/**
 * Flattens Zabbix configuration.importcompare output into stable entity paths.
 *
 * The native import comparison already normalizes current/imported objects and
 * matches structured entities by UUID before falling back to unique fields.
 * This extractor deliberately consumes that result instead of reimplementing
 * Zabbix import semantics from raw template files.
 */
final class ImportCompareEntityExtractor {

	private const CHANGE_KEYS = ['added', 'updated', 'removed'];

	private const FALLBACK_IDENTITY_FIELDS = [
		'template_groups' => ['name'],
		'host_groups' => ['name'],
		'templates' => ['template'],
		'items' => ['key', 'name'],
		'triggers' => ['name', 'expression', 'recovery_expression'],
		'dashboards' => ['name'],
		'httptests' => ['name'],
		'valuemaps' => ['name'],
		'discovery_rules' => ['key', 'name'],
		'item_prototypes' => ['key', 'name'],
		'trigger_prototypes' => ['name', 'expression', 'recovery_expression'],
		'graph_prototypes' => ['name'],
		'host_prototypes' => ['host'],
		'graphs' => ['name']
	];

	public static function extract(array $diff): array {
		$entities = [];
		self::walkContainer($diff, '', $entities);
		ksort($entities, SORT_STRING);
		return $entities;
	}

	private static function walkContainer(array $container, string $parentPath, array &$entities): void {
		foreach ($container as $entityType => $changeBlock) {
			if ($entityType === 'before' || $entityType === 'after' || !is_array($changeBlock)) {
				continue;
			}
			if (!self::isChangeBlock($changeBlock)) {
				continue;
			}

			foreach (self::CHANGE_KEYS as $operation) {
				$records = $changeBlock[$operation] ?? [];
				if (!is_array($records)) {
					throw new RuntimeException('The import comparison contains an invalid change list.');
				}

				foreach ($records as $ordinal => $entityDiff) {
					if (!is_array($entityDiff)) {
						throw new RuntimeException('The import comparison contains an invalid entity change.');
					}

					$beforeExists = array_key_exists('before', $entityDiff);
					$afterExists = array_key_exists('after', $entityDiff);
					$before = $beforeExists ? self::snapshot($entityDiff['before']) : null;
					$after = $afterExists ? self::snapshot($entityDiff['after']) : null;

					if (!$beforeExists && !$afterExists) {
						throw new RuntimeException('The import comparison entity has neither before nor after state.');
					}

					$identity = self::identity((string) $entityType, $before, $after, (int) $ordinal);
					$entityPath = $parentPath.'/'.(string) $entityType.':'.$identity['token'];

					if (array_key_exists($entityPath, $entities)) {
						throw new RuntimeException('The import comparison contains an ambiguous entity identity.');
					}

					$entities[$entityPath] = [
						'path' => $entityPath,
						'parent_path' => $parentPath,
						'entity_type' => (string) $entityType,
						'identity' => $identity['token'],
						'label' => $identity['label'],
						'identity_reliable' => $identity['reliable'],
						'operation' => $operation,
						'before_exists' => $beforeExists,
						'after_exists' => $afterExists,
						'before' => $before,
						'after' => $after
					];

					self::walkContainer($entityDiff, $entityPath, $entities);
				}
			}
		}
	}

	private static function isChangeBlock(array $value): bool {
		foreach (self::CHANGE_KEYS as $key) {
			if (array_key_exists($key, $value)) {
				return true;
			}
		}
		return false;
	}

	private static function snapshot($value): array {
		if (!is_array($value)) {
			throw new RuntimeException('The import comparison contains a non-array entity snapshot.');
		}
		return self::normalize($value);
	}

	private static function identity(string $entityType, ?array $before, ?array $after, int $ordinal): array {
		$source = $after ?? $before ?? [];
		$uuid = self::normalizeUuid((string) ($source['uuid'] ?? ''));
		if (preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			return [
				'token' => 'uuid-'.$uuid,
				'label' => self::displayLabel($source, $uuid),
				'reliable' => true
			];
		}

		$other = $before ?? [];
		$uuid = self::normalizeUuid((string) ($other['uuid'] ?? ''));
		if (preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			return [
				'token' => 'uuid-'.$uuid,
				'label' => self::displayLabel($source ?: $other, $uuid),
				'reliable' => true
			];
		}

		$fields = self::FALLBACK_IDENTITY_FIELDS[$entityType] ?? [];
		$values = [];
		foreach ($fields as $field) {
			$value = $source[$field] ?? $other[$field] ?? null;
			if (is_scalar($value) || $value === null) {
				$values[$field] = (string) $value;
			}
			else {
				$values[$field] = self::canonicalJson($value);
			}
		}

		if ($values !== [] && count(array_filter($values, static fn(string $value): bool => $value !== '')) > 0) {
			return [
				'token' => 'key-'.substr(hash('sha256', self::canonicalJson([$entityType, $values])), 0, 24),
				'label' => self::displayLabel($source ?: $other, $entityType),
				'reliable' => true
			];
		}

		return [
			'token' => 'unresolved-'.substr(hash('sha256', $entityType.'|'.$ordinal.'|'.self::canonicalJson($source ?: $other)), 0, 24),
			'label' => $entityType,
			'reliable' => false
		];
	}

	private static function displayLabel(array $snapshot, string $fallback): string {
		foreach (['name', 'template', 'key', 'host'] as $field) {
			$value = trim((string) ($snapshot[$field] ?? ''));
			if ($value !== '') {
				return $value;
			}
		}
		return $fallback;
	}

	private static function normalize($value) {
		if (!is_array($value)) {
			return $value;
		}

		if (array_is_list($value)) {
			return array_map([self::class, 'normalize'], $value);
		}

		ksort($value, SORT_STRING);
		foreach ($value as &$child) {
			$child = self::normalize($child);
		}
		unset($child);
		return $value;
	}

	private static function canonicalJson($value): string {
		$encoded = json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return is_string($encoded) ? $encoded : '';
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
