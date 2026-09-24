<?php

use Modules\ZabbixTemplateUpdateManager\Repository\HistoricalBaselineCacheRepository;

require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/HistoricalBaselineCacheRepository.php';

function assertBaselineCache($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function removeBaselineCacheTree(string $path): void {
	if (!is_dir($path)) {
		return;
	}
	foreach (scandir($path) ?: [] as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$file = $path.DIRECTORY_SEPARATOR.$entry;
		if (is_dir($file)) {
			removeBaselineCacheTree($file);
		}
		else {
			@unlink($file);
		}
	}
	@rmdir($path);
}

$cacheDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-cache-test-'.getmypid().'-'.bin2hex(random_bytes(4));
$repository = new HistoricalBaselineCacheRepository($cacheDir);
$path = 'templates/os/linux/template_os_linux.yaml';
$currentCommit = str_repeat('a', 40);
$uuid = 'f8f7908280354f2abeed07dc788c3747';
$version = '7.0-3';
$vendor = 'Zabbix';
$baseline = [
	'status' => 'found',
	'commit' => str_repeat('b', 40),
	'path' => $path,
	'vendor_version' => $version,
	'commits_examined' => 4,
	'history_truncated' => false,
	'source' => '{"zabbix_export":{"version":"7.0","templates":[]}}'
];

try {
	assertBaselineCache(null, $repository->load($path, $currentCommit, $uuid, $version, $vendor), 'Empty cache must miss.');
	assertBaselineCache(true, $repository->store($path, $currentCommit, $uuid, $version, $vendor, $baseline), 'Valid baseline must be cached.');

	$loaded = $repository->load($path, $currentCommit, $uuid, $version, $vendor);
	assertBaselineCache('found', $loaded['status'] ?? null, 'Cached baseline status must round-trip.');
	assertBaselineCache($baseline['commit'], $loaded['commit'] ?? null, 'Cached baseline commit must round-trip.');
	assertBaselineCache($baseline['source'], $loaded['source'] ?? null, 'Cached baseline source must round-trip.');
	assertBaselineCache(4, $loaded['commits_examined'] ?? null, 'Cached examination count must round-trip.');

	assertBaselineCache(
		null,
		$repository->load($path, str_repeat('c', 40), $uuid, $version, $vendor),
		'A different current upstream commit must select a different cache identity.'
	);
	assertBaselineCache(
		null,
		$repository->load($path, $currentCommit, $uuid, '7.0-2', $vendor),
		'A different installed vendor version must select a different cache identity.'
	);

	$notFound = $baseline;
	$notFound['status'] = 'not_found';
	assertBaselineCache(false, $repository->store($path, $currentCommit, $uuid, $version, $vendor, $notFound), 'Negative baseline results must not be cached.');

	$files = glob($cacheDir.DIRECTORY_SEPARATOR.'baseline-*.json') ?: [];
	assertBaselineCache(1, count($files), 'Exactly one successful baseline record should exist.');

	$record = json_decode((string) file_get_contents($files[0]), true);
	$record['baseline']['source'] = 'tampered';
	file_put_contents($files[0], json_encode($record));
	assertBaselineCache(
		null,
		$repository->load($path, $currentCommit, $uuid, $version, $vendor),
		'A cache record with a mismatched source fingerprint must be ignored.'
	);

	$threw = false;
	try {
		$repository->load('../template.yaml', $currentCommit, $uuid, $version, $vendor);
	}
	catch (RuntimeException $exception) {
		$threw = true;
	}
	assertBaselineCache(true, $threw, 'Invalid template paths must fail closed.');
}
finally {
	removeBaselineCacheTree($cacheDir);
}

echo "HistoricalBaselineCacheRepository tests passed.\n";
