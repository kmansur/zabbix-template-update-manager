<?php

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBackupVerificationService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateExportService;

require_once dirname(__DIR__, 2).'/src/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__, 2).'/src/Service/TemplateExportService.php';
require_once dirname(__DIR__, 2).'/src/Service/TemplateBackupVerificationService.php';

function assertBackupVerification($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function removeBackupVerificationTree(string $path): void {
	if (!is_dir($path)) {
		return;
	}
	foreach (scandir($path) ?: [] as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$child = $path.DIRECTORY_SEPARATOR.$entry;
		if (is_dir($child) && !is_link($child)) {
			removeBackupVerificationTree($child);
		}
		else {
			@unlink($child);
		}
	}
	@rmdir($path);
}

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-backup-verification-'.getmypid().'-'.bin2hex(random_bytes(4));
$repository = new TemplateBackupRepository($root, static fn(): int => 1789438800);
$template = [
	'templateid' => '12345',
	'uuid' => 'f8f7908280354f2abeed07dc788c3747',
	'technical_name' => 'Linux by Zabbix agent',
	'name' => 'Linux by Zabbix agent',
	'vendor_version' => '7.0-3'
];
$currentSource = "zabbix_export:\n  version: '7.0'\n  templates:\n    - uuid: f8f7908280354f2abeed07dc788c3747\n";
$exportArray = static fn(string $source): array => [
	'format' => 'yaml',
	'source' => $source,
	'bytes' => strlen($source),
	'sha256' => hash('sha256', $source)
];

try {
	$matchingExporter = new TemplateExportService(static fn(array $params): string => $currentSource);
	$verification = new TemplateBackupVerificationService($matchingExporter, $repository);

	$result = $verification->verifyCurrent($template);
	assertBackupVerification('no_backup', $result['status'], 'Missing backup must be reported explicitly.');
	assertBackupVerification(false, $result['current_match'], 'Missing backup cannot match current state.');

	$artifact = $repository->store($template, $exportArray($currentSource));
	$result = $verification->verifyCurrent($template);
	assertBackupVerification('current_match', $result['status'], 'Valid latest backup matching the fresh export must verify.');
	assertBackupVerification(true, $result['current_match'], 'Verified backup must expose current_match=true.');
	assertBackupVerification($artifact['sha256'], $result['latest']['sha256'] ?? null, 'Verification must report the latest artifact fingerprint.');
	assertBackupVerification($artifact['sha256'], $result['current_export']['sha256'] ?? null, 'Verification must report the fresh current export fingerprint.');

	$differentSource = $currentSource."# local change\n";
	$mismatchExporter = new TemplateExportService(static fn(array $params): string => $differentSource);
	$mismatchVerification = new TemplateBackupVerificationService($mismatchExporter, $repository);
	$result = $mismatchVerification->verifyCurrent($template);
	assertBackupVerification('current_mismatch', $result['status'], 'Changed current export must make the stored backup stale.');
	assertBackupVerification('current_export_fingerprint_mismatch', $result['reason'], 'Stale backup must report fingerprint mismatch.');

	@file_put_contents($artifact['source_path'], $currentSource."tampered\n");
	if (DIRECTORY_SEPARATOR === '/') {
		@chmod($artifact['source_path'], 0600);
	}
	$result = $verification->verifyCurrent($template);
	assertBackupVerification('latest_invalid', $result['status'], 'Tampered newest backup must fail closed instead of falling back to an older artifact.');
	assertBackupVerification(true, in_array($result['reason'], ['source_size_mismatch', 'source_hash_mismatch'], true), 'Tampered source must be rejected by size or hash integrity validation.');
}
finally {
	removeBackupVerificationTree($root);
}

echo "TemplateBackupVerificationService tests passed.\n";
