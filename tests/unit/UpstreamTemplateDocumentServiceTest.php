<?php

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
				]]
			],
			[
				'uuid' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
				'template' => 'Other',
				'name' => 'Other',
				'vendor' => ['name' => 'Zabbix', 'version' => '7.0-1'],
				'groups' => [['name' => 'Templates/Other']]
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

$badIdentity = $expected;
$badIdentity['vendor_version'] = '7.0-99';
assertDocumentThrows(
	fn() => UpstreamTemplateDocumentService::buildImportSource($document, $uuid, $badIdentity),
	'Identity mismatch against the validated index must fail closed.'
);

assertDocumentThrows(
	fn() => UpstreamTemplateDocumentService::buildImportSource($document, str_repeat('c', 32), $expected),
	'Missing target UUID must fail closed.'
);

echo "UpstreamTemplateDocumentService tests passed.\n";
