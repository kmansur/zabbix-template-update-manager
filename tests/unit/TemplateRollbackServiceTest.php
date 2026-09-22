<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateRollbackService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateRollbackService.php';

function assertRollbackService($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$manifest = 'backup-20260915T030000Z-123456789abc.json';
$uuid = 'f8f7908280354f2abeed07dc788c3747';
$currentSha = hash('sha256', 'current');
$targetSha = hash('sha256', 'target');
$evidence = hash('sha256', 'rollback-evidence');
$template = [
	'templateid' => '12345',
	'uuid' => $uuid,
	'name' => 'Linux by Zabbix agent',
	'technical_name' => 'Linux by Zabbix agent',
	'vendor_version' => '7.0-8'
];
$targetDescriptor = [
	'templateid' => '12345',
	'uuid' => $uuid,
	'vendor_version' => '7.0-3',
	'format' => 'yaml',
	'created_at' => '2026-09-15T03:00:00+00:00',
	'bytes' => 6,
	'sha256' => $targetSha,
	'manifest_file' => $manifest,
	'source_file' => 'backup-20260915T030000Z-123456789abc.yaml'
];
$preflight = [
	'status' => 'ready',
	'write_enabled' => false,
	'evidence_sha256' => $evidence,
	'template' => $template,
	'current_export' => ['bytes' => 7, 'sha256' => $currentSha],
	'target' => $targetDescriptor
];
$artifact = $targetDescriptor + [
	'format' => 'yaml',
	'source' => 'target'
];
$recovery = [
	'created_at' => '2026-09-15T04:00:00+00:00',
	'bytes' => 7,
	'sha256' => $currentSha,
	'manifest_path' => '/private/backup-recovery.json',
	'source_path' => '/private/backup-recovery.yaml'
];

$imported = false;
$service = new TemplateRollbackService(
	static fn(string $templateId, string $manifestFile): array => $preflight,
	static fn(array $currentTemplate): array => $recovery,
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static function (array $target) use (&$imported): void {
		$imported = true;
	},
	static fn(string $templateId, array $target): array => [
		'status' => 'validated',
		'valid' => true,
		'expected_version' => '7.0-3',
		'installed_version' => '7.0-3',
		'remaining_changes' => 0,
		'reasons' => []
	]
);
$result = $service->execute('12345', $manifest, $evidence);
assertRollbackService('rolled_back', $result['status'], 'Matching fresh evidence should allow controlled rollback.');
assertRollbackService(true, $result['write_performed'], 'Successful rollback must report configuration write.');
assertRollbackService(true, $imported, 'Rollback importer must run after recovery backup and second preflight.');
assertRollbackService($currentSha, $result['recovery_backup']['sha256'], 'Rollback result must retain recovery backup fingerprint.');
assertRollbackService('yaml', $result['target']['format'], 'Rollback result must retain the selected artifact format evidence.');

$imported = false;
$result = $service->execute('12345', $manifest, hash('sha256', 'stale'));
assertRollbackService('blocked_evidence_changed', $result['status'], 'Stale rollback evidence must fail closed.');
assertRollbackService(false, $result['write_performed'], 'Stale rollback evidence must not write configuration.');
assertRollbackService(false, $imported, 'Importer must not run for stale rollback evidence.');

$changedRecovery = $recovery;
$changedRecovery['sha256'] = hash('sha256', 'changed-current');
$imported = false;
$changedService = new TemplateRollbackService(
	static fn(string $templateId, string $manifestFile): array => $preflight,
	static fn(array $currentTemplate): array => $changedRecovery,
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static function (array $target) use (&$imported): void {
		$imported = true;
	},
	static fn(string $templateId, array $target): array => ['valid' => true]
);
$result = $changedService->execute('12345', $manifest, $evidence);
assertRollbackService('blocked_current_changed', $result['status'], 'Recovery backup that differs from preflight current state must block import.');
assertRollbackService(false, $imported, 'Importer must not run when recovery backup exposes current-state drift.');

$validationFailure = new TemplateRollbackService(
	static fn(string $templateId, string $manifestFile): array => $preflight,
	static fn(array $currentTemplate): array => $recovery,
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static function (array $target): void {},
	static fn(string $templateId, array $target): array => [
		'status' => 'validation_failed',
		'valid' => false,
		'reasons' => ['remaining_import_differences']
	]
);
$result = $validationFailure->execute('12345', $manifest, $evidence);
assertRollbackService('validation_failed', $result['status'], 'Post-rollback validation failure must be explicit.');
assertRollbackService(true, $result['write_performed'], 'Validation failure happens after an acknowledged import write.');

$blockedPreflight = $preflight;
$blockedPreflight['status'] = 'already_restored';
$blockedService = new TemplateRollbackService(
	static fn(string $templateId, string $manifestFile): array => $blockedPreflight,
	static fn(array $currentTemplate): array => $recovery,
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static function (array $target): void {},
	static fn(string $templateId, array $target): array => ['valid' => true]
);
$result = $blockedService->execute('12345', $manifest, $evidence);
assertRollbackService('blocked_preflight', $result['status'], 'Non-ready rollback preflight must block write.');

echo "TemplateRollbackService tests passed.\n";
