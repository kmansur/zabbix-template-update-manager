<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpdatePreviewAnalyzer;

require_once dirname(__DIR__, 2).'/src/Service/UpdatePreviewAnalyzer.php';

function assertUpdatePreview($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$templateUuid = str_repeat('a', 32);
$itemUuid = str_repeat('b', 32);
$triggerUuid = str_repeat('c', 32);
$diff = [
	'templates' => ['updated' => [[
		'before' => ['uuid' => $templateUuid, 'template' => 'T', 'name' => 'T'],
		'after' => ['uuid' => $templateUuid, 'template' => 'T', 'name' => 'T'],
		'items' => [
			'updated' => [[
				'before' => ['uuid' => $itemUuid, 'name' => 'CPU', 'key' => 'cpu.old', 'delay' => '1m'],
				'after' => ['uuid' => $itemUuid, 'name' => 'CPU', 'key' => 'cpu.new', 'delay' => '30s']
			]]
		],
		'triggers' => [
			'added' => [[
				'after' => ['uuid' => $triggerUuid, 'name' => 'New alert', 'expression' => 'last(/T/cpu.new)>90']
			]]
		]
	]]]
];

$result = UpdatePreviewAnalyzer::analyze($diff);
assertUpdatePreview(2, $result['summary']['updated_fields'], 'Two changed item fields must be reported.');
assertUpdatePreview(1, $result['summary']['added'], 'Added trigger must be reported.');
assertUpdatePreview(3, $result['summary']['total'], 'Total field/entity operations must be counted.');
assertUpdatePreview(2, $result['summary']['entities_affected'], 'Only item and trigger should contain effective changes.');

$fields = array_column($result['details'], 'field');
assertUpdatePreview(true, in_array('key', $fields, true), 'Item key change must be present.');
assertUpdatePreview(true, in_array('delay', $fields, true), 'Item delay change must be present.');
assertUpdatePreview(true, in_array('[entity]', $fields, true), 'Added entity marker must be present.');

echo "UpdatePreviewAnalyzer tests passed.\n";
