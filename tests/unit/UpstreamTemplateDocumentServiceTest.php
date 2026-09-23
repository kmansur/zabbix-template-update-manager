<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateIsolationSafetyException;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamTemplateDocumentService;

require_once dirname(__DIR__, 2).'/src/Service/UpstreamTemplateDocumentService.php';

function assertDocumentValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function assertDocumentThrows(callable $callback, string $message): void {
	try {
		$callback();
	}
	catch (RuntimeException $exception) {
		return;
	}

	fwrite(STDERR, $message."\n");
	exit(1);
}

function assertIsolationReason(callable $callback, string $expectedReason, string $message): void {
	try {
		$callback();
	}
	catch (TemplateIsolationSafetyException $exception) {
		if ($exception->getReasonCode() === $expectedReason) {
			return;
		}

		fwrite(STDERR, $message."\nExpected reason: ".$expectedReason."\nActual reason: ".$exception->getReasonCode()."\n");
		exit(1);
	}

	fwrite(STDERR, $message."\n");
	exit(1);
}

$uuid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$document = [
	'zabbix_export' => [
		'version' => '7.0',
		'template_groups' => [
			['uuid' => str_repeat('1', 32), 'name' => 'Templates/Target'],
			['uuid' => str_repeat('2', 32), 'name' => 'Templates/Other']
		],
		'host_groups' => [
			['uuid' => str_repeat('3', 32), 'name' => 'Discovered/Target'],
			['uuid' => str_repeat('4', 32), 'name' => 'Discovered/Other']
		],
		'templates' => [
			[
				'uuid' => $uuid,
				'template' => 'Target by agent',
				'name' => 'Target by agent',
				'vendor' => ['name' => 'Zabbix', 'version' => '7.0-4'],
				'groups' => [['name' => 'Templates/Target']],
				'items' => [[
					'uuid' => str_repeat('7', 32),
					'name' => 'Target metric',
					'key' => 'target.metric'
				]],
				'discovery_rules' => [[
					'uuid' => str_repeat('5', 32),
					'name' => 'Discover',
					'key' => 'discovery',
					'host_prototypes' => [[
						'uuid' => str_repeat('6', 32),
						'host' => '{#HOST}',
						'name' => '{#HOST}',
						'group_links' => [['group' => ['name' => 'Discovered/Target']]]
					]]
				]],
				'dashboards' => [[
					'uuid' => str_repeat('8', 32),
					'name' => 'Target overview',
					'pages' => [[
						'widgets' => [[
							'type' => 'graph',
							'fields' => [[
								'type' => 'GRAPH',
								'name' => 'graphid.0',
								'value' => [
									'host' => 'Target by agent',
									'name' => 'Target overview graph'
								]
							]]
						]]
					]]
				]]
			],
			[
				'uuid' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
				'template' => 'Other',
				'name' => 'Other',
				'vendor' => ['name' => 'Zabbix', 'version' => '7.0-1'],
				'groups' => [['name' => 'Templates/Other']]
			]
		],
		'triggers' => [
			[
				'uuid' => str_repeat('9', 32),
				'expression' => 'last(/Target by agent/target.metric)>0',
				'name' => 'Target trigger'
			],
			[
				'uuid' => str_repeat('a', 32),
				'expression' => 'last(/Other/other.metric)>0',
				'name' => 'Other trigger'
			]
		],
		'graphs' => [
			[
				'uuid' => str_repeat('b', 32),
				'name' => 'Target overview graph',
				'graph_items' => [[
					'item' => [
						'host' => 'Target by agent',
						'key' => 'target.metric'
					]
				]]
			],
			[
				'uuid' => str_repeat('c', 32),
				'name' => 'Other graph',
				'graph_items' => [[
					'item' => [
						'host' => 'Other',
						'key' => 'other.metric'
					]
				]]
			]
		]
	]
];
$expected = [
	'uuid' => $uuid,
	'name' => 'Target by agent',
	'technical_name' => 'Target by agent',
	'vendor_name' => 'Zabbix',
	'vendor_version' => '7.0-4'
];

$result = UpstreamTemplateDocumentService::buildImportSource($document, $uuid, $expected);
$source = json_decode($result['source'], true);
assertDocumentValue(1, count($source['zabbix_export']['templates']), 'Only the requested template must be kept.');
assertDocumentValue($uuid, $source['zabbix_export']['templates'][0]['uuid'], 'The wrong template was isolated.');
assertDocumentValue(['Templates/Target'], $result['template_group_names'], 'Unrelated template groups must be excluded.');
assertDocumentValue(['Discovered/Target'], $result['host_group_names'], 'Unrelated host groups must be excluded.');
assertDocumentValue(1, count($source['zabbix_export']['template_groups']), 'Only referenced template-group definitions must remain.');
assertDocumentValue(1, count($source['zabbix_export']['host_groups']), 'Only referenced host-group definitions must remain.');
assertDocumentValue(1, count($source['zabbix_export']['triggers']), 'Only top-level triggers owned by the selected template must remain.');
assertDocumentValue('Target trigger', $source['zabbix_export']['triggers'][0]['name'], 'The selected template top-level trigger must be preserved.');
assertDocumentValue(1, count($source['zabbix_export']['graphs']), 'Only top-level graphs owned by the selected template must remain.');
assertDocumentValue('Target overview graph', $source['zabbix_export']['graphs'][0]['name'], 'Dashboard graph dependency must be preserved.');
assertDocumentValue(1, $result['top_level_trigger_count'], 'Isolated source must report its preserved top-level trigger count.');
assertDocumentValue(1, $result['top_level_graph_count'], 'Isolated source must report its preserved top-level graph count.');

