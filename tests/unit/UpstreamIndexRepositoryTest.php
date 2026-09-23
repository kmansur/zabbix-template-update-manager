<?php

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;

require_once dirname(__DIR__, 2).'/src/Support/ZabbixVersion.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';

function assertUpstreamValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function assertUpstreamThrows(callable $callback, string $message): void {
	try {
		$callback();
	}
	catch (\RuntimeException $exception) {
		return;
	}

	fwrite(STDERR, $message."\n");
	exit(1);
}

assertUpstreamValue(
	'https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/upstream-index/initial/7.0.json',
	UpstreamIndexRepository::initialReleaseEndpointForVersion('7.0.31'),
	'Initial-release baseline endpoint must follow the detected major.minor line.'
);
assertUpstreamValue(
	null,
	UpstreamIndexRepository::initialReleaseEndpointForVersion('invalid'),
	'Unsupported Zabbix versions must not produce an initial-release baseline endpoint.'
);

$uuid = 'f8f7908280354f2abeed07dc788c3747';
$valid = json_encode([
	'schema_version' => 1,
	'source' => [
		'line' => '7.0',
		'ref' => 'release/7.0',
		'commit' => str_repeat('a', 40)
	],
	'statistics' => [
		'templates' => 1,
		'duplicate_uuid_definitions' => 0,
		'content_variant_uuids' => 0
	],
	'templates' => [
		$uuid => [
			'uuid' => $uuid,
			'name' => 'Linux by Zabbix agent',
			'technical_name' => 'Linux by Zabbix agent',
			'vendor_name' => 'Zabbix',
			'vendor_version' => '7.0-4',
			'paths' => ['templates/os/linux/template_os_linux.yaml'],
			'content_sha256s' => [str_repeat('b', 64)],
			'sources' => [[
				'path' => 'templates/os/linux/template_os_linux.yaml',
				'sha256' => str_repeat('c', 64)
			]]
		]
	]
], JSON_UNESCAPED_SLASHES);

$decoded = UpstreamIndexRepository::decodeIndex($valid, '7.0');
assertUpstreamValue('release/7.0', $decoded['source']['ref'], 'Valid index source ref was not preserved.');
assertUpstreamValue('7.0-4', $decoded['templates'][$uuid]['vendor_version'], 'Valid template record was not preserved.');
assertUpstreamValue(1, count($decoded['templates'][$uuid]['paths']), 'Valid template source paths were not preserved.');

$officialPlusPath = 'templates/net/mikrotik/mikrotik_CRS305-1G-4S+IN_snmp/template_net_mikrotik_CRS305-1G-4S+IN_snmp.yaml';
assertUpstreamValue(
	true,
	UpstreamIndexRepository::isValidTemplatePath($officialPlusPath),
	'An official Zabbix source path containing a literal plus sign must be accepted.'
);

$validPlus = json_decode($valid, true);
$validPlus['templates'][$uuid]['paths'] = [$officialPlusPath];
$validPlus['templates'][$uuid]['sources'][0]['path'] = $officialPlusPath;
$decodedPlus = UpstreamIndexRepository::decodeIndex(json_encode($validPlus, JSON_UNESCAPED_SLASHES), '7.0');
assertUpstreamValue(
	$officialPlusPath,
	$decodedPlus['templates'][$uuid]['paths'][0],
	'The official plus-sign path must survive index validation.'
);

assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex($valid, '8.0'),
	'An index for the wrong Zabbix line must be rejected.'
);

$invalidUuid = json_decode($valid, true);
$invalidUuid['templates'] = [
	'invalid' => ['uuid' => 'invalid']
];
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($invalidUuid), '7.0'),
	'An invalid UUID key must be rejected.'
);

$invalidPath = json_decode($valid, true);
$invalidPath['templates'][$uuid]['paths'] = ['../outside.yaml'];
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($invalidPath), '7.0'),
	'An invalid source path must be rejected.'
);

$traversalWithPlus = json_decode($valid, true);
$traversalWithPlus['templates'][$uuid]['paths'] = ['templates/net/device+name/../outside.yaml'];
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($traversalWithPlus), '7.0'),
	'Allowing plus signs must not weaken traversal protection.'
);

$invalidHash = json_decode($valid, true);
$invalidHash['templates'][$uuid]['content_sha256s'] = ['invalid'];
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($invalidHash), '7.0'),
	'An invalid content hash must be rejected.'
);

$invalidSourceHash = json_decode($valid, true);
$invalidSourceHash['templates'][$uuid]['sources'][0]['sha256'] = 'invalid';
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($invalidSourceHash), '7.0'),
	'An invalid raw source hash must be rejected.'
);

$mismatchedSourcePath = json_decode($valid, true);
$mismatchedSourcePath['templates'][$uuid]['sources'][0]['path'] = 'templates/os/linux/other.yaml';
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($mismatchedSourcePath), '7.0'),
	'Raw source fingerprints must cover exactly the declared source paths.'
);

$legacy = json_decode($valid, true);
unset($legacy['templates'][$uuid]['sources']);
$decodedLegacy = UpstreamIndexRepository::decodeIndex(json_encode($legacy, JSON_UNESCAPED_SLASHES), '7.0');
assertUpstreamValue(
	1,
	count($decodedLegacy['templates'][$uuid]['paths']),
	'Legacy indexes without raw source fingerprints must remain readable for non-write analysis.'
);

echo "UpstreamIndexRepository tests passed.\n";
