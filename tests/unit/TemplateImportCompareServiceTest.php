<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateImportCompareService;

if (!class_exists('API')) {
	class API {
		public static array $lastImportCompare = [];

		public static function Configuration(): object {
			return new class {
				public function importcompare(array $params): array {
					API::$lastImportCompare = $params;
					return [];
				}
			};
		}
	}
}

require_once dirname(__DIR__, 2).'/src/Service/TemplateImportCompareService.php';

function assertImportRule($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$rules = TemplateImportCompareService::rules();
assertImportRule(true, $rules['templates']['updateExisting'], 'Template updates must be included in preview.');
assertImportRule(true, $rules['items']['deleteMissing'], 'Local-only items must be visible as preview removals.');
assertImportRule(true, $rules['discoveryRules']['deleteMissing'], 'Local-only discovery rules must be visible as preview removals.');
assertImportRule(true, $rules['templateLinkage']['deleteMissing'], 'Local-only template links must be visible as preview removals.');
assertImportRule(false, array_key_exists('hosts', $rules), 'Host imports are outside template content comparison scope.');

$service = new TemplateImportCompareService();
$yaml = "zabbix_export:\n  version: '7.0'\n";
$service->compare($yaml, 'yaml');
assertImportRule('yaml', API::$lastImportCompare['format'] ?? null, 'YAML rollback previews must call configuration.importcompare with format=yaml.');
assertImportRule($yaml, API::$lastImportCompare['source'] ?? null, 'YAML rollback preview must preserve the exact artifact bytes.');

$service->compare('{}');
assertImportRule('json', API::$lastImportCompare['format'] ?? null, 'JSON must remain the default comparison format for isolated update candidates.');

$threw = false;
try {
	$service->compare('<xml/>', 'xml');
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertImportRule(true, $threw, 'Unsupported comparison formats must fail closed before the Zabbix API call.');

echo "TemplateImportCompareService tests passed.\n";
