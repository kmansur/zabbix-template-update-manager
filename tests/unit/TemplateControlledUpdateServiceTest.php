<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledUpdateService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateControlledUpdateService.php';

function assertControlledUpdate($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$evidence = hash('sha256', 'fresh-preflight');
$contentSha = hash('sha256', 'canonical');
$candidate = [
	'commit' => '0123456789abcdef0123456789abcdef01234567',
	'path' => 'templates/os/linux/template_os_linux.yaml',
	'content_sha256' => $contentSha,
	'uuid' => 'f8f7908280354f2abeed07dc788c3747',
	'name' => 'Linux by Zabbix agent',
	'technical_name' => 'Linux by Zabbix agent',
	'vendor_name' => 'Zabbix',
	'vendor_version' => '7.0-8'
];
$preflight = [
	'status' => 'passed',
	'write_enabled' => false,
	'evidence_sha256' => $evidence,
	'candidate' => $candidate,
	'rollback' => [
		'sha256' => hash('sha256', 'rollback')
	]
];
$builtCandidate = $candidate + [
	'canonical_sha256' => $contentSha,
	'import_sha256' => hash('sha256', 'isolated'),
	'format' => 'json',
	'source' => '{"zabbix_export":{"version":"7.0","templates":[]}}'
];

$imported = false;
$service = new TemplateControlledUpdateService(
	static fn(string $templateId): array => $preflight,
	static fn(array $freshPreflight): array => $builtCandidate,
	static function (array $candidateToImport) use (&$imported): void {
		$imported = true;
	},
	static fn(string $templateId, array $candidateToValidate): array => [
		'status' => 'validated',
		'valid' => true,
		'expected_version' => '7.0-8',
		'installed_version' => '7.0-8',
		'content_status' => 'matches_current_upstream',
		'remaining_changes' => 0,
		'reasons' => []
	]
);

$result = $service->execute('12345', $evidence);
assertControlledUpdate('updated', $result['status'], 'Matching fresh evidence should allow the controlled update path.');
assertControlledUpdate(true, $result['write_performed'], 'Successful controlled update must report that a write occurred.');
assertControlledUpdate(true, $imported, 'Importer must run only after fresh preflight and evidence match.');
assertControlledUpdate($evidence, $result['preflight_evidence_sha256'], 'Result must retain confirmed fresh evidence.');
assertControlledUpdate($contentSha, $result['candidate']['canonical_sha256'], 'Result must retain verified upstream content evidence.');

$imported = false;
$result = $service->execute('12345', hash('sha256', 'stale-page'));
assertControlledUpdate('blocked_evidence_changed', $result['status'], 'Stale confirmation evidence must fail closed.');
assertControlledUpdate(false, $result['write_performed'], 'Stale evidence must not write configuration.');
assertControlledUpdate(false, $imported, 'Importer must not run after evidence mismatch.');

$blockedPreflight = $preflight;
$blockedPreflight['status'] = 'blocked_backup';
$blockedPreflight['reason'] = 'rollback_evidence_unresolved';
$imported = false;
$blockedService = new TemplateControlledUpdateService(
	static fn(string $templateId): array => $blockedPreflight,
	static fn(array $freshPreflight): array => $builtCandidate,
	static function (array $candidateToImport) use (&$imported): void {
		$imported = true;
	},
	static fn(string $templateId, array $candidateToValidate): array => ['valid' => true]
);
$result = $blockedService->execute('12345', $evidence);
assertControlledUpdate('blocked_preflight', $result['status'], 'Non-passing fresh preflight must block update.');
assertControlledUpdate(false, $imported, 'Importer must not run when fresh preflight is blocked.');

$badCandidate = $builtCandidate;
$badCandidate['vendor_version'] = '7.0-9';
$imported = false;
$mismatchService = new TemplateControlledUpdateService(
	static fn(string $templateId): array => $preflight,
	static fn(array $freshPreflight): array => $badCandidate,
	static function (array $candidateToImport) use (&$imported): void {
		$imported = true;
	},
	static fn(string $templateId, array $candidateToValidate): array => ['valid' => true]
);
$threw = false;
try {
	$mismatchService->execute('12345', $evidence);
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertControlledUpdate(true, $threw, 'Candidate identity drift after preflight must throw before import.');
assertControlledUpdate(false, $imported, 'Candidate identity drift must not invoke importer.');

$hashDriftCandidate = $builtCandidate;
$hashDriftCandidate['canonical_sha256'] = hash('sha256', 'different-source');
$imported = false;
$hashDriftService = new TemplateControlledUpdateService(
	static fn(string $templateId): array => $preflight,
	static fn(array $freshPreflight): array => $hashDriftCandidate,
	static function (array $candidateToImport) use (&$imported): void {
		$imported = true;
	},
	static fn(string $templateId, array $candidateToValidate): array => ['valid' => true]
);
$threw = false;
try {
	$hashDriftService->execute('12345', $evidence);
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertControlledUpdate(true, $threw, 'Candidate content drift after preflight must throw before import.');
assertControlledUpdate(false, $imported, 'Candidate content drift must not invoke importer.');

$validationFailureService = new TemplateControlledUpdateService(
	static fn(string $templateId): array => $preflight,
	static fn(array $freshPreflight): array => $builtCandidate,
	static function (array $candidateToImport): void {},
	static fn(string $templateId, array $candidateToValidate): array => [
		'status' => 'validation_failed',
		'valid' => false,
		'reasons' => ['remaining_import_differences']
	]
);
$result = $validationFailureService->execute('12345', $evidence);
assertControlledUpdate('validation_failed', $result['status'], 'Post-import validation failure must be reported explicitly.');
assertControlledUpdate(true, $result['write_performed'], 'Validation failure occurs after an acknowledged import write.');

$validationExceptionService = new TemplateControlledUpdateService(
	static fn(string $templateId): array => $preflight,
	static fn(array $freshPreflight): array => $builtCandidate,
	static function (array $candidateToImport): void {},
	static function (string $templateId, array $candidateToValidate): array {
		throw new RuntimeException('simulated validation failure');
	}
);
$result = $validationExceptionService->execute('12345', $evidence);
assertControlledUpdate('validation_failed', $result['status'], 'Validation exceptions after import must be converted to explicit validation failure.');
assertControlledUpdate(true, $result['write_performed'], 'Validation exceptions occur after an acknowledged import write.');
assertControlledUpdate('validation_error', $result['validation']['status'], 'Validation exception state must remain explicit.');

$threw = false;
try {
	$service->execute('../12345', $evidence);
}
catch (RuntimeException $exception) {
	$threw = true;
}
assertControlledUpdate(true, $threw, 'Controlled update must validate template IDs independently.');

echo "TemplateControlledUpdateService tests passed.\n";
