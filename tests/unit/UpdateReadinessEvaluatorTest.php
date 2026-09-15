<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpdateReadinessEvaluator;

require_once dirname(__DIR__, 2).'/src/Service/UpdateReadinessEvaluator.php';

function assertReadiness($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$template = [
	'upstream_status' => 'official_match',
	'version_status' => 'update_available',
	'host_count' => 37
];
$baseline = ['status' => 'found'];
$preview = ['summary' => ['unresolved' => 0]];
$threeWay = ['summary' => ['unresolved' => 0, 'conflict' => 0, 'local_only_overwrite' => 0]];
$riskLow = ['coverage' => 'complete', 'level' => 'low'];

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, $riskLow);
assertReadiness('candidate_for_backup', $result['status'], 'Low known risk with complete overlap evidence should become a backup candidate.');
assertReadiness(true, $result['candidate_for_backup'], 'Positive comparison gate should allow advancing to backup creation.');
assertReadiness(false, $result['backup_verified'], 'Backup candidate is not yet a verified rollback prerequisite.');
assertReadiness(false, $result['write_enabled'], 'Readiness evaluator must never enable configuration writes by itself.');
assertReadiness(37, $result['direct_host_count'], 'Direct host impact must be preserved as context.');
assertReadiness('create_and_verify_backup', $result['next_step'], 'Backup must remain the next step before controlled preflight.');

$verifiedBackup = ['status' => 'current_match', 'current_match' => true];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, $riskLow, $verifiedBackup);
assertReadiness('backup_verified', $result['status'], 'Exact current rollback artifact should advance readiness to backup_verified.');
assertReadiness(true, $result['backup_verified'], 'Verified rollback prerequisite must be explicit.');
assertReadiness(false, $result['candidate_for_backup'], 'Once verified, backup creation is no longer the outstanding prerequisite.');
assertReadiness('run_controlled_preflight', $result['next_step'], 'Verified backup must advance only to the fresh controlled preflight gate.');
assertReadiness(false, $result['write_enabled'], 'Even backup_verified must not directly enable a Zabbix configuration write.');

$staleBackup = ['status' => 'current_mismatch', 'current_match' => false];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, $riskLow, $staleBackup);
assertReadiness('candidate_for_backup', $result['status'], 'Stale backup must not satisfy the rollback prerequisite.');
assertReadiness(false, $result['backup_verified'], 'Stale backup must never be marked verified.');

$invalidBackup = ['status' => 'latest_invalid', 'current_match' => false];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, $riskLow, $invalidBackup);
assertReadiness('candidate_for_backup', $result['status'], 'Invalid newest backup must leave the workflow at backup candidacy.');

$notOfficial = $template;
$notOfficial['upstream_status'] = 'not_found';
$result = UpdateReadinessEvaluator::evaluate($notOfficial, null, null, null, null);
assertReadiness('not_applicable', $result['status'], 'Non-official templates must not enter the official update readiness path.');

$current = $template;
$current['version_status'] = 'current';
$result = UpdateReadinessEvaluator::evaluate($current, null, null, null, null);
assertReadiness('not_applicable', $result['status'], 'Current templates do not require an update readiness gate.');

$result = UpdateReadinessEvaluator::evaluate($template, null, $threeWay, $preview, $riskLow);
assertReadiness('blocked_baseline', $result['status'], 'Missing historical baseline must block readiness.');
assertReadiness('resolve_historical_baseline', $result['next_step'], 'Baseline resolution must be the next step.');

$result = UpdateReadinessEvaluator::evaluate($template, ['status' => 'history_limit_reached'], $threeWay, $preview, $riskLow);
assertReadiness('blocked_baseline', $result['status'], 'Truncated history must not be treated as authoritative baseline coverage.');

$unresolvedPreview = ['summary' => ['unresolved' => 1]];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $unresolvedPreview, $riskLow);
assertReadiness('blocked_unresolved', $result['status'], 'Unresolved update-preview identities must block readiness.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, null, $preview, $riskLow);
assertReadiness('blocked_unresolved', $result['status'], 'Missing three-way analysis must block readiness.');

$threeWayUnresolved = ['summary' => ['unresolved' => 2, 'conflict' => 0, 'local_only_overwrite' => 0]];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWayUnresolved, $preview, $riskLow);
assertReadiness('blocked_unresolved', $result['status'], 'Unresolved three-way state must block readiness.');

$threeWayConflict = ['summary' => ['unresolved' => 0, 'conflict' => 1, 'local_only_overwrite' => 0]];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWayConflict, $preview, ['coverage' => 'complete', 'level' => 'conflict']);
assertReadiness('blocked_conflict', $result['status'], 'Confirmed three-way conflict must block readiness.');
assertReadiness('resolve_conflicts', $result['next_step'], 'Conflicts require explicit resolution before any further update path.');

$threeWayOverwrite = ['summary' => ['unresolved' => 0, 'conflict' => 0, 'local_only_overwrite' => 3]];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWayOverwrite, $preview, ['coverage' => 'complete', 'level' => 'high']);
assertReadiness('blocked_local_overwrite', $result['status'], 'Known local customizations that upstream would overwrite must block the default update path.');
assertReadiness('protect_local_customizations', $result['next_step'], 'Local customizations must be protected/reconciled first.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'incomplete', 'level' => 'unknown']);
assertReadiness('blocked_unresolved', $result['status'], 'Incomplete risk coverage must block readiness.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'complete', 'level' => 'high']);
assertReadiness('review_high', $result['status'], 'Known high technical risk should require manual high-risk review rather than becoming a backup candidate automatically.');
assertReadiness(false, $result['candidate_for_backup'], 'High-risk review must be completed before the gate advances to backup.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'complete', 'level' => 'medium']);
assertReadiness('review_medium', $result['status'], 'Medium technical risk should require manual review.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'complete', 'level' => 'none']);
assertReadiness('candidate_for_backup', $result['status'], 'No effective technical risk with complete evidence may advance to backup creation.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, null);
assertReadiness('blocked_unresolved', $result['status'], 'Missing risk analysis must fail closed.');

echo "UpdateReadinessEvaluator tests passed.\n";
