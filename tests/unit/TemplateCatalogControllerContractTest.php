<?php

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root.'/actions/TemplateList.php');
$view = (string) file_get_contents($root.'/views/template.list.php');

function assertCatalogControllerContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach (['CPagerHelper', 'CUrl', 'CProfile', 'CDiv', 'CTag', 'CLink'] as $class) {
	assertCatalogControllerContract(
		preg_match('/^use '.preg_quote($class, '/').';$/m', $controller) === 1,
		'TemplateList must import native global Zabbix class '.$class.'.'
	);
}

assertCatalogControllerContract(
	strpos($controller, 'CPagerHelper::paginate') !== false
		&& strpos($controller, "CPagerHelper::savePage('ztum.template.catalog'") !== false,
	'TemplateList must keep native Zabbix pagination wired through CPagerHelper.'
);

foreach (['all', 'current', 'not_applicable', 'update_available', 'not_installed'] as $status) {
	assertCatalogControllerContract(
		strpos($controller, $status) !== false && strpos($view, "'".$status."'") !== false,
		'Catalog status filter is missing option '.$status.'.'
	);
}

assertCatalogControllerContract(
	strpos($view, 'new CFilter()') !== false
		&& strpos($view, "new CRadioButtonList('filter_status'") !== false
		&& strpos($view, '->setModern(true)') !== false,
	'Catalog status filtering must use native Zabbix filter/radio controls.'
);

assertCatalogControllerContract(
	strpos($controller, "'show_all' => 'in 1'") !== false
		&& strpos($controller, "new CLink(_('All')") !== false
		&& strpos($controller, "new CLink(_('Pages')") !== false,
	'Catalog pager must provide reversible All/Pages display mode.'
);

echo "Template catalog controller/filter contracts passed.\n";
