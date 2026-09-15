<?php

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateRollbackArtifactService;

require_once dirname(__DIR__, 2).'/src/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__, 2).'/src/Service/TemplateRollbackArtifactService.php';

function assertRollbackArtifact($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-rollback-artifact-'.bin2hex(random_bytes(4));
$clock = static fn(): int => 1789441200;
$repository = new TemplateBackupRepository($root, $clock);
$template = [
	'templateid' => '12345',
	'uuid' => 'f8f7908280354f2abeed07dc788c3747',
	'technical_name' => 'Linux by Zabbix agent',
	'name' => 'Linux by Zabbix agent',
	'vendor_version' => '7.0-3'
];
$source = "zabbix_export:\n  version: '7.0'\n";
$stored = $repository->store($template, [
	'format' => 'yaml',
	'source' => $source,
	'bytes' => strlen($source),
	'sha256' => hash('sha256', $source)
]);
$manifest = basename($stored['manifest_path']);

$service = new TemplateRollbackArtifactService($repository);
$loaded = $service->load('12345', $manifest);
assertRollbackArtifact($source, $loaded['source'], 'Rollback artifact loader must return exact validated YAML bytes.');
assertRollbackArtifact(hash('sha256', $source), $loaded['sha256'], 'Rollback artifact loader must preserve validated SHA-256.');
assertRollbackArtifact($manifest, $loaded['manifest_file'], 'Rollback artifact loader must preserve explicit manifest selection.');

file_put_contents($stored['source_path'], $source."tamper\n");
$threw = false;
try {
	$service->load('12345', $manifest);
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertRollbackArtifact(true, $threw, 'Tampered rollback YAML must fail closed.');

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $entry) {
	$entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
}
@rmdir($root);

echo "TemplateRollbackArtifactService tests passed.\n";
