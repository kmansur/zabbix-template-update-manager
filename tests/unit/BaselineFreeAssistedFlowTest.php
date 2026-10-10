<?php
use Modules\ZabbixTemplateUpdateManager\Service\UpdateReadinessEvaluator;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdatePreflightService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledUpdateService;
require_once dirname(__DIR__, 2).'/src/Service/UpdateReadinessEvaluator.php';
require_once dirname(__DIR__, 2).'/src/Service/TemplateUpdatePreflightService.php';
require_once dirname(__DIR__, 2).'/src/Service/TemplateControlledUpdateService.php';

$uuid = 'f8f7908280354f2abeed07dc788c3747';
$path = 'templates/os/linux/template_os_linux.yaml';
$sourceHash = hash('sha256', 'official yaml');
$contentHash = hash('sha256', 'canonical template');
$backupHash = hash('sha256', 'installed export');
$template = [
    'templateid' => '12345', 'uuid' => $uuid, 'name' => 'Lab template',
    'technical_name' => 'Lab template', 'vendor_version' => '8.0-0',
    'upstream_vendor_version' => '8.0-2', 'host_count' => 1,
    'upstream_status' => 'official_match', 'version_status' => 'update_available',
    'upstream' => [
        'name' => 'Lab template', 'technical_name' => 'Lab template',
        'vendor_name' => 'Zabbix', 'vendor_version' => '8.0-2',
        'content_sha256s' => [$contentHash],
        'sources' => [['path' => $path, 'sha256' => $sourceHash]]
    ]
];
$preview = ['summary' => ['total'=>1, 'unresolved'=>0],
    'details' => [['path'=>'templates/0/items/0','entity_type'=>'items',
        'field'=>'delay','change_type'=>'updated','before'=>'30s','after'=>'1m']],
    'details_truncated' => false];
$baseline = ['status' => 'ambiguous'];
$risk = ['technical_level'=>'medium', 'level'=>'unknown', 'coverage'=>'incomplete'];
$verified = ['status'=>'current_match', 'current_match'=>true,
    'latest'=>['created_at'=>'2026-10-10T00:00:00Z','bytes'=>100,'sha256'=>$backupHash],
    'current_export'=>['bytes'=>100,'sha256'=>$backupHash]];
$readiness = UpdateReadinessEvaluator::evaluate($template,$baseline,null,$preview,$risk,$verified);
$analysis = ['template'=>$template,'comparison_error'=>null,
    'upstream_source'=>['commit'=>'0123456789abcdef0123456789abcdef01234567'],
    'source_path'=>$path,'update_readiness'=>$readiness,
    'backup_verification'=>$verified];
$preflight = new TemplateUpdatePreflightService(
    static fn(string $id): array => $analysis,
    static fn(string $id,string $uuid): bool => false
);
$withoutReview = $preflight->run('12345', false);
if ($withoutReview['status'] !== 'blocked_readiness') {
    throw new RuntimeException('Assisted write must require reviewed mode.');
}
$review = $preflight->run('12345', true);
if ($review['status'] !== 'passed' || $review['write_enabled']
    || $review['manual_reasons'] !== ['unverified_historical_baseline']) {
    throw new RuntimeException('Assisted preflight must bind unverified baseline to non-write evidence.');
}
$writeCalled = false;
$controlled = new TemplateControlledUpdateService(
    static fn(string $id, bool $manual): array => $preflight->run($id, $manual),
    static fn(array $pf): array => [
        'commit'=>$pf['candidate']['commit'], 'path'=>$path, 'source_sha256'=>$sourceHash,
        'content_sha256'=>$contentHash, 'uuid'=>str_replace('-', '', $uuid),
        'name'=>'Lab template','technical_name'=>'Lab template',
        'vendor_name'=>'Zabbix','vendor_version'=>'8.0-2',
        'format'=>'json', 'source'=>'{}','import_sha256'=>hash('sha256','{}')
    ],
    static function (array $candidate) use (&$writeCalled): void { $writeCalled = true; },
    static fn(string $id,array $candidate): array => ['status'=>'validated','valid'=>true]
    ,static fn(string $id,string $uuid): bool => false
);
$stale = $controlled->execute('12345', hash('sha256','stale'), true);
if ($stale['write_performed'] || $writeCalled) {
    throw new RuntimeException('Stale assisted confirmation reached import.');
}
$missingBackup = $analysis;
$missingBackup['update_readiness'] = UpdateReadinessEvaluator::evaluate(
    $template,$baseline,null,$preview,$risk);
$blockedPreflight = new TemplateUpdatePreflightService(
    static fn(string $id): array => $missingBackup,
    static fn(string $id,string $uuid): bool => false
);
if ($blockedPreflight->run('12345', true)['status'] !== 'blocked_readiness') {
    throw new RuntimeException('Assisted preflight must reject absent rollback.');
}
echo "Baseline-free assisted flow test passed without any configuration write.\n";
