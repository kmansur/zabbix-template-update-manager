<?php

use Modules\ZabbixTemplateUpdateManager\Service\ImportCompareEntityExtractor;

require_once dirname(__DIR__, 2).'/src/Service/ImportCompareEntityExtractor.php';

function assertEntityExtractor($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$templateUuid = str_repeat('a', 32);
$itemUuid = str_repeat('b', 32);
$diff = [
	'templates' => [
		'updated' => [[
			'before' => ['uuid' => $templateUuid, 'template' => 'T', 'name' => 'T'],
			'after' => ['uuid' => $templateUuid, 'template' => 'T', 'name' => 'T'],
			'items' => [
				'updated' => [[
					'before' => ['uuid' => $itemUuid, 'name' => 'CPU', 'key' => 'cpu', 'delay' => '30s'],
					'after' => ['uuid' => $itemUuid, 'name' => 'CPU', 'key' => 'cpu', 'delay' => '1m']
				]]
			]
		]]
	]
];

$entities = ImportCompareEntityExtractor::extract($diff);
$templatePath = '/templates:uuid-'.$templateUuid;
$itemPath = $templatePath.'/items:uuid-'.$itemUuid;

assertEntityExtractor(true, isset($entities[$templatePath]), 'Template UUID must form a stable parent path.');
assertEntityExtractor(true, isset($entities[$itemPath]), 'Nested item UUID must form a stable child path.');
assertEntityExtractor('30s', $entities[$itemPath]['before']['delay'], 'Before state must be retained.');
assertEntityExtractor('1m', $entities[$itemPath]['after']['delay'], 'After state must be retained.');
assertEntityExtractor(true, $entities[$itemPath]['identity_reliable'], 'UUID identity must be authoritative.');

$fallback = ImportCompareEntityExtractor::extract([
	'template_groups' => [
		'added' => [[
			'after' => ['name' => 'Templates/Test']
		]]
	]
]);
$fallbackRecord = reset($fallback);
assertEntityExtractor(true, str_starts_with($fallbackRecord['identity'], 'key-'), 'Known unique fields must provide a deterministic fallback identity.');
assertEntityExtractor(true, $fallbackRecord['identity_reliable'], 'Known unique fields are a reliable fallback when UUID is absent.');

echo "ImportCompareEntityExtractor tests passed.\n";
