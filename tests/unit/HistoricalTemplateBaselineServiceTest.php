<?php

use Modules\ZabbixTemplateUpdateManager\Service\HistoricalTemplateBaselineService;

require_once dirname(__DIR__, 2).'/src/Service/UpstreamTemplateDocumentService.php';
require_once dirname(__DIR__, 2).'/src/Service/HistoricalTemplateBaselineService.php';

function assertBaseline($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$uuid = 'f8f7908280354f2abeed07dc788c3747';
$commits = [str_repeat('a', 40), str_repeat('b', 40), str_repeat('c', 40)];
$versions = [
	$commits[0] => '7.0-4',
	$commits[1] => '7.0-4',
	$commits[2] => '7.0-3'
];

$historyLoader = static fn(string $path, string $until, int $limit): array => [
	'commits' => array_map(static fn(string $id): array => ['id' => $id, 'message' => ''], $commits),
	'truncated' => false,
	'limit' => $limit
];
$sourceLoader = static function (string $commit, string $path) use ($uuid, $versions): array {
	$document = [
		'zabbix_export' => [
			'version' => '7.0',
			'template_groups' => [
				['uuid' => str_repeat('d', 32), 'name' => 'Templates/Operating systems']
			],
			'templates' => [[
				'uuid' => $uuid,
				'template' => 'Linux by Zabbix agent',
				'name' => 'Linux by Zabbix agent',
				'vendor' => ['name' => 'Zabbix', 'version' => $versions[$commit]],
				'groups' => [['name' => 'Templates/Operating systems']]
			]]
		]
	];
	return ['content' => json_encode($document), 'path' => $path, 'commit' => $commit];
};
$reader = static fn(string $source): array => json_decode($source, true);

$service = new HistoricalTemplateBaselineService($historyLoader, $sourceLoader, $reader);
$baseline = $service->find(
	'templates/os/linux/template_os_linux.yaml',
	str_repeat('f', 40),
	$uuid,
	'7.0-3'
);
assertBaseline('found', $baseline['status'], 'The requested historical vendor version must be found.');
assertBaseline($commits[2], $baseline['commit'], 'The matching historical commit must be returned.');
assertBaseline(3, $baseline['commits_examined'], 'The scan must report how many path commits were examined.');
assertBaseline(true, is_string($baseline['source']) && $baseline['source'] !== '', 'A found baseline must provide an isolated import source.');

$notFoundService = new HistoricalTemplateBaselineService(
	static fn(string $path, string $until, int $limit): array => [
		'commits' => [['id' => str_repeat('e', 40), 'message' => '']],
		'truncated' => true,
		'limit' => $limit
	],
	static function (string $commit, string $path) use ($uuid): array {
		return ['content' => json_encode([
			'zabbix_export' => [
				'version' => '7.0',
				'templates' => [[
					'uuid' => $uuid,
					'template' => 'Linux by Zabbix agent',
					'name' => 'Linux by Zabbix agent',
					'vendor' => ['name' => 'Zabbix', 'version' => '7.0-4']
				]]
			]
		]), 'path' => $path, 'commit' => $commit];
	},
	$reader
);
$notFound = $notFoundService->find('templates/os/linux/template_os_linux.yaml', str_repeat('f', 40), $uuid, '7.0-1');
assertBaseline('history_limit_reached', $notFound['status'], 'A truncated scan must not claim that a historical version does not exist.');

echo "HistoricalTemplateBaselineService tests passed.\n";
