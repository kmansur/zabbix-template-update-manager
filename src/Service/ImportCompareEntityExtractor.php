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
		self::walkContainer($diff, '', $entities, true);
		ksort($entities, SORT_STRING);
		return $entities;
	}

	private static function walkContainer(
		array $container,
		string $parentPath,
		array &$entities,
		bool $parentReliable
	): void {
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

					/*
					 * Zabbix compareByStructure() intentionally removes direct before/after
					 * state from an updated structured entity when only one of its nested
					 * children changed. The node then acts only as a structural wrapper.
					 *
					 * There is no authoritative identity left in that wrapper, so recurse
					 * through it using a deterministic scope and downgrade descendant
					 * identity reliability. This preserves the useful native diff while
					 * keeping readiness fail-closed instead of aborting the entire analysis.
					 */
					if (!$beforeExists && !$afterExists) {
						if (!self::hasNestedChangeBlock($entityDiff)) {
							throw new RuntimeException(
								'The import comparison entity has neither before/after state nor nested changes.'
							);
						}

						$scopePath = $parentPath.'/'.(string) $entityType
							.':structural-'.(string) $operation.'-'.(int) $ordinal;
						self::walkContainer($entityDiff, $scopePath, $entities, false);
						continue;
					}

					$identity = self::identity((string) $entityType, $before, $after, (int) $ordinal);
					$identityReliable = $identity['reliable'] && $parentReliable;
					$identityIssue = null;
					if (!$parentReliable) {
						$identityIssue = 'unresolved_parent_identity';
					}
					elseif (!$identity['reliable']) {
						$identityIssue = 'unresolved_identity';
					}

					$entityPath = $parentPath.'/'.(string) $entityType.':'.$identity['token'];
					$record = [
						'path' => $entityPath,
						'parent_path' => $parentPath,
						'entity_type' => (string) $entityType,
						'identity' => $identity['token'],
						'label' => $identity['label'],
						'identity_reliable' => $identityReliable,
						'identity_issue' => $identityIssue,
						'operation' => $operation,
						'before_exists' => $beforeExists,
						'after_exists' => $afterExists,
						'before' => $before,
						'after' => $after
					];

					$storedPath = self::storeEntity(
						$entities,
						$entityPath,
						$record,
						(string) $entityType,
						(string) $operation,
						(int) $ordinal
					);

					$storedReliable = (bool) ($entities[$storedPath]['identity_reliable'] ?? false);
					self::walkContainer($entityDiff, $storedPath, $entities, $storedReliable);
				}
			}
		}
	}

	/**
	 * Store one normalized entity without allowing an identity collision to
	 * abort the complete template analysis.
	 *
	 * A collision means our fallback identity is insufficient to prove which
	 * native Zabbix entity is which. Both records are therefore marked
	 * unreliable and the second record receives a deterministic collision path.
	 * Downstream analyzers will fail closed for these records while continuing
	 * to explain all other entities in the comparison.
	 */
	private static function storeEntity(
		array &$entities,
		string $entityPath,
		array $record,
		string $entityType,
		string $operation,
		int $ordinal
	): string {
		if (!array_key_exists($entityPath, $entities)) {
			$entities[$entityPath] = $record;
			return $entityPath;
		}

		$entities[$entityPath]['identity_reliable'] = false;
		$entities[$entityPath]['identity_issue'] = 'ambiguous_identity';
		self::markDescendantsUnreliable($entities, $entityPath, 'ambiguous_parent_identity');

		$fingerprint = substr(hash('sha256', self::canonicalJson([
			$entityType,
			$operation,
			$ordinal,
			$record['before'],
			$record['after']
		])), 0, 16);
		$collisionPath = $entityPath.'~collision-'.$fingerprint;
		$counter = 2;
		while (array_key_exists($collisionPath, $entities)) {
			$collisionPath = $entityPath.'~collision-'.$fingerprint.'-'.$counter;
			$counter++;
		}

		$record['path'] = $collisionPath;
		$record['identity_reliable'] = false;
		$record['identity_issue'] = 'ambiguous_identity';
		$entities[$collisionPath] = $record;

		return $collisionPath;
	}

	private static function markDescendantsUnreliable(array &$entities, string $parentPath, string $issue): void {
		$prefix = $parentPath.'/';
		foreach ($entities as &$entity) {
			$path = (string) ($entity['path'] ?? '');
			if (!str_starts_with($path, $prefix)) {
				continue;
			}

			$entity['identity_reliable'] = false;
			if (($entity['identity_issue'] ?? null) === null) {
				$entity['identity_issue'] = $issue;
			}
		}
		unset($entity);
	}

	private static function hasNestedChangeBlock(array $entityDiff): bool {
		foreach ($entityDiff as $key => $value) {
			if ($key === 'before' || $key === 'after' || !is_array($value)) {
				continue;
			}
			if (self::isChangeBlock($value)) {
				return true;
			}
		}

		return false;
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
		// For updated entities, LOCAL (before) is the stable pivot shared by both
		// historical and current comparisons. Added entities naturally fall back
		// to the after state.
		$source = $before ?? $after ?? [];
		$other = $after ?? [];

		$uuid = self::normalizeUuid((string) ($source['uuid'] ?? ''));
		if (preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			return [
				'token' => 'uuid-'.$uuid,
				'label' => self::displayLabel($source, $uuid),
				'reliable' => true
			];
		}

		$uuid = self::normalizeUuid((string) ($other['uuid'] ?? ''));
		if (preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			return [
				'token' => 'uuid-'.$uuid,
				'label' => self::displayLabel($source ?: $other, $uuid),
				'reliable' => true
			];
		}

		$values = self::fallbackIdentityValues($entityType, $source, $other);
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

	private static function fallbackIdentityValues(string $entityType, array $source, array $other): array {
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

		// Zabbix 7.x/8.x importcompare does not identify graphs by name alone.
		// Its uniqueness rule also incorporates the host values referenced by
		// graph_items[].item.host. Mirroring that here prevents valid same-name
		// graph/graph-prototype records from collapsing into one local identity.
		if (in_array($entityType, ['graphs', 'graph_prototypes'], true)) {
			$graphHosts = self::graphItemHosts($source);
			if ($graphHosts === []) {
				$graphHosts = self::graphItemHosts($other);
			}
			if ($graphHosts !== []) {
				$values['graph_item_hosts'] = self::canonicalJson($graphHosts);
			}
		}

		return $values;
	}

	private static function graphItemHosts(array $snapshot): array {
		$graphItems = $snapshot['graph_items'] ?? [];
		if (!is_array($graphItems)) {
			return [];
		}

		$hosts = [];
		foreach ($graphItems as $graphItem) {
			if (!is_array($graphItem)) {
				continue;
			}
			$item = $graphItem['item'] ?? null;
			if (!is_array($item)) {
				continue;
			}
			$host = trim((string) ($item['host'] ?? ''));
			if ($host !== '') {
				$hosts[$host] = true;
			}
		}

		$hosts = array_keys($hosts);
		sort($hosts, SORT_STRING);
		return $hosts;
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
