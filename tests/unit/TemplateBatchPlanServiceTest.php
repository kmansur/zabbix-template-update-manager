<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateBatchPlanService.php';

function assertBatchPlan($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$backupCreated = [];
$analysisCalls = [];
$analysisRunner = static function (string $templateId) use (&$backupCreated, &$analysisCalls): array {
	$analysisCalls[$templateId] = ($analysisCalls[$templateId] ?? 0) + 1;
	$status = match ($templateId) {
		'101' => isset($backupCreated[$templateId]) ? 'backup_verified' : 'candidate_for_backup',
		'102' => isset($backupCreated[$templateId]) ? 'review_backup_verified' : 'review_required',
		'103' => 'blocked_conflict',
		default => 'blocked_unresolved'
	};

	return [
		'template' => [
			'templateid' => $templateId,
			'name' => 'Template '.$templateId,
			'vendor_version' => '7.0-1',
			'upstream_vendor_version' => '7.0-2',
			'host_count' => 1
		],
		'update_readiness' => [
			'status' => $status,
			'next_step' => in_array($status, ['candidate_for_backup', 'review_required'], true)
				? 'create_and_verify_backup'
				: 'none',
			'candidate_for_backup' => in_array($status, ['candidate_for_backup', 'review_required'], true),
			'backup_verified' => in_array($status, ['backup_verified', 'review_backup_verified'], true),
			'manual_confirmation_required' => in_array($status, ['review_required', 'review_backup_verified'], true),
			'blockers' => $status === 'blocked_conflict' ? ['three_way_conflict'] : [],
			'review_flags' => in_array($status, ['review_required', 'review_backup_verified'], true)
				? ['medium_technical_risk']
				: []
		],
		'backup_verification' => in_array($status, ['backup_verified', 'review_backup_verified'], true)
			? ['status' => 'current_match']
			: null,
		'comparison_error' => null
	];
};

$service = new TemplateBatchPlanService(
	$analysisRunner,
	static function (array $template) use (&$backupCreated): array {
		$backupCreated[(string) $template['templateid']] = true;
		return ['status' => 'stored'];
	},
	static fn(string $templateId): array => [
		'status' => 'passed',
		'evidence_sha256' => hash('sha256', 'evidence-'.$templateId)
	]
);

$plan = $service->build(['101', '102', '103', '104'], true);
assertBatchPlan(4, $plan['summary']['selected'], 'All selected templates must be represented.');
assertBatchPlan(1, $plan['summary']['ready'], 'Prepared low-risk template must become ready.');
assertBatchPlan(1, $plan['summary']['review'], 'Medium-risk template must require manual review.');
assertBatchPlan(1, $plan['summary']['conflict'], 'Conflict template must be separated.');
assertBatchPlan(1, $plan['summary']['blocked'], 'Unresolved template must remain blocked.');
assertBatchPlan(true, isset($backupCreated['101']), 'Candidate-for-backup template must receive a rollback artifact during preparation.');
assertBatchPlan(true, isset($backupCreated['102']), 'Manual-review candidate may prepare rollback evidence without becoming batch-ready.');
assertBatchPlan('ready', $plan['items'][0]['category'], 'Prepared and preflighted template must be ready.');
assertBatchPlan('review', $plan['items'][1]['category'], 'Reviewed manual-update template must remain review-only in batch mode.');
assertBatchPlan('conflict', $plan['items'][2]['category'], 'Conflict template must classify as conflict.');
assertBatchPlan('blocked', $plan['items'][3]['category'], 'Unresolved template must classify as blocked.');
assertBatchPlan(hash('sha256', 'evidence-101'), $plan['items'][0]['evidence_sha256'], 'Ready template must retain fresh preflight evidence.');
assertBatchPlan('', $plan['items'][1]['evidence_sha256'], 'Manual-review template must never carry batch execution evidence.');
assertBatchPlan('', $plan['items'][2]['evidence_sha256'], 'Conflict template must never carry batch execution evidence.');
assertBatchPlan('', $plan['items'][3]['evidence_sha256'], 'Blocked template must never carry batch execution evidence.');

echo "TemplateBatchPlanService tests passed.\n";
