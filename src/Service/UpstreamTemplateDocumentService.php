<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use JsonException;
use RuntimeException;

final class TemplateIsolationSafetyException extends RuntimeException {

	private string $reasonCode;

	public function __construct(string $reasonCode, string $message) {
		parent::__construct($message);
		$this->reasonCode = $reasonCode;
	}

	public function getReasonCode(): string {
		return $this->reasonCode;
	}
}

final class UpstreamTemplateDocumentService {

	public static function buildImportSource(
		array $document,
		string $expectedUuid,
		array $expectedRecord,
		bool $allowExternalTemplateReferences = false
	): array {
		[$export, $template, $normalizedUuid] = self::locateTemplate($document, $expectedUuid);
		self::assertIdentity($template, $expectedRecord, $normalizedUuid);
		return self::buildMinimalSource($export, $template, $allowExternalTemplateReferences);
	}

	public static function buildHistoricalImportSource(
		array $document,
		string $expectedUuid,
		string $expectedVendorVersion,
		string $expectedVendorName = 'Zabbix'
	): array {
		[$export, $template] = self::locateTemplate($document, $expectedUuid);
		$metadata = self::metadataFromTemplate($template);

		if ($metadata['vendor_version'] !== trim($expectedVendorVersion)) {
			throw new RuntimeException('The historical template vendor version does not match the requested baseline.');
		}
		if ($expectedVendorName !== '' && $metadata['vendor_name'] !== $expectedVendorName) {
			throw new RuntimeException('The historical template vendor does not match the requested baseline.');
		}

		return self::buildMinimalSource($export, $template);
	}

	public static function templateMetadata(array $document, string $expectedUuid): array {
		[, $template, $normalizedUuid] = self::locateTemplate($document, $expectedUuid);
		return ['uuid' => $normalizedUuid] + self::metadataFromTemplate($template);
	}

	private static function locateTemplate(array $document, string $expectedUuid): array {
		$expectedUuid = self::normalizeUuid($expectedUuid);
		if (!preg_match('/^[a-f0-9]{32}$/', $expectedUuid)) {
			throw new RuntimeException('The expected template UUID is invalid.');
		}

		$export = $document['zabbix_export'] ?? null;
		if (!is_array($export) || !is_string($export['version'] ?? null) || trim($export['version']) === '') {
			throw new RuntimeException('The upstream source is not a valid Zabbix export document.');
		}

		$templates = $export['templates'] ?? null;
		if (!is_array($templates)) {
			throw new RuntimeException('The upstream source does not contain templates.');
		}

		$matches = [];
		foreach ($templates as $template) {
			if (is_array($template) && self::normalizeUuid((string) ($template['uuid'] ?? '')) === $expectedUuid) {
				$matches[] = $template;
			}
		}

		if (count($matches) !== 1) {
			throw new RuntimeException('The upstream source does not contain exactly one matching template UUID.');
		}

		return [$export, $matches[0], $expectedUuid];
	}

	private static function metadataFromTemplate(array $template): array {
		$vendor = is_array($template['vendor'] ?? null) ? $template['vendor'] : [];
		return [
			'name' => trim((string) ($template['name'] ?? $template['template'] ?? '')),
			'technical_name' => trim((string) ($template['template'] ?? '')),
			'vendor_name' => trim((string) ($vendor['name'] ?? '')),
			'vendor_version' => trim((string) ($vendor['version'] ?? ''))
		];
	}

	private static function assertIdentity(array $template, array $expectedRecord, string $expectedUuid): void {
		$actual = ['uuid' => $expectedUuid] + self::metadataFromTemplate($template);

		foreach (['uuid', 'name', 'technical_name', 'vendor_name', 'vendor_version'] as $field) {
			if ($actual[$field] !== trim((string) ($expectedRecord[$field] ?? ''))) {
				throw new RuntimeException('The upstream source template identity does not match the validated index.');
			}
		}
	}

