<?php

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root.'/actions/TemplateList.php');
$view = (string) file_get_contents($root.'/views/ztum.template.list.php');

function assertCatalogControllerContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach (['CPagerHelper', 'CUrl', 'CProfile', 'CDiv', 'CTag', 'CLink', 'CWebUser'] as $class) {
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

assertCatalogControllerContract(
	strpos($controller, "\$needsPagination = \$data['filtered_count'] > \$rowsPerPage;") !== false
		&& strpos($controller, "if (!\$needsPagination)") !== false
		&& strpos($controller, "if (\$needsPagination) {") !== false,
	'Catalog All/Pages controls must be suppressed when the filtered result fits on one native Zabbix page.'
);

assertCatalogControllerContract(
	strpos($view, "new CCheckBox('all_templates')") !== false
		&& strpos($view, '->setEnabled(true)') !== false
		&& strpos($view, "checkAll('") !== false
		&& strpos($view, 'installation batch limit is 25 templates') === false,
	'Catalog select-all must remain enabled for the request-bounded missing-template installation workflow.'
);

assertCatalogControllerContract(
	strpos($view, "new CCheckBox('ztum_disabled[") !== false
		&& strpos($view, "This template is not eligible for the current bulk action.") !== false,
	'Rows that are not eligible for the active bulk action must render a disabled checkbox instead of an empty cell.'
);


echo "Template catalog controller/filter contracts passed.\n";
