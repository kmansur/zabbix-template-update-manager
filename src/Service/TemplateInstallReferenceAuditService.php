<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

require_once __DIR__.'/ZabbixExpressionHostExtractor.php';

/**
 * Performs bounded, read-only structural reference checks on one isolated
 * template before configuration.importcompare/import.
 *
 * The audit only blocks references that can be proven unresolved from the
 * selected template plus already-validated linked-template names. It does not
 * attempt to replace Zabbix's authoritative import validation.
 */
final class TemplateInstallReferenceAuditService {

	public static function analyze(array $template, array $linkedTemplateNames = []): array {
		$selfNames = [];
		foreach (['template', 'name'] as $field) {
			$name = trim((string) ($template[$field] ?? ''));
			if ($name !== '') {
				$selfNames[$name] = true;
			}
		}

		$allowedHosts = $selfNames;
		foreach ($linkedTemplateNames as $name) {
			$name = trim((string) $name);
			if ($name !== '') {
				$allowedHosts[$name] = true;
			}
		}

		$allItemKeys = self::allItemKeys($template);
		$topLevelItemKeys = self::topLevelItemKeys($template);
		$valueMapNames = self::valueMapNames($template);
		$issues = [];
		$counts = [
			'linked_templates' => count(array_unique(array_map('strval', $linkedTemplateNames))),
			'item_keys' => count($allItemKeys),
			'top_level_item_keys' => count($topLevelItemKeys),
			'master_item_references' => 0,
			'value_map_references' => 0,
			'dashboard_item_references' => 0,
			'trigger_host_references' => 0,
			'trigger_item_references' => 0,
			'graph_item_host_references' => 0,
			'graph_item_references' => 0
		];

		self::auditNestedReferences(
			$template,
			$allItemKeys,
			$valueMapNames,
			$linkedTemplateNames,
			$issues,
			$counts
		);
		self::auditDashboards($template, $allowedHosts, $topLevelItemKeys, $issues, $counts);
		self::auditTriggerReferences($template, $allowedHosts, $allItemKeys, $issues, $counts);
		self::auditGraphReferences($template, $template, $allowedHosts, $allItemKeys, $issues, $counts);

		$issues = self::uniqueIssues($issues);

		return [
			'safe' => $issues === [],
			'issues' => $issues,
			'counts' => $counts
		];
	}

	private static function allItemKeys(array $template): array {
		$keys = [];
		self::collectKeys($template['items'] ?? [], $keys);

		foreach (($template['discovery_rules'] ?? []) as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			$key = trim((string) ($rule['key'] ?? ''));
			if ($key !== '') {
				$keys[$key] = true;
			}

			self::collectKeys($rule['item_prototypes'] ?? [], $keys);
		}

		return $keys;
	}

	private static function topLevelItemKeys(array $template): array {
		$keys = [];
		foreach (($template['items'] ?? []) as $item) {
			if (!is_array($item)) {
				continue;
			}

			$key = trim((string) ($item['key'] ?? ''));
			if ($key !== '') {
				$keys[$key] = true;
			}
		}

		return $keys;
	}

	private static function collectKeys($value, array &$keys): void {
		if (!is_array($value)) {
			return;
		}

		foreach ($value as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$key = trim((string) ($entry['key'] ?? ''));
			if ($key !== '') {
				$keys[$key] = true;
			}
		}
	}

	private static function valueMapNames(array $template): array {
		$names = [];
		foreach (($template['valuemaps'] ?? []) as $valueMap) {
			if (!is_array($valueMap)) {
				continue;
			}

			$name = trim((string) ($valueMap['name'] ?? ''));
			if ($name !== '') {
				$names[$name] = true;
			}
		}

		return $names;
	}

	private static function auditNestedReferences(
		$value,
		array $allItemKeys,
		array $valueMapNames,
		array $linkedTemplateNames,
		array &$issues,
		array &$counts,
		?string $key = null
	): void {
		if (!is_array($value)) {
			return;
		}

		if ($key === 'master_item') {
			$masterKey = trim((string) ($value['key'] ?? ''));
			if ($masterKey !== '') {
				$counts['master_item_references']++;

				// A missing master key can only be proven invalid when the
				// template has no linked-template source that might provide it.
				if (!isset($allItemKeys[$masterKey]) && $linkedTemplateNames === []) {
					$issues[] = [
						'code' => 'missing_master_item',
						'reference' => $masterKey
					];
				}
			}
		}

		if ($key === 'valuemap') {
			$name = trim((string) ($value['name'] ?? ''));
			if ($name !== '') {
				$counts['value_map_references']++;
				if (!isset($valueMapNames[$name])) {
					$issues[] = [
						'code' => 'missing_value_map',
						'reference' => $name
					];
				}
			}
		}

		foreach ($value as $childKey => $childValue) {
			self::auditNestedReferences(
				$childValue,
				$allItemKeys,
				$valueMapNames,
				$linkedTemplateNames,
				$issues,
				$counts,
				is_string($childKey) ? $childKey : null
			);
		}
	}

