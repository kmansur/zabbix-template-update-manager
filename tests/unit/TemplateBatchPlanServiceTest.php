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
			'manual_reasons' => in_array($status, ['review_required', 'review_backup_verified'], true)
				? ['medium_technical_risk']
				: [],
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
	static fn(string $templateId, bool $manualOverride = false): array => [
		'status' => 'passed',
		'manual_override' => $manualOverride,
		'evidence_sha256' => hash('sha256', ($manualOverride ? 'manual-' : 'evidence-').$templateId)
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
assertBatchPlan('', $plan['items'][1]['evidence_sha256'], 'Manual-review template must never carry unattended Ready evidence.');
assertBatchPlan(true, $plan['items'][1]['batch_manual_eligible'],
	'Technical-risk-only reviewed template with verified manual preflight may be explicitly selected for reviewed batch override.');
assertBatchPlan(hash('sha256', 'manual-102'), $plan['items'][1]['manual_evidence_sha256'],
	'Reviewed batch override must retain manual-mode preflight evidence separately from Ready evidence.');
assertBatchPlan(['medium_technical_risk'], $plan['items'][1]['manual_reasons'],
	'Reviewed batch override must expose the exact manual reasons.');
assertBatchPlan('', $plan['items'][2]['evidence_sha256'], 'Conflict template must never carry batch execution evidence.');
assertBatchPlan('', $plan['items'][3]['evidence_sha256'], 'Blocked template must never carry batch execution evidence.');

$localOverwriteService = new TemplateBatchPlanService(
	static fn(string $templateId): array => [
		'template' => [
			'templateid' => $templateId,
			'name' => 'Template '.$templateId,
			'vendor_version' => '7.0-1',
			'upstream_vendor_version' => '7.0-2',
			'host_count' => 0
		],
		'update_readiness' => [
			'status' => 'review_backup_verified',
			'next_step' => 'run_manual_preflight',
			'candidate_for_backup' => false,
			'backup_verified' => true,
			'manual_confirmation_required' => true,
			'manual_reasons' => ['local_customization_overwrite', 'high_technical_risk'],
			'blockers' => [],
			'review_flags' => ['local_customization_overwrite', 'high_technical_risk']
		],
		'backup_verification' => ['status' => 'current_match'],
		'comparison_error' => null
	],
	null,
	static fn(string $templateId, bool $manualOverride = false): array => [
		'status' => 'passed',
		'manual_override' => $manualOverride,
		'evidence_sha256' => hash('sha256', 'local-overwrite-'.$templateId)
	]
);
$localPlan = $localOverwriteService->build(['105'], false);
assertBatchPlan(true, $localPlan['items'][0]['batch_manual_eligible'],
	'Local-customization overwrite may enter reviewed batch only after explicit local-overwrite acknowledgement.');
assertBatchPlan(true, $localPlan['items'][0]['batch_manual_requires_local_overwrite_ack'],
	'Local-overwrite reviewed batch candidate must require the additional overwrite acknowledgement.');
assertBatchPlan(hash('sha256', 'local-overwrite-105'), $localPlan['items'][0]['manual_evidence_sha256'],
	'Local-overwrite reviewed batch candidate must retain manual preflight evidence.');

$unknownManualService = new TemplateBatchPlanService(
	static fn(string $templateId): array => [
		'template' => [
			'templateid' => $templateId,
			'name' => 'Template '.$templateId,
			'vendor_version' => '7.0-1',
			'upstream_vendor_version' => '7.0-2',
			'host_count' => 0
		],
		'update_readiness' => [
			'status' => 'review_backup_verified',
			'next_step' => 'run_manual_preflight',
			'candidate_for_backup' => false,
			'backup_verified' => true,
			'manual_confirmation_required' => true,
			'manual_reasons' => ['unrecognized_manual_reason'],
			'blockers' => [],
			'review_flags' => ['unrecognized_manual_reason']
		],
		'backup_verification' => ['status' => 'current_match'],
		'comparison_error' => null
	],
	null,
	static fn(string $templateId, bool $manualOverride = false): array => [
		'status' => 'passed',
		'manual_override' => $manualOverride,
		'evidence_sha256' => hash('sha256', 'unknown-'.$templateId)
	]
);
$unknownPlan = $unknownManualService->build(['106'], false);
assertBatchPlan(false, $unknownPlan['items'][0]['batch_manual_eligible'],
	'Unrecognized manual-review reasons must remain individual-review only.');
assertBatchPlan('', $unknownPlan['items'][0]['manual_evidence_sha256'],
	'Unrecognized manual-review reasons must not receive batch execution evidence.');

$largeIds = array_map('strval', range(1001, 1026));
$largeService = new TemplateBatchPlanService(
	static fn(string $templateId): array => [
		'template' => [
			'templateid' => $templateId,
			'name' => 'Template '.$templateId,
			'vendor_version' => '7.0-1',
			'upstream_vendor_version' => '7.0-2',
			'host_count' => 0
		],
		'update_readiness' => [
			'status' => 'blocked_unresolved',
			'next_step' => 'none',
			'candidate_for_backup' => false,
			'backup_verified' => false,
			'manual_confirmation_required' => false,
			'blockers' => ['test_blocker'],
			'review_flags' => []
		],
		'comparison_error' => null
	]
);
$largePlan = $largeService->build($largeIds, false);
assertBatchPlan(26, $largePlan['summary']['selected'],
	'The former 25-template update batch ceiling must not reject a 26-template request-bounded plan.');
assertBatchPlan(26, $largePlan['summary']['blocked'],
	'All 26 regression candidates must be represented instead of being silently truncated.');

echo "TemplateBatchPlanService tests passed.\n";
