<?php

use Modules\ZabbixTemplateUpdateManager\Service\ImportCompareSummary;

require_once dirname(__DIR__, 2).'/src/Service/ImportCompareSummary.php';

function assertCompareSummaryValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$summary = ImportCompareSummary::summarize([
	'templates' => [
		'updated' => [[
			'before' => ['name' => 'Target'],
			'after' => ['name' => 'Target'],
			'items' => [
				'added' => [['name' => 'New item']],
				'updated' => [[
					'before' => ['name' => 'Changed item'],
					'after' => ['name' => 'Changed item']
				]],
				'removed' => [['name' => 'Local only item']]
			],
			'triggers' => [
				'added' => [['name' => 'New trigger']]
			]
		]]
	]
]);

assertCompareSummaryValue(2, $summary['added'], 'Nested added changes were not counted.');
assertCompareSummaryValue(2, $summary['updated'], 'Template and nested updated changes were not counted.');
assertCompareSummaryValue(1, $summary['removed'], 'Nested removed changes were not counted.');
assertCompareSummaryValue(5, $summary['total'], 'Total change count is incorrect.');
assertCompareSummaryValue(
	['added' => 0, 'updated' => 1, 'removed' => 0],
	$summary['by_entity']['templates'],
	'Template-level changes were not classified.'
);
assertCompareSummaryValue(
	['added' => 1, 'updated' => 1, 'removed' => 1],
	$summary['by_entity']['items'],
	'Item-level changes were not classified.'
);

echo "ImportCompareSummary tests passed.\n";