	private static function buildMinimalSource(
		array $export,
		array $template,
		bool $allowExternalTemplateReferences = false
	): array {
		$minimalExport = [
			'version' => $export['version']
		];

		$templateGroupNames = self::templateGroupNames($template);
		if ($templateGroupNames !== []) {
			$minimalExport['template_groups'] = self::filterDefinitions(
				$export['template_groups'] ?? [],
				$templateGroupNames,
				'template group'
			);
		}

		$hostGroupNames = self::hostGroupNames($template);
		if ($hostGroupNames !== []) {
			$minimalExport['host_groups'] = self::filterDefinitions(
				$export['host_groups'] ?? [],
				$hostGroupNames,
				'host group'
			);
		}

		$minimalExport['templates'] = [$template];

		$technicalName = trim((string) ($template['template'] ?? ''));
		if ($technicalName === '') {
			throw new RuntimeException('The selected upstream template has no technical name.');
		}

		$externalTemplateNames = [];
		$triggers = self::filterTopLevelTriggers(
			$export['triggers'] ?? [],
			$technicalName,
			$allowExternalTemplateReferences,
			$externalTemplateNames
		);
		if ($triggers !== []) {
			$minimalExport['triggers'] = $triggers;
		}

		$graphs = self::filterTopLevelGraphs(
			$export['graphs'] ?? [],
			$technicalName,
			$allowExternalTemplateReferences,
			$externalTemplateNames
		);
		if ($graphs !== []) {
			$minimalExport['graphs'] = $graphs;
		}
		self::assertDashboardGraphReferences(
			$template,
			$graphs,
			$technicalName,
			$allowExternalTemplateReferences,
			$externalTemplateNames
		);

		try {
			$source = json_encode(
				['zabbix_export' => $minimalExport],
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('Unable to encode the isolated upstream template source.', 0, $exception);
		}

		$externalTemplateNames = array_keys($externalTemplateNames);
		sort($externalTemplateNames, SORT_NATURAL | SORT_FLAG_CASE);

		return [
			'source' => $source,
			'template' => $template,
			'export_version' => (string) $export['version'],
			'template_group_names' => $templateGroupNames,
			'host_group_names' => $hostGroupNames,
			'top_level_trigger_count' => count($triggers),
			'top_level_graph_count' => count($graphs),
			'external_template_names' => $externalTemplateNames
		];
	}

	private static function filterTopLevelGraphs(
		$definitions,
		string $technicalName,
		bool $allowExternalTemplateReferences = false,
		array &$externalTemplateNames = []
	): array {
		if (!is_array($definitions)) {
			throw new RuntimeException('The upstream source contains invalid top-level graph definitions.');
		}

		$result = [];
		foreach ($definitions as $graph) {
			if (!is_array($graph)) {
				continue;
		}

			$hosts = [];
			foreach (($graph['graph_items'] ?? []) as $graphItem) {
				if (!is_array($graphItem) || !is_array($graphItem['item'] ?? null)) {
					continue;
				}
				$host = trim((string) ($graphItem['item']['host'] ?? ''));
				if ($host !== '') {
					$hosts[$host] = true;
				}
			}

			if (!isset($hosts[$technicalName])) {
				continue;
			}
			if (count($hosts) !== 1) {
				if (!$allowExternalTemplateReferences) {
					throw new TemplateIsolationSafetyException(
						'cross_template_graph_dependency',
						'The selected template has a cross-template top-level graph dependency that cannot be isolated safely.'
					);
				}
				foreach (array_keys($hosts) as $host) {
					if ($host !== $technicalName) {
						$externalTemplateNames[$host] = true;
					}
				}
			}

			$result[] = $graph;
		}

		return $result;
	}

	private static function filterTopLevelTriggers(
		$definitions,
		string $technicalName,
		bool $allowExternalTemplateReferences = false,
		array &$externalTemplateNames = []
	): array {
		if (!is_array($definitions)) {
			throw new RuntimeException('The upstream source contains invalid top-level trigger definitions.');
		}

		$result = [];
		foreach ($definitions as $trigger) {
			if (!is_array($trigger)) {
				continue;
			}

			$hosts = self::triggerHostNames($trigger);
			if (!isset($hosts[$technicalName])) {
				continue;
			}
			if (count($hosts) !== 1) {
				if (!$allowExternalTemplateReferences) {
					throw new TemplateIsolationSafetyException(
						'cross_template_trigger_dependency',
						'The selected template has a cross-template top-level trigger dependency that cannot be isolated safely.'
					);
				}
				foreach (array_keys($hosts) as $host) {
					if ($host !== $technicalName) {
						$externalTemplateNames[$host] = true;
					}
				}
			}

			$result[] = $trigger;
		}

		return $result;
	}

	private static function triggerHostNames(array $trigger): array {
		$expressions = [];
		self::collectTriggerExpressions($trigger, $expressions);

		$hosts = [];
		foreach ($expressions as $expression) {
			if (preg_match_all('~/([^/\r\n]+)/[^,\)\s]+~', $expression, $matches) !== false) {
				foreach (($matches[1] ?? []) as $host) {
					$host = trim((string) $host);
					if ($host !== '') {
						$hosts[$host] = true;
					}
				}
			}
		}

		return $hosts;
	}

	private static function collectTriggerExpressions($value, array &$expressions, ?string $key = null): void {
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
			self::collectTriggerExpressions(
				$childValue,
				$expressions,
				is_string($childKey) ? $childKey : null
			);
		}
	}

