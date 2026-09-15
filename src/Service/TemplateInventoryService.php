<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;

final class TemplateInventoryService {

	private TemplateRepository $repository;

	public function __construct(TemplateRepository $repository) {
		$this->repository = $repository;
	}

	public function getInventory(): array {
		return self::fromRecords($this->repository->findAll());
	}

	public static function fromRecords(array $records): array {
		$templates = [];

		foreach ($records as $record) {
			$templates[] = self::normalizeTemplate($record);
		}

		return [
			'templates' => $templates,
			'summary' => self::summarize($templates)
		];
	}

	public static function emptySummary(): array {
		return [
			'total' => 0,
			'zabbix_vendor' => 0,
			'other_vendor' => 0,
			'unidentified_vendor' => 0,
			'without_vendor_version' => 0,
			'in_use' => 0
		];
	}

	private static function normalizeTemplate(array $record): array {
		$technicalName = trim((string) ($record['host'] ?? ''));
		$visibleName = trim((string) ($record['name'] ?? ''));
		$vendorName = trim((string) ($record['vendor_name'] ?? ''));
		$vendorVersion = trim((string) ($record['vendor_version'] ?? ''));
		$groups = [];

		foreach (($record['templategroups'] ?? []) as $group) {
			$name = trim((string) ($group['name'] ?? ''));
			if ($name !== '') {
				$groups[] = $name;
			}
		}

		natcasesort($groups);
		$groups = array_values(array_unique($groups));

		return [
			'templateid' => (string) ($record['templateid'] ?? ''),
			'name' => $visibleName !== '' ? $visibleName : $technicalName,
			'technical_name' => $technicalName,
			'uuid' => trim((string) ($record['uuid'] ?? '')),
			'vendor_name' => $vendorName,
			'vendor_version' => $vendorVersion,
			'vendor_classification' => self::classifyVendor($vendorName),
			'groups' => $groups,
			'host_count' => max(0, (int) ($record['hosts'] ?? 0))
		];
	}

	private static function classifyVendor(string $vendorName): string {
		if ($vendorName === '') {
			return 'unidentified_vendor';
		}

		return strcasecmp($vendorName, 'Zabbix') === 0
			? 'zabbix_vendor'
			: 'other_vendor';
	}

	private static function summarize(array $templates): array {
		$summary = self::emptySummary();
		$summary['total'] = count($templates);

		foreach ($templates as $template) {
			$classification = $template['vendor_classification'];
			if (array_key_exists($classification, $summary)) {
				$summary[$classification]++;
			}

			if ($template['vendor_version'] === '') {
				$summary['without_vendor_version']++;
			}

			if ($template['host_count'] > 0) {
				$summary['in_use']++;
			}
		}

		return $summary;
	}
}
