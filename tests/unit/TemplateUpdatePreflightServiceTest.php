<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdatePreflightService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateUpdatePreflightService.php';

function assertPreflight($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$backupSha = hash('sha256', 'current-template-export');
$analysis = [
	'template' => [
		'templateid' => '12345',
		'uuid' => 'f8f79082-8035-4f2a-beed-07dc788c3747',
		'name' => 'Linux by Zabbix agent',
		'technical_name' => 'Linux by Zabbix agent',
		'vendor_version' => '7.0-3',
		'upstream_vendor_version' => '7.0-8',
		'host_count' => 37
	],
	'comparison_error' => null,
	'upstream_source' => [
		'commit' => '0123456789abcdef0123456789abcdef01234567'
	],
	'source_path' => 'templates/os/linux/template_os_linux.yaml',
	'update_readiness' => [
		'status' => 'backup_verified',
		'write_enabled' => false
	],
	'backup_verification' => [
		'status' => 'current_match',
		'current_match' => true,
		'latest' => [
			'created_at' => '2026-09-15T03:00:00+00:00',
			'bytes' => 1234,
			'sha256' => $backupSha
		],
		'current_export' => [
			'bytes' => 1234,
			'sha256' => $backupSha
		]
	]
];

$service = new TemplateUpdatePreflightService(static fn(string $templateId): array => $analysis);
$result = $service->run('12345');

assertPreflight('passed', $result['status'], 'Fresh backup_verified analysis should pass the read-only preflight.');
assertPreflight(false, $result['write_enabled'], 'Passing preflight must never enable Zabbix configuration writes.');
assertPreflight('await_write_enabled_milestone', $result['next_step'], 'Passing preflight must stop before any write milestone.');
assertPreflight('12345', $result['template']['templateid'], 'Preflight must preserve template identity.');
assertPreflight('f8f7908280354f2abeed07dc788c3747', $result['template']['uuid'], 'Preflight must normalize the template UUID.');
assertPreflight('0123456789abcdef0123456789abcdef01234567', $result['candidate']['commit'], 'Preflight must bind the exact upstream commit.');
assertPreflight('templates/os/linux/template_os_linux.yaml', $result['candidate']['path'], 'Preflight must bind the exact upstream path.');
assertPreflight($backupSha, $result['rollback']['sha256'], 'Preflight must bind the verified rollback fingerprint.');
assertPreflight($backupSha, $result['rollback']['current_export_sha256'], 'Rollback fingerprint must match the fresh current export.');
assertPreflight(37, $result['direct_host_count'], 'Direct host count must remain impact context.');
assertPreflight(true, is_string($result['evidence_sha256']) && strlen($result['evidence_sha256']) === 64, 'Passing preflight must emit a deterministic evidence fingerprint.');

$resultAgain = $service->run('12345');
assertPreflight($result['evidence_sha256'], $resultAgain['evidence_sha256'], 'Unchanged authoritative evidence must produce the same fingerprint.');

$notReady = $analysis;
$notReady['update_readiness']['status'] = 'candidate_for_backup';
$result = (new TemplateUpdatePreflightService(static fn(string $templateId): array => $notReady))->run('12345');
assertPreflight('blocked_readiness', $result['status'], 'Anything below backup_verified must remain blocked.');
assertPreflight(false, $result['write_enabled'], 'Blocked readiness must not enable writes.');

$badCandidate = $analysis;
$badCandidate['upstream_source']['commit'] = 'release/7.0';
$result = (new TemplateUpdatePreflightService(static fn(string $templateId): array => $badCandidate))->run('12345');
assertPreflight('blocked_candidate', $result['status'], 'A non-immutable upstream identity must fail closed.');

$staleBackup = $analysis;
$staleBackup['backup_verification']['current_export']['sha256'] = hash('sha256', 'changed-template-export');
$result = (new TemplateUpdatePreflightService(static fn(string $templateId): array => $staleBackup))->run('12345');
assertPreflight('blocked_backup', $result['status'], 'Rollback evidence that no longer matches LOCAL must fail closed.');

$analysisError = $analysis;
$analysisError['comparison_error'] = 'failed';
$result = (new TemplateUpdatePreflightService(static fn(string $templateId): array => $analysisError))->run('12345');
assertPreflight('blocked_analysis', $result['status'], 'Comparison errors must block preflight.');

$unexpectedWrite = $analysis;
$unexpectedWrite['update_readiness']['write_enabled'] = true;
$threw = false;
try {
	(new TemplateUpdatePreflightService(static fn(string $templateId): array => $unexpectedWrite))->run('12345');
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertPreflight(true, $threw, 'Preflight must reject any read-only readiness result that unexpectedly enables writes.');

$threw = false;
try {
	$service->run('../12345');
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertPreflight(true, $threw, 'Preflight must validate template IDs independently.');

echo "TemplateUpdatePreflightService tests passed.\n";
