<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use RuntimeException;

/**
 * Resolves the host reach of one template through template inheritance.
 *
 * Direct host count is the number of hosts linked to the selected template.
 * Indirect host count is the additional unique host count reached through
 * descendant templates that inherit from the selected template.
 */
final class TemplateHostImpactService {

	private const MAX_GRAPH_TEMPLATES = 5000;

	private $templateGraphLoader;
	private $hostCounter;

	public function __construct(?callable $templateGraphLoader = null, ?callable $hostCounter = null) {
		$this->templateGraphLoader = $templateGraphLoader ?? static fn(): array
			=> API::Template()->get([
				'output' => ['templateid'],
				'selectParentTemplates' => ['templateid']
			]);

		$this->hostCounter = $hostCounter ?? static fn(array $templateIds): int
			=> (int) API::Host()->get([
				'countOutput' => true,
				'templateids' => array_values($templateIds)
			]);
	}

	public function analyze(string $templateId): array {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('Host-impact analysis requires a valid template ID.');
		}

		$records = ($this->templateGraphLoader)();
		if (!is_array($records) || count($records) > self::MAX_GRAPH_TEMPLATES) {
			throw new RuntimeException('Template inheritance graph is unavailable or exceeds the safe analysis bound.');
		}

		$children = [];
		$known = [];

		foreach ($records as $record) {
			if (!is_array($record)) {
				throw new RuntimeException('Template inheritance graph contains an invalid record.');
			}

			$childId = trim((string) ($record['templateid'] ?? ''));
			if ($childId === '' || !ctype_digit($childId) || (int) $childId <= 0) {
				throw new RuntimeException('Template inheritance graph contains an invalid template ID.');
			}
			$known[$childId] = true;

			$parents = is_array($record['parentTemplates'] ?? null)
				? $record['parentTemplates']
				: [];

			foreach ($parents as $parent) {
				if (!is_array($parent)) {
					throw new RuntimeException('Template inheritance graph contains an invalid parent record.');
				}
				$parentId = trim((string) ($parent['templateid'] ?? ''));
				if ($parentId === '' || !ctype_digit($parentId) || (int) $parentId <= 0) {
					throw new RuntimeException('Template inheritance graph contains an invalid parent template ID.');
				}
				$children[$parentId][$childId] = true;
			}
		}

		if (!isset($known[$templateId])) {
			throw new RuntimeException('Selected template is absent from the visible inheritance graph.');
		}

		$descendants = [];
		$queue = array_keys($children[$templateId] ?? []);

		while ($queue !== []) {
			$current = (string) array_shift($queue);
			if ($current === $templateId || isset($descendants[$current])) {
				continue;
			}

			$descendants[$current] = true;
			if (count($descendants) > self::MAX_GRAPH_TEMPLATES) {
				throw new RuntimeException('Template inheritance traversal exceeded the safe analysis bound.');
			}

			foreach (array_keys($children[$current] ?? []) as $childId) {
				if (!isset($descendants[$childId])) {
					$queue[] = $childId;
				}
			}
		}

		$directHosts = max(0, (int) ($this->hostCounter)([$templateId]));
		$impactedTemplateIds = array_values(array_unique(array_merge([$templateId], array_map('strval', array_keys($descendants)))));
		sort($impactedTemplateIds, SORT_STRING);
		$totalHosts = max(0, (int) ($this->hostCounter)($impactedTemplateIds));

		if ($totalHosts < $directHosts) {
			throw new RuntimeException('Host-impact API returned an inconsistent aggregate count.');
		}

		return [
			'status' => 'complete',
			'direct_host_count' => $directHosts,
			'indirect_host_count' => $totalHosts - $directHosts,
			'total_host_count' => $totalHosts,
			'dependent_template_count' => count($descendants),
			'impacted_template_ids' => $impactedTemplateIds
		];
	}
}
