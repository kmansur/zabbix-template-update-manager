<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use RuntimeException;

final class TemplateImportCompareService {

	public const PROFILE_UPDATE = 'update';
	public const PROFILE_INSTALL = 'install_create_only_v1';

	public function compare(
		string $source,
		string $format = 'json',
		string $ruleProfile = self::PROFILE_UPDATE
	): array {
		if ($source === '') {
			throw new RuntimeException('The comparison source is empty.');
		}

		$format = strtolower(trim($format));
		if (!in_array($format, ['json', 'yaml'], true)) {
			throw new RuntimeException('The comparison source format is not supported.');
		}

		$result = API::Configuration()->importcompare([
			'format' => $format,
			'source' => $source,
			'rules' => self::rules($ruleProfile)
		]);

		if (!is_array($result)) {
			throw new RuntimeException('Zabbix configuration import comparison returned invalid data.');
		}

		return $result;
	}

	public static function rules(string $ruleProfile = self::PROFILE_UPDATE): array {
		if ($ruleProfile === self::PROFILE_INSTALL) {
			return self::installRules();
		}
		if ($ruleProfile !== self::PROFILE_UPDATE) {
			throw new RuntimeException('Unknown reviewed configuration import rule profile.');
		}

		return self::updateRules();
	}

	private static function updateRules(): array {
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

	private static function installRules(): array {
		return [
			'host_groups' => ['updateExisting' => false, 'createMissing' => true],
			'template_groups' => ['updateExisting' => false, 'createMissing' => true],
			'templates' => ['updateExisting' => false, 'createMissing' => true],
			'templateDashboards' => [
				'updateExisting' => false,
				'createMissing' => true,
				'deleteMissing' => false
			],
			'templateLinkage' => ['createMissing' => true, 'deleteMissing' => false],
			'items' => ['updateExisting' => false, 'createMissing' => true, 'deleteMissing' => false],
			'discoveryRules' => ['updateExisting' => false, 'createMissing' => true, 'deleteMissing' => false],
			'triggers' => ['updateExisting' => false, 'createMissing' => true, 'deleteMissing' => false],
			'graphs' => ['updateExisting' => false, 'createMissing' => true, 'deleteMissing' => false],
			'httptests' => ['updateExisting' => false, 'createMissing' => true, 'deleteMissing' => false],
			'valueMaps' => ['updateExisting' => false, 'createMissing' => true, 'deleteMissing' => false]
		];
	}
}
