<?php

/**
 * Security contract: every action declared by the module is Super Admin only.
 * This source-level regression test is deliberately independent of the Zabbix runtime.
 * It does not replace HTTP authorization and CSRF field tests.
 */
$root = dirname(__DIR__, 2);
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$module = (string) file_get_contents($root.'/Module.php');

function failAccessContract(string $reason): void {
    fwrite(STDERR, "FAIL: ".$reason."\n");
    exit(1);
}

if (!str_contains($module, 'CWebUser::getType() !== USER_TYPE_SUPER_ADMIN')
    || str_contains($module, 'USER_TYPE_ZABBIX_ADMIN')) {
    failAccessContract('Navigation must be restricted to Super Admin.');
}

foreach ($manifest['actions'] as $route => $action) {
    $path = $root.'/actions/'.$action['class'].'.php';
    if (!is_file($path)) {
        failAccessContract($route.': controller file missing.');
    }

    $code = (string) file_get_contents($path);
    if (!preg_match(
        '/protected\\s+function\\s+checkPermissions\\s*\\(\\s*\\)\\s*:\\s*bool\\s*\\{([^{}]*)\\}/s',
        $code,
        $matches
    )) {
        failAccessContract($route.': permissions method missing or too complex for static proof.');
    }
    if (!preg_match('/return\\s+\\$this->getUserType\\(\\)\\s*===\\s*USER_TYPE_SUPER_ADMIN\\s*;/', $matches[1])
        || str_contains($matches[1], 'USER_TYPE_ZABBIX_ADMIN')) {
        failAccessContract($route.': Super Admin restriction not enforced.');
    }
}

printf("PASS: navigation is restricted to Super Admin\n");
printf("PASS: all %d registered actions are restricted to Super Admin\n", count($manifest['actions']));
