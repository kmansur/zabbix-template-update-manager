<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateVersionComparator;

require_once dirname(__DIR__, 2).'/src/Service/TemplateVersionComparator.php';

function assertVersionValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function officialTemplate(string $installed, string $upstream): array {
	return [
		'uuid' => str_repeat('a', 32),
		'vendor_version' => $installed,
		'upstream_status' => 'official_match',
		'upstream' => ['vendor_version' => $upstream]
	];
}

$result = TemplateVersionComparator::attach([
	officialTemplate('7.0-4', '7.0-4'),
	officialTemplate('7.0-4', '7.0-10'),
	officialTemplate('7.0-12', '7.0-10'),
	officialTemplate('6.0-99', '7.0-1'),
	officialTemplate('', '7.0-4'),
	officialTemplate('custom', '7.0-4'),
	[
		'uuid' => str_repeat('b', 32),
		'vendor_version' => '1.0-1',
		'upstream_status' => 'not_found',
		'upstream' => null
	],
	[
		'uuid' => str_repeat('c', 32),
		'vendor_version' => '',
		'installation_status' => 'not_installed',
		'upstream_status' => 'official_catalog',
		'upstream' => ['vendor_version' => '7.0-3']
	]
]);

assertVersionValue('current', $result['templates'][0]['version_status'], 'Equal official versions must be current.');
assertVersionValue('update_available', $result['templates'][1]['version_status'], 'Numeric revisions must not be compared lexically.');
assertVersionValue('installed_newer', $result['templates'][2]['version_status'], 'A newer installed revision must be explicit.');
assertVersionValue('update_available', $result['templates'][3]['version_status'], 'A newer upstream release line must be considered newer.');
assertVersionValue('installed_version_missing', $result['templates'][4]['version_status'], 'Missing installed vendor version must fail closed.');
assertVersionValue('version_uncomparable', $result['templates'][5]['version_status'], 'Unexpected version formats must not be guessed.');
assertVersionValue('not_applicable', $result['templates'][6]['version_status'], 'Non-official templates must not receive version assumptions.');
assertVersionValue('not_installed', $result['templates'][7]['version_status'], 'Upstream-only catalog templates must be explicitly not installed.');
assertVersionValue('7.0-3', $result['templates'][7]['upstream_vendor_version'], 'Catalog entries must expose the available official version.');
assertVersionValue('7.0-10', $result['templates'][1]['upstream_vendor_version'], 'The upstream vendor version must be exposed.');
assertVersionValue([
	'current' => 1,
	'update_available' => 2,
	'installed_newer' => 1,
	'installed_version_missing' => 1,
	'upstream_version_missing' => 0,
	'version_uncomparable' => 1,
	'not_applicable' => 1,
	'not_installed' => 1
], $result['summary'], 'Version comparison summary is incorrect.');

echo "TemplateVersionComparator tests passed.\n";
