<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Merges the local installed inventory with the validated official upstream
 * index. Upstream-only records are catalog entries, not synthetic local
 * templates, and therefore never receive a local template ID.
 */
final class UpstreamCatalogService {

	public static function merge(array $localTemplates, ?array $index): array {
		$records = is_array($index['templates'] ?? null) ? $index['templates'] : [];
		$localUuids = [];
		$officialInstalled = 0;

		foreach ($localTemplates as &$template) {
			$template['installation_status'] = 'installed';
			$uuid = self::normalizeUuid((string) ($template['uuid'] ?? ''));
			if ($uuid !== '') {
				$localUuids[$uuid] = true;
				if (array_key_exists($uuid, $records)) {
					$officialInstalled++;
				}
			}
		}
		unset($template);

		$missing = [];
		foreach ($records as $uuid => $record) {
			$uuid = self::normalizeUuid((string) $uuid);
			if ($uuid === '' || isset($localUuids[$uuid]) || !is_array($record)) {
				continue;
			}

			$missing[] = [
				'templateid' => '',
				'name' => trim((string) ($record['name'] ?? $record['technical_name'] ?? $uuid)),
				'technical_name' => trim((string) ($record['technical_name'] ?? '')),
				'uuid' => $uuid,
				'vendor_name' => trim((string) ($record['vendor_name'] ?? '')),
				'vendor_version' => '',
				'vendor_classification' => strcasecmp(trim((string) ($record['vendor_name'] ?? '')), 'Zabbix') === 0
					? 'zabbix_vendor'
					: 'other_vendor',
				'groups' => [],
				'host_count' => 0,
				'installation_status' => 'not_installed',
				'upstream_status' => 'official_catalog',
				'upstream' => $record
			];
		}

		return [
			'templates' => array_merge($localTemplates, $missing),
			'summary' => [
				'local_visible' => count($localTemplates),
				'official_installed' => $officialInstalled,
				'official_catalog_total' => count($records),
				'not_installed' => count($missing)
			]
		];
	}

	public static function emptySummary(): array {
		return [
			'local_visible' => 0,
			'official_installed' => 0,
			'official_catalog_total' => 0,
			'not_installed' => 0
		];
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
