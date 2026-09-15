<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

/**
 * Read-only wrapper around Zabbix configuration.export for one template.
 */
final class TemplateExportService {

	private const MAX_EXPORT_BYTES = 20971520;

	private $exporter;

	public function __construct(?callable $exporter = null) {
		$this->exporter = $exporter ?? static fn(array $params) => \API::Configuration()->export($params);
	}

	public function export(string $templateId): array {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for export.');
		}

		$params = [
			'format' => 'yaml',
			'prettyprint' => true,
			'options' => [
				'templates' => [$templateId]
			]
		];

		$source = ($this->exporter)($params);
		if (!is_string($source) || trim($source) === '') {
			throw new RuntimeException('Zabbix configuration.export returned an empty or invalid template export.');
		}
		if (strlen($source) > self::MAX_EXPORT_BYTES) {
			throw new RuntimeException('The exported template exceeds the backup size limit.');
		}

		return [
			'format' => 'yaml',
			'source' => $source,
			'bytes' => strlen($source),
			'sha256' => hash('sha256', $source)
		];
	}
}
