<?php
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
require_once dirname(__DIR__, 2).'/src/Support/ZabbixVersion.php';
$pre = ['8.0.0beta2', '8.0.0rc1', '8.0.0-rc1', '8.0.0~beta2', '8.0.0alpha1', '8.0.0dev'];
foreach ($pre as $v) {
    if (!ZabbixVersion::isPrerelease($v)) {
        throw new RuntimeException("Expected prerelease: ".$v);
    }
}
foreach (['8.0.0', '8.0.1', '7.0.29', '7.0.0'] as $v) {
    if (ZabbixVersion::isPrerelease($v)) {
        throw new RuntimeException("Stable version misclassified: ".$v);
    }
}
echo "Zabbix prerelease detection tests passed.\n";
