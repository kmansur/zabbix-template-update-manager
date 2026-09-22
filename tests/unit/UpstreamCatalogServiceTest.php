<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpstreamCatalogService;

require_once dirname(__DIR__, 2).'/src/Service/UpstreamCatalogService.php';

function assertCatalogValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$installedUuid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$missingUuid = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

$result = UpstreamCatalogService::merge([
	[
		'templateid' => '10001',
		'name' => 'Installed official',
		'technical_name' => 'Installed official',
		'uuid' => strtoupper($installedUuid),
		'vendor_name' => 'Zabbix',
		'vendor_version' => '7.0-1',
		'vendor_classification' => 'zabbix_vendor',
		'groups' => [],
		'host_count' => 0,
		'upstream_status' => 'official_match',
		'upstream' => ['uuid' => $installedUuid, 'vendor_version' => '7.0-1']
	]
], [
	'templates' => [
		$installedUuid => [
			'uuid' => $installedUuid,
			'name' => 'Installed official',
			'technical_name' => 'Installed official',
			'vendor_name' => 'Zabbix',
			'vendor_version' => '7.0-1'
		],
		$missingUuid => [
			'uuid' => $missingUuid,
			'name' => 'Missing official',
			'technical_name' => 'Missing official',
			'vendor_name' => 'Zabbix',
			'vendor_version' => '7.0-5'
		]
	]
]);

assertCatalogValue(2, count($result['templates']), 'Catalog merge must retain installed and add upstream-only templates.');
assertCatalogValue('installed', $result['templates'][0]['installation_status'], 'Local records must remain installed.');
assertCatalogValue('not_installed', $result['templates'][1]['installation_status'], 'Upstream-only record must be marked not installed.');
assertCatalogValue('', $result['templates'][1]['templateid'], 'Upstream-only record must never invent a local template ID.');
assertCatalogValue('official_catalog', $result['templates'][1]['upstream_status'], 'Upstream-only record must be identified as official catalog content.');
assertCatalogValue('7.0-5', $result['templates'][1]['upstream']['vendor_version'], 'Missing record must retain authoritative upstream metadata.');
assertCatalogValue([
	'local_visible' => 1,
	'official_installed' => 1,
	'official_catalog_total' => 2,
	'not_installed' => 1
], $result['summary'], 'Catalog summary is incorrect.');

echo "UpstreamCatalogService tests passed.\n";
