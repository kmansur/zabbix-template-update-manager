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
			'content_sha256s' => [str_repeat('b', 64)]
		]
	]
], JSON_UNESCAPED_SLASHES);

$decoded = UpstreamIndexRepository::decodeIndex($valid, '7.0');
assertUpstreamValue('release/7.0', $decoded['source']['ref'], 'Valid index source ref was not preserved.');
assertUpstreamValue('7.0-4', $decoded['templates'][$uuid]['vendor_version'], 'Valid template record was not preserved.');
assertUpstreamValue(1, count($decoded['templates'][$uuid]['paths']), 'Valid template source paths were not preserved.');

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

$invalidHash = json_decode($valid, true);
$invalidHash['templates'][$uuid]['content_sha256s'] = ['invalid'];
assertUpstreamThrows(
	fn() => UpstreamIndexRepository::decodeIndex(json_encode($invalidHash), '7.0'),
	'An invalid content hash must be rejected.'
);

echo "UpstreamIndexRepository tests passed.\n";
