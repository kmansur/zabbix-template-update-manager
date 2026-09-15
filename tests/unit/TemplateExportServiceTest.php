<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateExportService;
use RuntimeException;

require_once dirname(__DIR__, 2).'/src/Service/TemplateExportService.php';

function assertTemplateExport($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$captured = null;
$source = "zabbix_export:\n  version: '7.0'\n  templates: []\n";
$service = new TemplateExportService(static function (array $params) use (&$captured, $source): string {
	$captured = $params;
	return $source;
});

$result = $service->export('12345');
assertTemplateExport([
	'format' => 'yaml',
	'prettyprint' => true,
	'options' => ['templates' => ['12345']]
], $captured, 'Template export must use the native configuration.export YAML contract for exactly one template ID.');
assertTemplateExport('yaml', $result['format'], 'Export format must be YAML.');
assertTemplateExport(strlen($source), $result['bytes'], 'Export byte count must be exact.');
assertTemplateExport(hash('sha256', $source), $result['sha256'], 'Export SHA-256 must fingerprint the exact source.');
assertTemplateExport($source, $result['source'], 'Export source must be returned unchanged.');

$threw = false;
try {
	$service->export('../1');
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertTemplateExport(true, $threw, 'Non-numeric template IDs must fail closed.');

$emptyService = new TemplateExportService(static fn(array $params): string => '');
$threw = false;
try {
	$emptyService->export('1');
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertTemplateExport(true, $threw, 'Empty configuration.export output must fail closed.');

echo "TemplateExportService tests passed.\n";