	private static function assertDashboardGraphReferences(
		array $template,
		array $graphs,
		string $technicalName,
		bool $allowExternalTemplateReferences = false,
		array &$externalTemplateNames = []
	): void {
		$available = [];
		foreach ($graphs as $graph) {
			if (!is_array($graph)) {
				continue;
			}
			$name = trim((string) ($graph['name'] ?? ''));
			if ($name !== '') {
				$available[$name] = true;
			}
		}

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
						if (!is_array($field) || (string) ($field['type'] ?? '') !== 'GRAPH') {
							continue;
						}
						$value = is_array($field['value'] ?? null) ? $field['value'] : [];
						$host = trim((string) ($value['host'] ?? ''));
						$name = trim((string) ($value['name'] ?? ''));

						if ($host !== '' && $host !== $technicalName) {
							if (!$allowExternalTemplateReferences) {
								throw new TemplateIsolationSafetyException(
									'cross_template_dashboard_dependency',
									'The selected template dashboard references a graph from another template and cannot be isolated safely.'
								);
							}
							$externalTemplateNames[$host] = true;
							continue;
						}
						if ($host === $technicalName && ($name === '' || !isset($available[$name]))) {
							throw new TemplateIsolationSafetyException(
								'missing_dashboard_graph_dependency',
								'The selected template dashboard references a top-level graph that is missing from the isolated source.'
							);
						}
					}
				}
			}
		}
	}

	private static function templateGroupNames(array $template): array {
		$names = [];
		foreach (($template['groups'] ?? []) as $group) {
			if (is_array($group)) {
				$name = trim((string) ($group['name'] ?? ''));
				if ($name !== '') {
					$names[$name] = true;
				}
			}
		}

		return array_keys($names);
	}

	private static function hostGroupNames(array $template): array {
		$names = [];
		foreach (($template['discovery_rules'] ?? []) as $discoveryRule) {
			if (!is_array($discoveryRule)) {
				continue;
			}
			foreach (($discoveryRule['host_prototypes'] ?? []) as $hostPrototype) {
				if (!is_array($hostPrototype)) {
					continue;
				}
				foreach (($hostPrototype['group_links'] ?? []) as $groupLink) {
					if (!is_array($groupLink) || !is_array($groupLink['group'] ?? null)) {
						continue;
					}
					$name = trim((string) ($groupLink['group']['name'] ?? ''));
					if ($name !== '') {
						$names[$name] = true;
					}
				}
			}
		}

		return array_keys($names);
	}

	private static function filterDefinitions($definitions, array $requiredNames, string $label): array {
		if (!is_array($definitions)) {
			throw new RuntimeException(sprintf('The upstream source is missing required %s definitions.', $label));
		}

		$required = array_fill_keys($requiredNames, true);
		$matched = [];
		foreach ($definitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}
			$name = trim((string) ($definition['name'] ?? ''));
			if ($name !== '' && isset($required[$name])) {
				$matched[$name] = $definition;
			}
		}

		if (count($matched) !== count($required)) {
			throw new RuntimeException(sprintf('The upstream source is missing a referenced %s definition.', $label));
		}

		$result = [];
		foreach ($requiredNames as $name) {
			$result[] = $matched[$name];
		}
		return $result;
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
