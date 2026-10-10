<?php
$root = dirname(__DIR__, 2);
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true);
if (($manifest['actions']['ztum.template.prepare']['class'] ?? '') !== 'TemplateUpdatePrepare'
    || ($manifest['actions']['ztum.template.prepare']['view'] ?? '') !== 'ztum.template.preflight') {
    throw new RuntimeException('Native single-template preparation action is not registered correctly.');
}
$view = (string) file_get_contents($root.'/views/ztum.template.compare.php');
$action = (string) file_get_contents($root.'/actions/TemplateUpdatePrepare.php');
$service = (string) file_get_contents($root.'/src/Service/TemplateUpdatePreparationService.php');
foreach ([
    'ztum.template.prepare',
    'Prepare and review update',
    'new CForm',
    'CCsrfTokenHelper::get',
    'No template import occurs until you explicitly confirm'
] as $needle) {
    if (!str_contains($view, $needle)) {
        throw new RuntimeException('Native preparation UI contract missing: '.$needle);
    }
}
foreach ([
    'USER_TYPE_SUPER_ADMIN',
    'TemplateUpdatePreparationService',
    'CControllerResponseData',
    'validateInput'
] as $needle) {
    if (!str_contains($action, $needle)) {
        throw new RuntimeException('Preparation action authorization missing: '.$needle);
    }
}
foreach ([
    'TemplateBackupService',
    'TemplateUpdatePreflightService',
    'current_match',
    'backup_verified',
    'review_backup_verified'
] as $needle) {
    if (!str_contains($service, $needle)) {
        throw new RuntimeException('Automatic preparation safety contract missing: '.$needle);
    }
}
if (str_contains($service, 'API::Configuration()->import(')
    || str_contains($action, 'API::Configuration()->import(')) {
    throw new RuntimeException('Preparation must not import Zabbix configuration.');
}
echo "Native automatic update preparation contract tests passed.\n";
