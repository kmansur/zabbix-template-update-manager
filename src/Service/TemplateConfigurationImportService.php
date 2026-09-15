<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use RuntimeException;

require_once __DIR__.'/TemplateImportCompareService.php';

/**
 * The single intentionally write-enabled Zabbix configuration boundary.
 *
 * Callers must complete fresh preflight validation before invoking this class.
 * This service imports one already isolated official template source using the
 * exact same rule profile used by configuration.importcompare.
 */
final class TemplateConfigurationImportService {

	public function import(string $source, string $format = 'json'): void {
		if ($source === '') {
			throw new RuntimeException('The controlled template import source is empty.');
		}
		if (!in_array($format, ['json', 'yaml'], true)) {
			throw new RuntimeException('The controlled template import format is not supported.');
		}

		$result = API::Configuration()->import([
			'format' => $format,
			'source' => $source,
			'rules' => TemplateImportCompareService::rules()
		]);

		if ($result !== true) {
			throw new RuntimeException('Zabbix configuration import did not report success.');
		}
	}
}
