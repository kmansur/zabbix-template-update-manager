<?php
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;
require_once dirname(__DIR__, 2).'/src/Service/TemplateBatchPlanService.php';

$service = new TemplateBatchPlanService(
    static fn(string $id): array => [
        'template'=>['templateid'=>$id,'name'=>'Lab RDAP','vendor_version'=>'8.0-0',
            'upstream_vendor_version'=>'8.0-2','host_count'=>1],
        'update_readiness'=>[
            'status'=>'review_backup_verified',
            'next_step'=>'run_manual_preflight',
            'manual_reasons'=>['unverified_historical_baseline'],
            'manual_confirmation_required'=>true,
            'backup_verified'=>true,
            'review_flags'=>['unverified_historical_baseline']
        ]
    ],
    static fn(array $template): array => ['status'=>'stored'],
    static fn(string $id,bool $reviewed): array => [
        'status'=>'passed','manual_override'=>$reviewed,
        'evidence_sha256'=>hash('sha256','reviewed-'.$id)
    ]
);
$item = $service->build(['10773'])['items'][0];
if ($item['category'] !== 'review'
    || !$item['batch_manual_eligible']
    || !$item['batch_manual_requires_local_overwrite_ack']
    || !preg_match('/^[a-f0-9]{64}$/', $item['manual_evidence_sha256'])) {
    throw new RuntimeException('Unverified baseline must require explicit reviewed batch execution and overwrite acknowledgement.');
}
$blockedService = new TemplateBatchPlanService(
    static fn(string $id): array => [
        'template'=>['templateid'=>$id, 'name'=>'Lab RDAP'],
        'update_readiness'=>[
            'status'=>'review_backup_verified',
            'manual_reasons'=>['unverified_historical_baseline'],
            'manual_confirmation_required'=>true,
            'backup_verified'=>true
        ]
    ],
    static fn(array $template): array => throw new RuntimeException('Backup already verified'),
    static fn(string $id,bool $reviewed): array => [
        'status'=>'blocked_candidate', 'manual_override'=>$reviewed
    ]
);
$blockedItem = $blockedService->build(['10773'])['items'][0];
if ($blockedItem['batch_manual_eligible']
    || $blockedItem['manual_evidence_sha256'] !== '') {
    throw new RuntimeException('Failed reviewed preflight must not authorize batch update.');
}
$wrongMode = new TemplateBatchPlanService(
    static fn(string $id): array => [
        'template'=>['templateid'=>$id, 'name'=>'Lab RDAP'],
        'update_readiness'=>[
            'status'=>'review_backup_verified',
            'manual_reasons'=>['unverified_historical_baseline'],
            'manual_confirmation_required'=>true,
            'backup_verified'=>true
        ]
    ],
    static fn(array $template): array => throw new RuntimeException('Backup already verified'),
    static fn(string $id,bool $reviewed): array => [
        'status'=>'passed','manual_override'=>false,
        'evidence_sha256'=>hash('sha256','invalid-mode')
    ]
);
if ($wrongMode->build(['10773'])['items'][0]['batch_manual_eligible']) {
    throw new RuntimeException('Preflight without manual mode must not enable reviewed batch.');
}
echo "Baseline-free reviewed batch gating tests passed.\n";
