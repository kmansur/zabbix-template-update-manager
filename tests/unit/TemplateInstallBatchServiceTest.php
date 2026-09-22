<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallBatchService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateInstallBatchService.php';

function assertInstallBatch($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$u1 = str_repeat('a', 32);
$u2 = str_repeat('b', 32);
$u3 = str_repeat('c', 32);
$evidence = [
	$u1 => hash('sha256', $u1),
	$u2 => hash('sha256', $u2),
	$u3 => hash('sha256', $u3)
];

$calls = [];
$service = new TemplateInstallBatchService(
	static function (string $uuid, string $expectedEvidence) use (&$calls): array {
		$calls[] = $uuid;
		return [
			'status' => 'installed',
			'write_performed' => true,
			'candidate' => ['name' => 'Template '.$uuid[0], 'vendor_version' => '7.0-9'],
			'validation' => ['templateid' => '12001', 'status' => 'validated']
		];
	}
);

$result = $service->execute([$u1, $u2], $evidence);
assertInstallBatch('completed', $result['status'], 'All-success batch installation must complete.');
assertInstallBatch([$u1, $u2], $calls, 'Install batch must execute in selected order.');
assertInstallBatch(2, count($result['installed']), 'Both successful installs must be recorded.');
assertInstallBatch(null, $result['failed'], 'Successful install batch must have no failure.');
assertInstallBatch([], $result['not_attempted'], 'Successful install batch must have no not-attempted candidates.');

$calls = [];
$service = new TemplateInstallBatchService(
	static function (string $uuid, string $expectedEvidence) use (&$calls, $u2): array {
		$calls[] = $uuid;
		if ($uuid === $u2) {
			return [
				'status' => 'blocked_evidence_changed',
				'write_performed' => false,
				'reason' => 'installation_evidence_changed'
			];
		}
		return [
			'status' => 'installed',
			'write_performed' => true,
			'candidate' => ['name' => 'Template '.$uuid[0], 'vendor_version' => '7.0-9'],
			'validation' => ['templateid' => '12001', 'status' => 'validated']
		];
	}
);

$result = $service->execute([$u1, $u2, $u3], $evidence);
assertInstallBatch('stopped', $result['status'], 'First non-successful install must stop batch execution.');
assertInstallBatch([$u1, $u2], $calls, 'Candidates after the first failure must not execute.');
assertInstallBatch($u2, $result['failed']['uuid'], 'Failure must identify the stopping UUID.');
assertInstallBatch([$u3], $result['not_attempted'], 'Remaining install candidates must be reported as not attempted.');

$calls = [];
$bad = $evidence;
$bad[$u1] = 'bad';
$result = $service->execute([$u1, $u2], $bad);
assertInstallBatch('stopped', $result['status'], 'Invalid evidence must stop before installation.');
assertInstallBatch([], $calls, 'Invalid evidence must not invoke controlled install.');
assertInstallBatch('invalid_evidence', $result['failed']['status'], 'Invalid evidence state must remain explicit.');
assertInstallBatch([$u2], $result['not_attempted'], 'Later candidates must remain unattempted.');

echo "TemplateInstallBatchService tests passed.\n";
