<?php

$root = dirname(__DIR__, 2);
$module = (string) file_get_contents($root.'/Module.php');

function assertModuleMenuContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertModuleMenuContract(
	strpos($module, 'use CWebUser;') !== false
		&& strpos($module, 'CWebUser::getType()') !== false
		&& strpos($module, '[USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]') !== false,
	'ZTUM menu registration must be limited to Zabbix administrators and super administrators.'
);

assertModuleMenuContract(
	strpos($module, "->setAction('ztum.templates')") !== false,
	'ZTUM menu must continue to point to the catalog action.'
);

echo "Module menu permission contract tests passed.\n";
