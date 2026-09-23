<?php

use Modules\ZabbixTemplateUpdateManager\Repository\OfflineBundleRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;

require_once dirname(__DIR__, 2).'/src/Support/ZabbixVersion.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/OfflineBundleRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamTemplateSourceRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamTemplateHistoryRepository.php';

function assertOffline($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-offline-test-'.bin2hex(random_bytes(6));
$commit = str_repeat('a', 40);
$path = 'templates/os/linux/template_os_linux.yaml';
$uuid = 'f8f7908280354f2abeed07dc788c3747';
$source = "zabbix_export:\n  version: '7.0'\n";
$sourceSha = hash('sha256', $source);

$index = json_encode([
	'schema_version' => 1,
	'source' => ['line' => '7.0', 'ref' => 'release/7.0', 'commit' => $commit],
	'templates' => [
		$uuid => [
			'uuid' => $uuid,
			'name' => 'Linux by Zabbix agent',
			'technical_name' => 'Linux by Zabbix agent',
			'vendor_name' => 'Zabbix',
			'vendor_version' => '7.0-1',
			'paths' => [$path],
			'content_sha256s' => [str_repeat('b', 64)],
			'sources' => [['path' => $path, 'sha256' => $sourceSha]]
		]
	]
], JSON_UNESCAPED_SLASHES);

$history = json_encode([
	'schema_version' => 1,
	'path' => $path,
	'until' => $commit,
	'truncated' => false,
	'commits' => [['id' => $commit, 'message' => 'test']]
], JSON_UNESCAPED_SLASHES);

$files = [
	'indexes/7.0.json' => $index,
	'sources/'.$commit.'/'.$path => $source,
	'history/'.$commit.'/'.hash('sha256', $path).'.json' => $history
];

foreach ($files as $relative => $content) {
	$file = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
	@mkdir(dirname($file), 0700, true);
	file_put_contents($file, $content);
}

$manifest = [
	'schema_version' => 1,
	'generated_at' => gmdate('c'),
	'files' => []
];
foreach ($files as $relative => $content) {
	$manifest['files'][$relative] = hash('sha256', $content);
}
@mkdir($root, 0700, true);
file_put_contents($root.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES));

$bundle = new OfflineBundleRepository($root, true);
assertOffline(true, $bundle->isConfigured(), 'Offline bundle must report configured state.');
assertOffline(true, $bundle->isOfflineOnly(), 'Offline bundle must preserve offline-only mode.');
assertOffline($source, $bundle->readSource($commit, $path), 'Offline source must be returned after manifest verification.');
assertOffline($commit, $bundle->readHistory($path, $commit, 75)['commits'][0]['id'],
	'Offline history must be normalized.');

$indexRepo = new UpstreamIndexRepository(
	sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-offline-cache-'.bin2hex(random_bytes(4)),
	$bundle
);
$loaded = $indexRepo->load('7.0.0');
assertOffline('offline', $loaded['runtime']['cache_status'], 'Offline index must take precedence over network/cache.');
assertOffline('7.0-1', $loaded['templates'][$uuid]['vendor_version'], 'Offline index record must remain intact.');

$sourceRepo = new UpstreamTemplateSourceRepository($bundle);
$fetched = $sourceRepo->fetchAtCommit($commit, $path);
assertOffline($source, $fetched['content'], 'Source repository must use verified offline bytes.');
assertOffline(true, str_starts_with($fetched['url'], 'offline-bundle://'), 'Offline source must be explicitly identified.');

$historyRepo = new UpstreamTemplateHistoryRepository($bundle);
$list = $historyRepo->listCommits($path, $commit, 75);
assertOffline(1, count($list['commits']), 'History repository must use verified offline history.');

file_put_contents(
	$root.DIRECTORY_SEPARATOR.'sources'.DIRECTORY_SEPARATOR.$commit.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path),
	$source."tampered"
);
$rejected = false;
try {
	(new OfflineBundleRepository($root, true))->readSource($commit, $path);
}
catch (RuntimeException $exception) {
	$rejected = str_contains($exception->getMessage(), 'integrity verification failed');
}
assertOffline(true, $rejected, 'Offline bundle tampering must fail closed.');

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
	$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
}
@rmdir($root);

echo "OfflineBundleRepository tests passed.\n";
