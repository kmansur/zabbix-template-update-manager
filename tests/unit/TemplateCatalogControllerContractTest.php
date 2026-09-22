<?php

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root.'/actions/TemplateList.php');

function assertCatalogControllerContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertCatalogControllerContract(
	preg_match('/^use CPagerHelper;$/m', $controller) === 1,
	'TemplateList must import the native global CPagerHelper class before using catalog pagination.'
);

assertCatalogControllerContract(
	preg_match('/^use CUrl;$/m', $controller) === 1,
	'TemplateList must import the native global CUrl class before building pager URLs.'
);

assertCatalogControllerContract(
	strpos($controller, 'CPagerHelper::paginate') !== false
		&& strpos($controller, "CPagerHelper::savePage('ztum.template.catalog'") !== false,
	'TemplateList must keep native Zabbix pagination wired through CPagerHelper.'
);

echo "Template catalog controller namespace contract tests passed.\n";
