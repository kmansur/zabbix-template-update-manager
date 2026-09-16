<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchUpdateService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateBatchUpdateService.php';

function assertBatchUpdate($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$evidence = [
	'101' => hash('sha256', '101'),
	'102' => hash('sha256', '102'),
	'103' => hash('sha256', '103')
];

$calls = [];
$service = new TemplateBatchUpdateService(
	static function (string $templateId, string $expectedEvidence) use (&$calls): array {
		$calls[] = $templateId;
		return [
			'status' => 'updated',
			'write_performed' => true,
			'candidate' => ['vendor_version' => '7.0-9'],
			'validation' => ['status' => 'validated']
		];
	}
);
$result = $service->execute(['101', '102'], $evidence);
assertBatchUpdate('completed', $result['status'], 'All-success batch must complete.');
assertBatchUpdate(['101', '102'], $calls, 'Batch must execute in selected order.');
assertBatchUpdate(2, count($result['updated']), 'Both templates must be recorded as updated.');
assertBatchUpdate(null, $result['failed'], 'Successful batch must have no failure.');
assertBatchUpdate([], $result['not_attempted'], 'Successful batch must have no not-attempted templates.');

$calls = [];
$service = new TemplateBatchUpdateService(
	static function (string $templateId, string $expectedEvidence) use (&$calls): array {
		$calls[] = $templateId;
		if ($templateId === '102') {
			return [
				'status' => 'blocked_evidence_changed',
				'write_performed' => false,
				'reason' => 'preflight_evidence_changed'
			];
		}
		return [
			'status' => 'updated',
			'write_performed' => true,
			'candidate' => ['vendor_version' => '7.0-9'],
			'validation' => ['status' => 'validated']
		];
	}
);
$result = $service->execute(['101', '102', '103'], $evidence);
assertBatchUpdate('stopped', $result['status'], 'First failure must stop batch execution.');
assertBatchUpdate(['101', '102'], $calls, 'Templates after the first failure must not execute.');
assertBatchUpdate('102', $result['failed']['templateid'], 'Failure must identify the stopping template.');
assertBatchUpdate(['103'], $result['not_attempted'], 'Remaining templates must be reported as not attempted.');

$calls = [];
$badEvidence = $evidence;
$badEvidence['101'] = 'not-a-sha';
$result = $service->execute(['101', '102'], $badEvidence);
assertBatchUpdate('stopped', $result['status'], 'Invalid evidence must stop before execution.');
assertBatchUpdate([], $calls, 'Invalid evidence must not invoke controlled update.');
assertBatchUpdate('invalid_evidence', $result['failed']['status'], 'Invalid evidence status must remain explicit.');
assertBatchUpdate(['102'], $result['not_attempted'], 'Later templates must remain unattempted after invalid evidence.');

echo "TemplateBatchUpdateService tests passed.\n";
