<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateRollbackPreflightService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateRollbackPreflightService.php';

function assertRollbackPreflight($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$uuid = 'f8f7908280354f2abeed07dc788c3747';
$manifest = 'backup-20260915T030000Z-123456789abc.json';
$targetSource = "zabbix_export:\n  version: '7.0'\n";
$artifact = [
	'templateid' => '12345',
	'uuid' => $uuid,
	'name' => 'Linux by Zabbix agent',
	'technical_name' => 'Linux by Zabbix agent',
	'vendor_version' => '7.0-3',
	'created_at' => '2026-09-15T03:00:00+00:00',
	'bytes' => strlen($targetSource),
	'sha256' => hash('sha256', $targetSource),
	'manifest_file' => $manifest,
	'source_file' => 'backup-20260915T030000Z-123456789abc.yaml',
	'format' => 'yaml',
	'source' => $targetSource
];
$template = [
	'templateid' => '12345',
	'uuid' => $uuid,
	'name' => 'Linux by Zabbix agent',
	'technical_name' => 'Linux by Zabbix agent',
	'vendor_version' => '7.0-8'
];
$currentSource = "zabbix_export:\n  version: '7.0'\n  current: true\n";
$currentExport = [
	'format' => 'yaml',
	'source' => $currentSource,
	'bytes' => strlen($currentSource),
	'sha256' => hash('sha256', $currentSource)
];
$diff = [
	'templates' => [
		'updated' => [[
			'before' => ['vendor' => ['version' => '7.0-8']],
			'after' => ['vendor' => ['version' => '7.0-3']]
		]]
	]
];

$observedFormat = null;
$service = new TemplateRollbackPreflightService(
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static fn(string $templateId): array => $template,
	static fn(string $templateId): array => $currentExport,
	static function (string $source, string $format) use ($diff, &$observedFormat): array {
		$observedFormat = $format;
		return $diff;
	}
);
$result = $service->run('12345', $manifest);
assertRollbackPreflight('ready', $result['status'], 'A valid differing artifact should be ready for explicit rollback confirmation.');
assertRollbackPreflight(false, $result['write_enabled'], 'Rollback preflight must never write configuration.');
assertRollbackPreflight(1, $result['comparison_summary']['total'], 'Rollback preflight must summarize import differences.');
assertRollbackPreflight('yaml', $observedFormat, 'Rollback preflight must compare stored backup bytes using their YAML format.');
assertRollbackPreflight('yaml', $result['target']['format'], 'Rollback target evidence must retain the artifact format.');
assertRollbackPreflight(true, is_string($result['evidence_sha256']) && strlen($result['evidence_sha256']) === 64, 'Rollback preflight must emit evidence fingerprint.');
$resultAgain = $service->run('12345', $manifest);
assertRollbackPreflight($result['evidence_sha256'], $resultAgain['evidence_sha256'], 'Unchanged rollback evidence must be deterministic.');

$already = new TemplateRollbackPreflightService(
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static fn(string $templateId): array => $template,
	static fn(string $templateId): array => $currentExport,
	static fn(string $source, string $format): array => []
);
$result = $already->run('12345', $manifest);
assertRollbackPreflight('already_restored', $result['status'], 'No import differences should suppress rollback.');

$wrongTemplate = $template;
$wrongTemplate['uuid'] = str_repeat('a', 32);
$blocked = new TemplateRollbackPreflightService(
	static fn(string $templateId, string $manifestFile): array => $artifact,
	static fn(string $templateId): array => $wrongTemplate,
	static fn(string $templateId): array => $currentExport,
	static fn(string $source, string $format): array => $diff
);
$result = $blocked->run('12345', $manifest);
assertRollbackPreflight('blocked_identity', $result['status'], 'Rollback must fail closed when UUID identity differs.');
assertRollbackPreflight(null, $result['evidence_sha256'], 'Blocked identity must not produce reusable rollback evidence.');

echo "TemplateRollbackPreflightService tests passed.\n";
