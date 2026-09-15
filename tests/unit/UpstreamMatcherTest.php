<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpstreamMatcher;

require_once dirname(__DIR__, 2).'/src/Service/UpstreamMatcher.php';

function assertMatcherValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$officialUuid = 'f8f7908280354f2abeed07dc788c3747';
$index = [
	'templates' => [
		$officialUuid => [
			'uuid' => $officialUuid,
			'name' => 'Linux by Zabbix agent',
			'technical_name' => 'Linux by Zabbix agent',
			'vendor_name' => 'Zabbix',
			'vendor_version' => '7.0-4',
			'path' => 'templates/os/linux/template_os_linux.yaml'
		]
	]
];

$result = UpstreamMatcher::attach([
	['uuid' => strtoupper($officialUuid), 'name' => 'Linux'],
	['uuid' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'name' => 'Custom'],
	['uuid' => '', 'name' => 'Legacy'],
	['uuid' => 'not-a-uuid', 'name' => 'Broken']
], $index);

assertMatcherValue('official_match', $result['templates'][0]['upstream_status'], 'Official UUID must match case-insensitively.');
assertMatcherValue('7.0-4', $result['templates'][0]['upstream']['vendor_version'], 'Matched upstream record must be attached.');
assertMatcherValue('not_found', $result['templates'][1]['upstream_status'], 'Unknown UUID must not match upstream.');
assertMatcherValue('no_uuid', $result['templates'][2]['upstream_status'], 'Empty UUID must be classified explicitly.');
assertMatcherValue('invalid_uuid', $result['templates'][3]['upstream_status'], 'Malformed UUID must be classified explicitly.');
assertMatcherValue([
	'official_match' => 1,
	'not_found' => 1,
	'no_uuid' => 1,
	'invalid_uuid' => 1,
	'repository_unavailable' => 0
], $result['summary'], 'Upstream summary is incorrect.');

$offline = UpstreamMatcher::attach([
	['uuid' => $officialUuid, 'name' => 'Linux'],
	['uuid' => '', 'name' => 'Legacy']
], null);
assertMatcherValue(2, $offline['summary']['repository_unavailable'], 'Unavailable repository must not produce identity assumptions.');

echo "UpstreamMatcher tests passed.\n";
