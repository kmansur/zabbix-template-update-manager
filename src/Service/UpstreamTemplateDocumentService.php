<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use JsonException;
use RuntimeException;

final class UpstreamTemplateDocumentService {

	public static function buildImportSource(array $document, string $expectedUuid, array $expectedRecord): array {
		[$export, $template, $normalizedUuid] = self::locateTemplate($document, $expectedUuid);
		self::assertIdentity($template, $expectedRecord, $normalizedUuid);
		return self::buildMinimalSource($export, $template);
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

	private static function buildMinimalSource(array $export, array $template): array {
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

		try {
			$source = json_encode(
				['zabbix_export' => $minimalExport],
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('Unable to encode the isolated upstream template source.', 0, $exception);
		}

		return [
			'source' => $source,
			'template' => $template,
			'export_version' => (string) $export['version'],
			'template_group_names' => $templateGroupNames,
			'host_group_names' => $hostGroupNames
		];
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
