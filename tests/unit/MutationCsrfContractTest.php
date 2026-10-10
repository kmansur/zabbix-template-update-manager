<?php
/**
 * Static CSRF contract for mutating ZTUM endpoints.
 * No Zabbix runtime, credentials, or writes required.
 */
$root = dirname(__DIR__, 2);
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$writeRoutes = [
    'ztum.templates.update_policy',
    'ztum.templates.prepare_one',
    'ztum.templates.batch_update_one',
    'ztum.templates.install_prepare_one',
    'ztum.templates.install_execute_one',
    'ztum.template.install',
    'ztum.template.backup',
    'ztum.template.update',
    'ztum.template.rollback',
    'ztum.batch.create',
    'ztum.batch.state'
];
foreach ($writeRoutes as $route) {
    if (!isset($manifest['actions'][$route])) {
        fwrite(STDERR, "FAIL: missing route ".$route."\n");
        exit(1);
    }
    $controller = $root.'/actions/'.$manifest['actions'][$route]['class'].'.php';
    $code = (string) file_get_contents($controller);
    if (str_contains($code, 'disableCsrfValidation')) {
        fwrite(STDERR, "FAIL: CSRF disabled in ".$route."\n");
        exit(1);
    }
    if (!str_contains($code, 'USER_TYPE_SUPER_ADMIN')) {
        fwrite(STDERR, "FAIL: missing Super Admin restriction in ".$route."\n");
        exit(1);
    }
}
echo "PASS: checked ".count($writeRoutes)." mutation-capable routes for CSRF bypass and authorization\n";
echo "NOTE: static test only; authenticated HTTP CSRF integration remains pending\n";
