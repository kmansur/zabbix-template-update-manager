<?php
use Modules\ZabbixTemplateUpdateManager\Service\UpdateReadinessEvaluator as Readiness;
require_once dirname(__DIR__, 2).'/src/Service/UpdateReadinessEvaluator.php';
$template = ['upstream_status' => 'official_match', 'version_status' => 'update_available', 'host_count' => 1];
$detail = ['path'=>'templates/0/items/0','entity_type'=>'items','field'=>'delay',
    'change_type'=>'updated','before'=>'30s','after'=>'1m'];
$preview = ['summary'=>['total'=>1,'unresolved'=>0],'details'=>[$detail],'details_truncated'=>false];
$risk = ['technical_level'=>'medium','level'=>'unknown','coverage'=>'incomplete'];
$baseline = ['status'=>'ambiguous'];
$pending = Readiness::evaluate($template,$baseline,null,$preview,$risk);
if ($pending['status'] !== 'review_required' || !$pending['candidate_for_backup']
    || !$pending['manual_confirmation_required'] || $pending['write_enabled']
    || $pending['manual_reasons'] !== ['unverified_historical_baseline']) {
    throw new RuntimeException('Baseline-free candidate should require explicit backup and manual review.');
}
$verified = Readiness::evaluate($template,$baseline,null,$preview,$risk,
    ['status'=>'current_match','current_match'=>true]);
if ($verified['status'] !== 'review_backup_verified' || !$verified['backup_verified']
    || $verified['write_enabled']) {
    throw new RuntimeException('Baseline-free candidate with backup must only reach reviewed preflight.');
}
foreach ([
    array_merge($preview,['details_truncated'=>true]),
    array_merge($preview,['details'=>[]]),
    array_merge($preview,['summary'=>['total'=>1,'unresolved'=>1]])
] as $bad) {
    $blocked = Readiness::evaluate($template,$baseline,null,$bad,$risk,
        ['status'=>'current_match','current_match'=>true]);
    if (!str_starts_with($blocked['status'],'blocked_') || $blocked['write_enabled']) {
        throw new RuntimeException('Incomplete baseline-free candidate must remain blocked.');
    }
}
$unknown = Readiness::evaluate($template,$baseline,null,$preview,
    ['technical_level'=>'unknown','level'=>'unknown','coverage'=>'incomplete']);
if ($unknown['status'] !== 'blocked_unresolved') {
    throw new RuntimeException('Unknown technical risk must block baseline-free assisted review.');
}
echo "Baseline-free assisted readiness tests passed.\n";
