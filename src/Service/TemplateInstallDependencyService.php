<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Resolves template-link dependencies required by one upstream template.
 *
 * The first installation implementation is intentionally non-recursive:
 * referenced templates must already be installed locally.
 */
final class TemplateInstallDependencyService {

	public static function analyze(
		array $template,
		array $localRecords,
		array $additionalRequiredNames = []
	): array {
		$required = [];
		self::collectTemplateLinks($template, $required);
		foreach ($additionalRequiredNames as $name) {
			$name = trim((string) $name);
			if ($name !== '') {
				$required[$name] = true;
			}
		}

		$selfNames = [];
		foreach (['template', 'name'] as $field) {
			$name = trim((string) ($template[$field] ?? ''));
			if ($name !== '') {
				$selfNames[$name] = true;
			}
		}
		foreach (array_keys($selfNames) as $selfName) {
			unset($required[$selfName]);
		}

		$installedNames = [];
		foreach ($localRecords as $record) {
			if (!is_array($record)) {
				continue;
			}
			foreach (['host', 'name'] as $field) {
				$name = trim((string) ($record[$field] ?? ''));
				if ($name !== '') {
					$installedNames[$name] = true;
				}
			}
		}

		$requiredNames = array_keys($required);
		sort($requiredNames, SORT_NATURAL | SORT_FLAG_CASE);
		$installed = [];
		$missing = [];

		foreach ($requiredNames as $name) {
			if (isset($installedNames[$name])) {
				$installed[] = $name;
			}
			else {
				$missing[] = $name;
			}
		}

		return [
			'required' => $requiredNames,
			'installed' => $installed,
			'missing' => $missing,
			'complete' => $missing === []
		];
	}

	private static function collectTemplateLinks($value, array &$required, ?string $key = null): void {
		if (!is_array($value)) {
			return;
		}

		if ($key === 'templates' && array_is_list($value)) {
			foreach ($value as $link) {
				if (!is_array($link)) {
					continue;
				}
				$name = trim((string) ($link['name'] ?? $link['template'] ?? ''));
				if ($name !== '') {
					$required[$name] = true;
				}
			}
		}

		foreach ($value as $childKey => $childValue) {
			self::collectTemplateLinks(
				$childValue,
				$required,
				is_string($childKey) ? $childKey : null
			);
		}
	}
}