	private static function auditDashboards(
		array $template,
		array $allowedHosts,
		array $topLevelItemKeys,
		array &$issues,
		array &$counts
	): void {
		foreach (($template['dashboards'] ?? []) as $dashboard) {
			if (!is_array($dashboard)) {
				continue;
			}

			foreach (($dashboard['pages'] ?? []) as $page) {
				if (!is_array($page)) {
					continue;
				}

				foreach (($page['widgets'] ?? []) as $widget) {
					if (!is_array($widget)) {
						continue;
					}

					foreach (($widget['fields'] ?? []) as $field) {
						if (!is_array($field) || strtoupper((string) ($field['type'] ?? '')) !== 'ITEM') {
							continue;
						}

						$value = is_array($field['value'] ?? null) ? $field['value'] : [];
						$host = trim((string) ($value['host'] ?? ''));
						$itemKey = trim((string) ($value['key'] ?? ''));
						$counts['dashboard_item_references']++;

						if ($host === '' || !isset($allowedHosts[$host])) {
							$issues[] = [
								'code' => 'unresolved_dashboard_item_host',
								'reference' => $host !== '' ? $host : '(empty)'
							];
							continue;
						}

						if (isset($allowedHosts[$host]) && self::isSelfHost($template, $host)
								&& ($itemKey === '' || !isset($topLevelItemKeys[$itemKey]))) {
							$issues[] = [
								'code' => 'missing_dashboard_item',
								'reference' => $host.':'.$itemKey
							];
						}
					}
				}
			}
		}
	}

	private static function auditTriggerReferences(
		array $template,
		array $allowedHosts,
		array $allItemKeys,
		array &$issues,
		array &$counts
	): void {
		$expressions = [];
		self::collectExpressions($template, $expressions);

		foreach ($expressions as $expression) {
			foreach (ZabbixExpressionHostExtractor::extractReferences($expression) as $reference) {
				$host = trim((string) ($reference['host'] ?? ''));
				$itemKey = trim((string) ($reference['item'] ?? ''));
				if ($host === '') {
					continue;
				}

				$counts['trigger_host_references']++;
				if (!isset($allowedHosts[$host])) {
					$issues[] = [
						'code' => 'unresolved_trigger_host',
						'reference' => $host
					];
					continue;
				}

				if ($itemKey !== '' && self::isSelfHost($template, $host)) {
					$counts['trigger_item_references']++;
					if (!isset($allItemKeys[$itemKey])) {
						$issues[] = [
							'code' => 'missing_trigger_item',
							'reference' => $host.':'.$itemKey
						];
					}
				}
			}
		}
	}

	private static function collectExpressions($value, array &$expressions, ?string $key = null): void {
		if (is_string($value)) {
			if ($key === 'expression' || $key === 'recovery_expression') {
				$expressions[] = $value;
			}
			return;
		}

		if (!is_array($value)) {
			return;
		}

		foreach ($value as $childKey => $childValue) {
			self::collectExpressions(
				$childValue,
				$expressions,
				is_string($childKey) ? $childKey : null
			);
		}
	}

	private static function auditGraphReferences(
		$value,
		array $template,
		array $allowedHosts,
		array $allItemKeys,
		array &$issues,
		array &$counts,
		?string $key = null
	): void {
		if (!is_array($value)) {
			return;
		}

		if ($key === 'graph_items' && array_is_list($value)) {
			foreach ($value as $graphItem) {
				if (!is_array($graphItem) || !is_array($graphItem['item'] ?? null)) {
					continue;
				}
				self::auditGraphItemReference(
					$template,
					$graphItem['item'],
					$allowedHosts,
					$allItemKeys,
					$issues,
					$counts
				);
			}
		}

		if (($key === 'ymin_item_1' || $key === 'ymax_item_1')
				&& array_key_exists('host', $value)
				&& array_key_exists('key', $value)) {
			self::auditGraphItemReference(
				$template,
				$value,
				$allowedHosts,
				$allItemKeys,
				$issues,
				$counts
			);
		}

		foreach ($value as $childKey => $childValue) {
			self::auditGraphReferences(
				$childValue,
				$template,
				$allowedHosts,
				$allItemKeys,
				$issues,
				$counts,
				is_string($childKey) ? $childKey : null
			);
		}
	}

	private static function auditGraphItemReference(
		array $template,
		array $item,
		array $allowedHosts,
		array $allItemKeys,
		array &$issues,
		array &$counts
	): void {
		$host = trim((string) ($item['host'] ?? ''));
		$itemKey = trim((string) ($item['key'] ?? ''));
		if ($host === '') {
			return;
		}

		$counts['graph_item_host_references']++;
		if (!isset($allowedHosts[$host])) {
			$issues[] = [
				'code' => 'unresolved_graph_item_host',
				'reference' => $host
			];
			return;
		}

		if ($itemKey !== '' && self::isSelfHost($template, $host)) {
			$counts['graph_item_references']++;
			if (!isset($allItemKeys[$itemKey])) {
				$issues[] = [
					'code' => 'missing_graph_item',
					'reference' => $host.':'.$itemKey
				];
			}
		}
	}

	private static function isSelfHost(array $template, string $host): bool {
		foreach (['template', 'name'] as $field) {
			if (trim((string) ($template[$field] ?? '')) === $host) {
				return true;
			}
		}

		return false;
	}

	private static function uniqueIssues(array $issues): array {
		$unique = [];
		foreach ($issues as $issue) {
			if (!is_array($issue)) {
				continue;
			}

			$code = trim((string) ($issue['code'] ?? ''));
			$reference = trim((string) ($issue['reference'] ?? ''));
			if ($code === '') {
				continue;
			}

			$unique[$code."\0".$reference] = [
				'code' => $code,
				'reference' => $reference
			];
		}

		return array_values($unique);
	}
}
