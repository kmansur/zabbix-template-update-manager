<?php

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;

require_once dirname(__DIR__, 2).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__, 2).'/src/Service/TemplateInventoryService.php';

function assertInventoryValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$inventory = TemplateInventoryService::fromRecords([
	[
		'templateid' => '10001',
		'host' => 'Linux by Zabbix agent',
		'name' => 'Linux by Zabbix agent',
		'uuid' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		'vendor_name' => 'Zabbix',
		'vendor_version' => '7.0-8',
		'hosts' => '12',
		'templategroups' => [
			['groupid' => '2', 'name' => 'Templates/Operating systems'],
			['groupid' => '1', 'name' => 'Templates']
		]
	],
	[
		'templateid' => '10002',
		'host' => 'Vendor appliance',
		'name' => 'Vendor Appliance',
		'uuid' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
		'vendor_name' => 'Acme',
		'vendor_version' => '1.2.3',
		'hosts' => '0',
		'templategroups' => []
	],
	[
		'templateid' => '10003',
		'host' => 'Legacy template',
		'name' => '',
		'uuid' => 'cccccccccccccccccccccccccccccccc',
		'vendor_name' => '',
		'vendor_version' => '',
		'hosts' => '2',
		'templategroups' => [['groupid' => '1', 'name' => 'Templates']]
	]
]);

assertInventoryValue(3, count($inventory['templates']), 'Unexpected number of normalized templates.');
assertInventoryValue('zabbix_vendor', $inventory['templates'][0]['vendor_classification'], 'Zabbix vendor classification failed.');
assertInventoryValue(['Templates', 'Templates/Operating systems'], $inventory['templates'][0]['groups'], 'Template groups were not normalized/sorted.');
assertInventoryValue(12, $inventory['templates'][0]['host_count'], 'Host count normalization failed.');
assertInventoryValue('other_vendor', $inventory['templates'][1]['vendor_classification'], 'Third-party vendor classification failed.');
assertInventoryValue('Legacy template', $inventory['templates'][2]['name'], 'Technical name fallback failed.');
assertInventoryValue('unidentified_vendor', $inventory['templates'][2]['vendor_classification'], 'Missing vendor classification failed.');
assertInventoryValue([
	'total' => 3,
	'zabbix_vendor' => 1,
	'other_vendor' => 1,
	'unidentified_vendor' => 1,
	'without_vendor_version' => 1,
	'in_use' => 2
], $inventory['summary'], 'Inventory summary is incorrect.');

echo "TemplateInventoryService tests passed.\n";
