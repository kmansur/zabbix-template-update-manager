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
assertReadiness(false, $result['manual_confirmation_required'], 'Low-risk path must not require a reviewed override.');
assertReadiness(false, $result['write_enabled'], 'Readiness evaluator must never enable configuration writes by itself.');
assertReadiness(37, $result['direct_host_count'], 'Direct host impact must be preserved as context.');
assertReadiness('create_and_verify_backup', $result['next_step'], 'Backup must remain the next step before controlled preflight.');

$verifiedBackup = ['status' => 'current_match', 'current_match' => true];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, $riskLow, $verifiedBackup);
assertReadiness('backup_verified', $result['status'], 'Exact current rollback artifact should advance low-risk readiness to backup_verified.');
assertReadiness(true, $result['backup_verified'], 'Verified rollback prerequisite must be explicit.');
assertReadiness(false, $result['candidate_for_backup'], 'Once verified, backup creation is no longer the outstanding prerequisite.');
assertReadiness('run_controlled_preflight', $result['next_step'], 'Verified low-risk backup must advance only to the fresh controlled preflight gate.');
assertReadiness(false, $result['write_enabled'], 'Even backup_verified must not directly enable a Zabbix configuration write.');

$staleBackup = ['status' => 'current_mismatch', 'current_match' => false];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, $riskLow, $staleBackup);
assertReadiness('candidate_for_backup', $result['status'], 'Stale backup must not satisfy the rollback prerequisite.');

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

$result = UpdateReadinessEvaluator::evaluate($template, ['status' => 'ambiguous'], $threeWay, $preview, $riskLow);
assertReadiness('blocked_baseline', $result['status'], 'Ambiguous historical provenance must remain a hard blocker.');

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
assertReadiness('blocked_conflict', $result['status'], 'Confirmed three-way conflict must remain a hard blocker.');
assertReadiness(false, $result['candidate_for_backup'], 'Conflict must not advance to backup/update path.');

$threeWayOverwrite = ['summary' => ['unresolved' => 0, 'conflict' => 0, 'local_only_overwrite' => 3]];
$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWayOverwrite, $preview, ['coverage' => 'complete', 'level' => 'high']);
assertReadiness('review_required', $result['status'], 'Known local overwrite with no conflict should enter explicit manual-review path.');
assertReadiness(true, $result['candidate_for_backup'], 'Manual-review path may prepare a rollback backup.');
assertReadiness(true, $result['manual_confirmation_required'], 'Manual-review path must require explicit final acknowledgement.');
assertReadiness(
	['local_customization_overwrite', 'high_technical_risk'],
	$result['manual_reasons'],
	'Manual-review reasons must bind both overwrite and technical risk.'
);

$result = UpdateReadinessEvaluator::evaluate(
	$template,
	$baseline,
	$threeWayOverwrite,
	$preview,
	['coverage' => 'complete', 'level' => 'high'],
	$verifiedBackup
);
assertReadiness('review_backup_verified', $result['status'], 'Reviewed path with current rollback evidence should become review_backup_verified.');
assertReadiness(true, $result['backup_verified'], 'Reviewed path must retain verified rollback prerequisite.');
assertReadiness('run_manual_preflight', $result['next_step'], 'Reviewed path must advance to explicit manual preflight, not unattended update.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'incomplete', 'level' => 'unknown']);
assertReadiness('blocked_unresolved', $result['status'], 'Incomplete risk coverage must remain a hard blocker.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'complete', 'level' => 'high']);
assertReadiness('review_required', $result['status'], 'Known high technical risk should enter explicit manual-review path.');
assertReadiness(['high_technical_risk'], $result['manual_reasons'], 'High-risk reason must be explicit.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'complete', 'level' => 'medium']);
assertReadiness('review_required', $result['status'], 'Medium technical risk should enter explicit manual-review path.');
assertReadiness(['medium_technical_risk'], $result['manual_reasons'], 'Medium-risk reason must be explicit.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, ['coverage' => 'complete', 'level' => 'none']);
assertReadiness('candidate_for_backup', $result['status'], 'No effective technical risk with complete evidence may advance to standard backup creation.');

$result = UpdateReadinessEvaluator::evaluate($template, $baseline, $threeWay, $preview, null);
assertReadiness('blocked_unresolved', $result['status'], 'Missing risk analysis must fail closed.');

echo "UpdateReadinessEvaluator tests passed.\n";
