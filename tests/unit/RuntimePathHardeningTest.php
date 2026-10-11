<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Exception\RuntimeStorageException;

require_once dirname(__DIR__, 2).'/src/Service/TemplateOperationLockService.php';

foreach (['relative-locks', '../locks', '/tmp/../locks', "/tmp/\0locks", ''] as $path) {
    $rejected = false;
    try {
        new TemplateOperationLockService($path);
    }
    catch (RuntimeStorageException $exception) {
        $rejected = true;
    }
    if (!$rejected) {
        fwrite(STDERR, 'Unsafe lock directory accepted: '.var_export($path, true)."\n");
        exit(1);
    }
}

$valid = new TemplateOperationLockService('/var/lib/zabbix-template-update-manager/locks');
if ($valid->defaultDirectory() !== '/var/lib/zabbix-template-update-manager/locks') {
    fwrite(STDERR, "Default lock directory changed unexpectedly.\n");
    exit(1);
}
echo "Runtime path hardening tests passed.\n";
