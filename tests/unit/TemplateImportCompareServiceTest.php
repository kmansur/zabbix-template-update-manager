<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateImportCompareService;

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

echo "TemplateImportCompareService tests passed.\n";
