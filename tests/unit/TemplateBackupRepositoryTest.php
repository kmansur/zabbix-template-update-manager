<?php

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;

require_once dirname(__DIR__, 2).'/src/Repository/TemplateBackupRepository.php';

function assertTemplateBackup($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function removeTemplateBackupTree(string $path): void {
	if (!is_dir($path)) {
		return;
	}
	foreach (scandir($path) ?: [] as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$child = $path.DIRECTORY_SEPARATOR.$entry;
		if (is_dir($child)) {
			removeTemplateBackupTree($child);
		}
		else {
			@unlink($child);
		}
	}
	@rmdir($path);
}

assertTemplateBackup(
	'/var/lib/zabbix-template-update-manager/backups',
	TemplateBackupRepository::defaultBackupDirectory(),
	'Runtime backups must default to persistent storage, never the system temporary directory.'
);

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-backup-test-'.getmypid().'-'.bin2hex(random_bytes(4));
$clock = static fn(): int => 1789438800;
$repository = new TemplateBackupRepository($root, $clock);
$source = "zabbix_export:\n  version: '7.0'\n  templates: []\n";
$export = [
	'format' => 'yaml',
	'source' => $source,
	'bytes' => strlen($source),
	'sha256' => hash('sha256', $source)
];
$template = [
	'templateid' => '12345',
	'uuid' => 'f8f7908280354f2abeed07dc788c3747',
	'technical_name' => 'Linux by Zabbix agent',
	'name' => 'Linux by Zabbix agent',
	'vendor_version' => '7.0-3'
];

try {
	$artifact = $repository->store($template, $export);
	assertTemplateBackup('12345', $artifact['templateid'], 'Backup must preserve template ID metadata.');
	assertTemplateBackup($export['sha256'], $artifact['sha256'], 'Backup must preserve exact export SHA-256.');
	assertTemplateBackup(true, is_file($artifact['source_path']), 'Backup YAML artifact must exist.');
	assertTemplateBackup(true, is_file($artifact['manifest_path']), 'Backup manifest must exist.');
	assertTemplateBackup($source, file_get_contents($artifact['source_path']), 'Backup YAML must preserve export bytes exactly.');

	$manifest = json_decode((string) file_get_contents($artifact['manifest_path']), true);
	assertTemplateBackup('12345', $manifest['template']['templateid'] ?? null, 'Manifest must identify the template.');
	assertTemplateBackup($export['sha256'], $manifest['export']['sha256'] ?? null, 'Manifest must fingerprint the backup source.');
	assertTemplateBackup(basename($artifact['source_path']), $manifest['export']['file'] ?? null, 'Manifest must point to the local YAML artifact by basename only.');

	if (DIRECTORY_SEPARATOR === '/') {
		assertTemplateBackup(0600, fileperms($artifact['source_path']) & 0777, 'Backup YAML should be private mode 0600.');
		assertTemplateBackup(0600, fileperms($artifact['manifest_path']) & 0777, 'Backup manifest should be private mode 0600.');
	}

	$badExport = $export;
	$badExport['sha256'] = str_repeat('0', 64);
	$threw = false;
	try {
		$repository->store($template, $badExport);
	}
	catch (RuntimeException $exception) {
		$threw = true;
	}
	assertTemplateBackup(true, $threw, 'Mismatched backup source fingerprints must fail closed.');

	$badTemplate = $template;
	$badTemplate['templateid'] = '../12345';
	$threw = false;
	try {
		$repository->store($badTemplate, $export);
	}
	catch (RuntimeException $exception) {
		$threw = true;
	}
	assertTemplateBackup(true, $threw, 'Invalid template IDs must not influence backup paths.');
}
finally {
	removeTemplateBackupTree($root);
}

echo "TemplateBackupRepository tests passed.\n";
