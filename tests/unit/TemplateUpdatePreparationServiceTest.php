<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdatePreparationService;
require_once dirname(__DIR__, 2).'/src/Service/TemplateUpdatePreparationService.php';

function checkPreparation(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$created = 0;
$analyzed = 0;
$freshPreflights = 0;
$analysis = static function (string $id) use (&$analyzed, &$created): array {
    $analyzed++;
    $verified = $created > 0;
    return [
        'template'=>['templateid'=>$id, 'uuid'=>str_repeat('a',32)],
        'update_readiness'=>[
            'status'=>$verified ? 'review_backup_verified' : 'review_required',
            'candidate_for_backup'=>!$verified,
            'backup_verified'=>$verified,
            'manual_confirmation_required'=>true,
            'manual_reasons'=>['unverified_historical_baseline']
        ],
        'backup_verification'=>$verified
            ? ['status'=>'current_match','current_match'=>true] : null
    ];
};
$service = new TemplateUpdatePreparationService(
    $analysis,
    static function (array $template) use (&$created): array {
        $created++;
        return ['sha256'=>hash('sha256','stored')];
    },
    static function (string $id, bool $manual) use (&$freshPreflights): array {
        $freshPreflights++;
        checkPreparation($manual, 'Baseline-free review must use reviewed preflight.');
        return ['status'=>'passed','write_enabled'=>false,
            'manual_override'=>true,'evidence_sha256'=>hash('sha256','review'),
            'template'=>['templateid'=>$id],
            'candidate'=>['commit'=>str_repeat('a',40)],
            'rollback'=>['sha256'=>hash('sha256','rollback')]];
    }
);
$result = $service->prepare('10773');
checkPreparation($result['status']==='passed', 'Verified preparation should reach review.');
checkPreparation($created===1 && $analyzed===2 && $freshPreflights===1,
    'Automatic preparation should create one backup and reanalyze before preflight.');

$backupCreated = false;
$unverified = new TemplateUpdatePreparationService(
    static fn(string $id): array => [
        'template'=>['templateid'=>$id],
        'update_readiness'=>['status'=>'review_required','candidate_for_backup'=>true],
        'backup_verification'=>null
    ],
    static function (array $template) use (&$backupCreated): array {
        $backupCreated = true;
        return [];
    },
    static fn(string $id,bool $manual): array => throw new RuntimeException('Preflight must not run')
);
try {
    $unverified->prepare('10773');
    throw new RuntimeException('Missing verified backup should have failed.');
}
catch (RuntimeException $error) {
    checkPreparation($backupCreated, 'Expected the backup attempt.');
    checkPreparation(str_contains($error->getMessage(),'not verified'),
        'Unverified backup must reject preparation.');
}

$blocked = new TemplateUpdatePreparationService(
    static fn(string $id): array => ['update_readiness'=>['status'=>'blocked_baseline']],
    static fn(array $template): array => throw new RuntimeException('Must not create backup'),
    static fn(string $id,bool $manual): array => throw new RuntimeException('Must not preflight')
);
try {
    $blocked->prepare('10773');
    throw new RuntimeException('Blocked readiness must not prepare.');
}
catch (RuntimeException $error) {
    checkPreparation(str_contains($error->getMessage(),'not eligible'),
        'Blocked readiness must not bypass safety gates.');
}
echo "Automatic individual update preparation tests passed (no Zabbix import).\n";
