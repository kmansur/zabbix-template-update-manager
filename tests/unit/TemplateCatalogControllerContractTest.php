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

foreach (['all', 'current', 'not_applicable', 'update_available', 'not_installed', 'never_update'] as $status) {
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
	strpos($controller, 'use Modules\\ZabbixTemplateUpdateManager\\Support\\ZabbixUiCompat;') !== false
		&& strpos($controller, "require_once dirname(__DIR__).'/src/Support/ZabbixUiCompat.php';") !== false
		&& strpos($controller, 'ZabbixUiCompat::pagerClass()') !== false
		&& strpos($controller, 'ZabbixUiCompat::pagerContainerClass()') !== false
		&& strpos($controller, "defined('ZBX_STYLE_PAGER')") === false
		&& strpos($controller, "defined('ZBX_STYLE_PAGING_BTN_CONTAINER')") === false,
	'Catalog pager compatibility must be delegated to ZabbixUiCompat instead of being implemented in the controller.'
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

assertCatalogControllerContract(
	strpos($view, "_('Source')") !== false
		&& strpos($view, "_('Commit')") !== false
		&& strpos($view, "_('Index cache')") !== false
		&& strpos($view, 'FrontendUi::fingerprint($catalogCommit, 12)') !== false
		&& strpos($view, 'FrontendUi::status($indexCacheLabel, $indexCacheTone)') !== false,
	'Official catalog source/commit/cache metadata must be integrated into the native catalog summary table.'
);

assertCatalogControllerContract(
	strpos($view, "if ((int) \$data['filtered_count'] > 0 && \$selectionGuidance !== '')") !== false
		&& strpos($view, "FrontendUi::section(_('Templates'))") !== false
		&& strpos($view, 'FrontendUi::status($selectionGuidance, FrontendUi::MUTED)') !== false,
	'Selection guidance must be concise, visually secondary and hidden when the filtered catalog is empty.'
);


echo "Template catalog controller/filter contracts passed.\n";
