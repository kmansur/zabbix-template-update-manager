<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdateCandidateService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateUpdateCandidateService.php';

function assertUpdateCandidate($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$commit = '0123456789abcdef0123456789abcdef01234567';
$path = 'templates/os/linux/template_os_linux.yaml';
$uuid = 'f8f7908280354f2abeed07dc788c3747';
$raw = "zabbix_export: fixture\n";
$document = [
	'zabbix_export' => [
		'version' => '7.0',
		'templates' => [[
			'uuid' => $uuid,
			'template' => 'Linux by Zabbix agent',
			'name' => 'Linux by Zabbix agent',
			'vendor' => [
				'name' => 'Zabbix',
				'version' => '7.0-8'
			]
		]]
	]
];

$preflight = [
	'status' => 'passed',
	'candidate' => [
		'commit' => $commit,
		'path' => $path,
		'uuid' => $uuid,
		'name' => 'Linux by Zabbix agent',
		'technical_name' => 'Linux by Zabbix agent',
		'vendor_name' => 'Zabbix',
		'vendor_version' => '7.0-8'
	]
];

$service = new TemplateUpdateCandidateService(
	static fn(string $requestedCommit, string $requestedPath): array => [
		'content' => $raw,
		'commit' => $requestedCommit,
		'path' => $requestedPath
	],
	static fn(string $source): array => $document
);

$result = $service->build($preflight);
assertUpdateCandidate($commit, $result['commit'], 'Candidate must preserve immutable commit.');
assertUpdateCandidate($path, $result['path'], 'Candidate must preserve validated path.');
assertUpdateCandidate($uuid, $result['uuid'], 'Candidate must preserve normalized UUID.');
assertUpdateCandidate('7.0-8', $result['vendor_version'], 'Candidate must preserve upstream vendor version.');
assertUpdateCandidate(hash('sha256', $raw), $result['canonical_sha256'], 'Candidate must fingerprint canonical source bytes.');
assertUpdateCandidate(true, is_string($result['source']) && $result['source'] !== '', 'Candidate must produce an isolated import source.');
assertUpdateCandidate(hash('sha256', $result['source']), $result['import_sha256'], 'Candidate must fingerprint isolated import source.');

$badPreflight = $preflight;
$badPreflight['candidate']['commit'] = 'release/7.0';
$threw = false;
try {
	$service->build($badPreflight);
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertUpdateCandidate(true, $threw, 'Mutable/non-immutable candidate refs must be rejected.');

$wrongFetch = new TemplateUpdateCandidateService(
	static fn(string $requestedCommit, string $requestedPath): array => [
		'content' => $raw,
		'commit' => str_repeat('a', 40),
		'path' => $requestedPath
	],
	static fn(string $source): array => $document
);
$threw = false;
try {
	$wrongFetch->build($preflight);
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertUpdateCandidate(true, $threw, 'Fetched source must match the preflight immutable commit exactly.');

echo "TemplateUpdateCandidateService tests passed.\n";