$historical = UpstreamTemplateDocumentService::buildHistoricalImportSource(
	$document,
	$uuid,
	'7.0-4',
	'Zabbix'
);
$historicalSource = json_decode($historical['source'], true);
assertDocumentValue(1, count($historicalSource['zabbix_export']['graphs']), 'Historical baseline isolation must preserve the same top-level graphs.');
assertDocumentValue(1, count($historicalSource['zabbix_export']['triggers']), 'Historical baseline isolation must preserve the same top-level triggers.');

$badIdentity = $expected;
$badIdentity['vendor_version'] = '7.0-99';
assertDocumentThrows(
	fn() => UpstreamTemplateDocumentService::buildImportSource($document, $uuid, $badIdentity),
	'Identity mismatch against the validated index must fail closed.'
);

assertDocumentThrows(
	fn() => UpstreamTemplateDocumentService::buildImportSource($document, str_repeat('d', 32), $expected),
	'Missing target UUID must fail closed.'
);

$mixedGraph = $document;
$mixedGraph['zabbix_export']['graphs'][0]['graph_items'][] = [
	'item' => ['host' => 'Other', 'key' => 'other.metric']
];
assertIsolationReason(
	fn() => UpstreamTemplateDocumentService::buildImportSource($mixedGraph, $uuid, $expected),
	'cross_template_graph_dependency',
	'Strict isolation must still fail closed on cross-template graph dependencies.'
);
$installGraph = UpstreamTemplateDocumentService::buildImportSource($mixedGraph, $uuid, $expected, true);
assertDocumentValue(['Other'], $installGraph['external_template_names'],
	'Installation isolation must surface cross-template graph hosts as explicit external dependencies.');
$installGraphSource = json_decode($installGraph['source'], true);
assertDocumentValue(1, count($installGraphSource['zabbix_export']['graphs']),
	'Installation isolation must preserve the selected mixed-host graph for dependency-aware preflight.');

$mixedTrigger = $document;
$mixedTrigger['zabbix_export']['triggers'][0]['expression'] =
	'last(/Target by agent/target.metric)>0 and last(/Other/other.metric)>0';
assertIsolationReason(
	fn() => UpstreamTemplateDocumentService::buildImportSource($mixedTrigger, $uuid, $expected),
	'cross_template_trigger_dependency',
	'Strict isolation must still fail closed on cross-template trigger dependencies.'
);
$installTrigger = UpstreamTemplateDocumentService::buildImportSource($mixedTrigger, $uuid, $expected, true);
assertDocumentValue(['Other'], $installTrigger['external_template_names'],
	'Installation isolation must surface cross-template trigger hosts as explicit external dependencies.');
$installTriggerSource = json_decode($installTrigger['source'], true);
assertDocumentValue(1, count($installTriggerSource['zabbix_export']['triggers']),
	'Installation isolation must preserve the selected mixed-host trigger for dependency-aware preflight.');

$historicalMixedTrigger = UpstreamTemplateDocumentService::buildHistoricalImportSource(
	$mixedTrigger,
	$uuid,
	'7.0-4',
	'Zabbix',
	true
);
assertDocumentValue(['Other'], $historicalMixedTrigger['external_template_names'],
	'Dependency-aware historical isolation must preserve and report external trigger hosts.');

$divisionTrigger = $document;
$divisionTrigger['zabbix_export']['triggers'][0]['expression'] =
	'min(/Target by agent/target.metric,5m)/last(/Target by agent/target.metric)>1';
$divisionResult = UpstreamTemplateDocumentService::buildImportSource(
	$divisionTrigger,
	$uuid,
	$expected,
	true
);
assertDocumentValue([], $divisionResult['external_template_names'],
	'Arithmetic division before last() must not create a false external template dependency.');
$divisionSource = json_decode($divisionResult['source'], true);
assertDocumentValue(1, count($divisionSource['zabbix_export']['triggers']),
	'Division expressions that reference only the selected template must remain isolated normally.');

$missingDashboardGraph = $document;
$missingDashboardGraph['zabbix_export']['graphs'] = [$document['zabbix_export']['graphs'][1]];
assertIsolationReason(
	fn() => UpstreamTemplateDocumentService::buildImportSource($missingDashboardGraph, $uuid, $expected),
	'missing_dashboard_graph_dependency',
	'A missing dashboard graph dependency must fail closed with an explicit reason.'
);

echo "UpstreamTemplateDocumentService tests passed.\n";
