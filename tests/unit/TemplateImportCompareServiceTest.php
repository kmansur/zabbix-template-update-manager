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

$installRules = TemplateImportCompareService::rules(TemplateImportCompareService::PROFILE_INSTALL);
assertImportRule(false, $installRules['templates']['updateExisting'],
	'Installation must never update an existing template.');
assertImportRule(true, $installRules['templates']['createMissing'],
	'Installation must allow creation of the selected missing template.');
assertImportRule(false, $installRules['items']['updateExisting'],
	'Installation item rules must remain create-only.');
assertImportRule(false, $installRules['items']['deleteMissing'],
	'Installation must never delete existing items.');
assertImportRule(false, $installRules['template_groups']['updateExisting'],
	'Installation must not rewrite an existing template group.');
assertImportRule(true, $installRules['template_groups']['createMissing'],
	'Installation may create a genuinely missing template group.');
assertImportRule(false, $installRules['templateLinkage']['deleteMissing'],
	'Installation must never remove existing template linkage.');


$service = new TemplateImportCompareService();
$yaml = "zabbix_export:\n  version: '7.0'\n";
$service->compare($yaml, 'yaml');
assertImportRule('yaml', API::$lastImportCompare['format'] ?? null, 'YAML rollback previews must call configuration.importcompare with format=yaml.');
assertImportRule($yaml, API::$lastImportCompare['source'] ?? null, 'YAML rollback preview must preserve the exact artifact bytes.');

$service->compare('{}');
assertImportRule('json', API::$lastImportCompare['format'] ?? null, 'JSON must remain the default comparison format for isolated update candidates.');

$service->compare('{}', 'json', TemplateImportCompareService::PROFILE_INSTALL);
assertImportRule(
	false,
	API::$lastImportCompare['rules']['templates']['updateExisting'] ?? null,
	'Installation preview must use the same create-only template rule profile as installation import.'
);

$threw = false;
try {
	$service->compare('<xml/>', 'xml');
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertImportRule(true, $threw, 'Unsupported comparison formats must fail closed before the Zabbix API call.');

echo "TemplateImportCompareService tests passed.\n";
