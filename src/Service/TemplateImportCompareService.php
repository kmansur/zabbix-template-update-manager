<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use RuntimeException;

final class TemplateImportCompareService {

	public function compare(string $source, string $format = 'json'): array {
		if ($source === '') {
			throw new RuntimeException('The comparison source is empty.');
		}

		$format = strtolower(trim($format));
		if (!in_array($format, ['json', 'yaml'], true)) {
			throw new RuntimeException('The comparison source format is not supported.');
		}

		return API::Configuration()->importcompare([
			'format' => $format,
			'source' => $source,
			'rules' => self::rules()
		]);
	}

	public static function rules(): array {
		return [
			'host_groups' => ['updateExisting' => true, 'createMissing' => true],
			'template_groups' => ['updateExisting' => true, 'createMissing' => true],
			'templates' => ['updateExisting' => true, 'createMissing' => true],
			'templateDashboards' => [
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => true
			],
			'templateLinkage' => ['createMissing' => true, 'deleteMissing' => true],
			'items' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
			'discoveryRules' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
			'triggers' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
			'graphs' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
			'httptests' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true],
			'valueMaps' => ['updateExisting' => true, 'createMissing' => true, 'deleteMissing' => true]
		];
	}
}
