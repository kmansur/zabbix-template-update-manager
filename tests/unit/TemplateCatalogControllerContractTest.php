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

foreach (['CPagerHelper', 'CUrl', 'CProfile'] as $class) {
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
	strpos($view, "new CLabel(_('Status'), 'filter_status')") !== false
		&& substr_count($view, 'new CFormField(') >= 2,
	'Catalog Name and Status controls must use the native Zabbix CFormGrid label/field layout.'
);

assertCatalogControllerContract(
	strpos($controller, "'filter_name' => 'string'") !== false
		&& strpos($controller, "'web.ztum.templates.filter.name'") !== false
		&& strpos($controller, "CProfile::delete('web.ztum.templates.filter.name')") !== false
		&& strpos($view, 'new CFormGrid()') !== false
		&& strpos($view, 'CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE') !== false
		&& strpos($view, "new CLabel(_('Name'), 'filter_name')") !== false
		&& strpos($view, "new CTextBox('filter_name'") !== false
		&& strpos($view, "->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)") !== false,
	'Catalog name filtering must match the native Zabbix template-list filter layout.'
);

assertCatalogControllerContract(
	strpos($controller, "if (\$filterName !== '')") !== false
		&& strpos($controller, "\$technicalName = trim((string) (\$template['technical_name'] ?? ''));") !== false
		&& strpos($controller, "\$visibleName .= ' ('.\$technicalName.')';") !== false
		&& strpos($controller, "mb_stripos(\$visibleName, \$filterName)") !== false
		&& strpos($controller, "stripos(\$visibleName, \$filterName)") !== false
		&& strpos($controller, "if (\$filterName !== '')") < strpos($controller, 'CPagerHelper::paginate'),
	'Catalog name filtering must use case-insensitive partial matching against the complete visible template name before pagination.'
);

assertCatalogControllerContract(
	strpos($controller, "\$this->hasInput('filter_set') || \$this->hasInput('filter_rst')") !== false
		&& strpos($controller, "? 1") !== false,
	'Applying or resetting catalog filters must return native pagination to page 1.'
);

assertCatalogControllerContract(
	strpos($view, "No templates match the current name filter.") !== false
		&& strpos($view, "Change or clear the Name filter to view other templates.") !== false,
	'Catalog name filtering must provide a contextual native no-data message.'
);

assertCatalogControllerContract(
	strpos($controller, "'show_all' => 'in 1'") === false
		&& strpos($controller, "new CLink(_('All')") === false
		&& strpos($controller, "new CLink(_('Pages')") === false
		&& strpos($controller, 'ZabbixUiCompat') === false
		&& strpos($controller, 'ZBX_STYLE_TABLE_PAGING') === false
		&& strpos($controller, 'ZBX_STYLE_PAGING_BTN_CONTAINER') === false,
	'Catalog pagination must use only the native CPagerHelper output without custom All/Pages controls or pager-style compatibility code.'
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
