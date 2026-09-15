<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use RuntimeException;

final class TemplateImportCompareService {

	public function compare(string $source): array {
		if ($source === '') {
			throw new RuntimeException('The comparison source is empty.');
		}

		return API::Configuration()->importcompare([
			'format' => 'json',
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
