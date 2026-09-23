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
assertBaseline(1, $baseline['distinct_candidate_count'], 'One distinct official body must be deterministic.');
assertBaseline(true, is_string($baseline['source']) && $baseline['source'] !== '', 'A found baseline must provide an isolated import source.');

// vendor.version alone is not source provenance. If several different official
// bodies carry the same version, select only a candidate that is an exact
// semantic match for LOCAL according to the injected evaluator.
$sameVersionCommits = [str_repeat('1', 40), str_repeat('2', 40), str_repeat('3', 40)];
$descriptions = [
	$sameVersionCommits[0] => 'newer revision, same vendor version',
	$sameVersionCommits[1] => 'installed official revision',
	$sameVersionCommits[2] => 'older vendor version'
];
$vendorVersions = [
	$sameVersionCommits[0] => '7.0-0',
	$sameVersionCommits[1] => '7.0-0',
	$sameVersionCommits[2] => '6.4-9'
];
$sameVersionHistory = static fn(string $path, string $until, int $limit): array => [
	'commits' => array_map(static fn(string $id): array => ['id' => $id, 'message' => ''], $sameVersionCommits),
	'truncated' => false,
	'limit' => $limit
];
$sameVersionSource = static function (string $commit, string $path) use ($uuid, $descriptions, $vendorVersions): array {
	return [
		'content' => json_encode([
			'zabbix_export' => [
				'version' => '7.0',
				'templates' => [[
					'uuid' => $uuid,
					'template' => 'T',
					'name' => 'T',
					'description' => $descriptions[$commit],
					'vendor' => ['name' => 'Zabbix', 'version' => $vendorVersions[$commit]]
				]]
			]
		]),
		'path' => $path,
		'commit' => $commit
	];
};
$sameVersionService = new HistoricalTemplateBaselineService($sameVersionHistory, $sameVersionSource, $reader);
$exact = $sameVersionService->find(
	'templates/test/template_test.yaml',
	str_repeat('f', 40),
	$uuid,
	'7.0-0',
	'Zabbix',
	75,
	static function (string $source, string $commit) use ($sameVersionCommits): int {
		return $commit === $sameVersionCommits[1] ? 0 : 9;
	}
);
assertBaseline('found', $exact['status'], 'An exact semantic LOCAL match must prove the historical baseline.');
assertBaseline($sameVersionCommits[1], $exact['commit'], 'The exact matching revision must be selected, not merely the newest same-version commit.');
assertBaseline(2, $exact['candidate_count'], 'Both commits carrying the installed vendor version must be counted.');
assertBaseline(2, $exact['distinct_candidate_count'], 'Distinct same-version official bodies must remain distinguishable.');
assertBaseline(1, $exact['exact_match_count'], 'Exactly one semantic LOCAL match must be reported.');
assertBaseline('exact_local_match', $exact['selection'], 'Selection provenance must explain why the baseline is authoritative.');

$ambiguous = $sameVersionService->find(
	'templates/test/template_test.yaml',
	str_repeat('f', 40),
	$uuid,
	'7.0-0',
	'Zabbix',
	75,
	static fn(string $source, string $commit): int => str_starts_with($commit, '1') ? 4 : 7
);
assertBaseline('ambiguous', $ambiguous['status'], 'Multiple same-version official bodies without an exact LOCAL match must fail closed.');
assertBaseline('', $ambiguous['commit'], 'An ambiguous baseline must not claim an authoritative commit.');
assertBaseline(2, $ambiguous['distinct_candidate_count'], 'Ambiguous result must expose the number of distinct candidates.');
assertBaseline(4, $ambiguous['closest_changes'], 'Closest semantic distance may be shown diagnostically without becoming authoritative.');
assertBaseline(null, $ambiguous['source'], 'An ambiguous baseline must not feed a guessed source into three-way analysis.');

$renameCommits = [str_repeat('4', 40), str_repeat('5', 40)];
$renameNewPath = 'templates/cloud/AWS/aws_http/template_cloud_aws_http.yaml';
$renameOldPath = 'templates/cloud/aws/template_aws_http.yaml';
$renameCalls = [];
$renameHistory = static fn(string $path, string $until, int $limit): array => [
	'commits' => array_map(static fn(string $id): array => ['id' => $id, 'message' => ''], $renameCommits),
	'truncated' => false,
	'limit' => $limit
];
$renameSource = static function (string $commit, string $path) use (
	&$renameCalls,
	$renameCommits,
	$renameNewPath,
	$renameOldPath,
	$uuid
): array {
	$renameCalls[] = [$commit, $path];

	if ($commit === $renameCommits[1] && $path === $renameNewPath) {
		throw new RuntimeException('Current path does not exist before rename.');
	}

	$version = $commit === $renameCommits[0] ? '7.0-4' : '7.0-3';
	return [
		'content' => json_encode([
			'zabbix_export' => [
				'version' => '7.0',
				'templates' => [[
					'uuid' => $uuid,
					'template' => 'AWS Cost Explorer by HTTP',
					'name' => 'AWS Cost Explorer by HTTP',
					'vendor' => ['name' => 'Zabbix', 'version' => $version]
				]]
			]
		]),
		'path' => $path,
		'commit' => $commit
	];
};
$renameResolver = static function (string $commit, string $path) use (
	$renameCommits,
	$renameNewPath,
	$renameOldPath
): ?string {
	return $commit === $renameCommits[0] && $path === $renameNewPath
		? $renameOldPath
		: null;
};
$renameService = new HistoricalTemplateBaselineService(
	$renameHistory,
	$renameSource,
	$reader,
	$renameResolver
);
$renamedBaseline = $renameService->find(
	$renameNewPath,
	str_repeat('f', 40),
	$uuid,
	'7.0-3'
);
assertBaseline('found', $renamedBaseline['status'],
	'Historical baseline lookup must continue across an official source-path rename.');
assertBaseline($renameCommits[1], $renamedBaseline['commit'],
	'Rename-aware lookup must return the older commit containing the installed vendor version.');
assertBaseline(
	true,
	in_array([$renameCommits[1], $renameOldPath], $renameCalls, true),
	'Historical source lookup must retry the older revision using the path from before the rename.'
);

$budgetService = new HistoricalTemplateBaselineService(
	static function (string $path, string $until, int $limit): array {
		usleep(3000);
		return [
			'commits' => [['id' => str_repeat('9', 40), 'message' => '']],
			'truncated' => false,
			'limit' => $limit
		];
	},
	static function (string $commit, string $path): array {
		throw new RuntimeException('Source loader must not run after the request budget is already exhausted.');
	},
	$reader
);
$budgetReached = $budgetService->find(
	'templates/test/template_test.yaml',
	str_repeat('f', 40),
	$uuid,
	'7.0-0',
	'Zabbix',
	75,
	null,
	false,
	0.001
);
assertBaseline('time_budget_reached', $budgetReached['status'],
	'Historical lookup must return a retryable time-budget state before starting another expensive revision fetch.');
assertBaseline('continue_request_bounded_scan', $budgetReached['selection'],
	'Time-budget state must explicitly identify request-bounded continuation.');

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
