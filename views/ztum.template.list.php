<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$compatibility = $data['zabbix_supported'] ? _('Supported') : _('Unsupported or undetected');

$upstreamLabels = [
	'official_match' => _('Official UUID match'),
	'official_catalog' => _('Official catalog'),
	'not_found' => _('Not found upstream'),
	'no_uuid' => _('No UUID'),
	'invalid_uuid' => _('Invalid UUID'),
	'repository_unavailable' => _('Repository unavailable')
];

$versionTones = [
	'current' => FrontendUi::SUCCESS,
	'update_available' => FrontendUi::WARNING,
	'not_installed' => FrontendUi::INFO,
	'installed_newer' => FrontendUi::INFO,
	'installed_version_missing' => FrontendUi::DANGER,
	'upstream_version_missing' => FrontendUi::DANGER,
	'version_uncomparable' => FrontendUi::WARNING,
	'not_applicable' => FrontendUi::MUTED
];

$upstreamTones = [
	'official_match' => FrontendUi::SUCCESS,
	'official_catalog' => FrontendUi::INFO,
	'not_found' => FrontendUi::MUTED,
	'no_uuid' => FrontendUi::WARNING,
	'invalid_uuid' => FrontendUi::DANGER,
	'repository_unavailable' => FrontendUi::DANGER
];

$versionLabels = [
	'current' => _('Current'),
	'update_available' => _('Update available'),
	'not_installed' => _('Not installed'),
	'installed_newer' => _('Installed version is newer'),
	'installed_version_missing' => _('Installed version missing'),
	'upstream_version_missing' => _('Upstream version missing'),
	'version_uncomparable' => _('Version format cannot be compared'),
	'not_applicable' => _('Not applicable')
];

$catalogUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');

$filter = (new CFilter())
	->setResetUrl($catalogUrl)
	->addVar('action', 'ztum.templates')
	->setProfile($data['filter_profile'])
	->setActiveTab($data['filter_active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())->addRow(
			_('Status'),
			(new CRadioButtonList('filter_status', (string) $data['filter']['status']))
				->addValue(_('All'), 'all')
				->addValue(_('Current'), 'current')
				->addValue(_('Not applicable'), 'not_applicable')
				->addValue(_('Update available'), 'update_available')
				->addValue(_('Not installed'), 'not_installed')
				->setModern(true)
		)
	]);

$localSummary = (new CTableInfo())
	->setHeader([
		_('Installed templates'),
		_('Zabbix vendor'),
		_('Other vendors'),
		_('No vendor metadata'),
		_('Linked to hosts')
	])
	->addRow([
		$data['summary']['total'],
		$data['summary']['zabbix_vendor'],
		$data['summary']['other_vendor'],
		$data['summary']['unidentified_vendor'],
		$data['summary']['in_use']
	]);

$catalogSummary = (new CTableInfo())
	->setHeader([_('Official catalog'), _('Installed'), _('Not installed'), _('Updates available')])
	->addRow([
		$data['catalog_summary']['official_catalog_total'],
		$data['catalog_summary']['official_installed'],
		$data['catalog_summary']['not_installed'],
		$data['version_summary']['update_available']
	]);

$installSelectionMode = $data['can_install'] && ($data['filter']['status'] ?? 'all') === 'not_installed';
$selectionForm = null;
$selectAllCheckbox = (new CCheckBox('all_templates'))
	->setEnabled(false)
	->setAttribute('title', _('Select all is unavailable for the current view.'));
$selectAllHeader = (new CColHeader($selectAllCheckbox))->addClass(ZBX_STYLE_CELL_WIDTH);

$canRenderSelectionForm = $installSelectionMode
	? $data['can_install']
	: ($data['can_compare'] && !empty($data['can_prepare_updates']));

if ($canRenderSelectionForm) {
	$selectionForm = (new CForm())
		->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
		->addItem((new CVar(
			CSRF_TOKEN_NAME,
			CCsrfTokenHelper::get(
				$installSelectionMode
					? 'ztum.templates.install_prepare_selected'
					: 'ztum.templates.prepare_selected'
			)
		))->removeId())
		->setId('ztum-template-list')
		->setName('ztum_template_list');

	$selectionNamespace = $installSelectionMode ? 'uuids' : 'templateids';

	$selectAllCheckbox = (new CCheckBox('all_templates'))
		->setEnabled(true)
		->onClick(
			"checkAll('".$selectionForm->getName()."', 'all_templates', '".$selectionNamespace."');"
		)
		->setAttribute(
			'title',
			$installSelectionMode
				? _('Select all visible Not installed templates.')
				: _('Select all visible update candidates.')
		);

	$selectAllHeader = (new CColHeader($selectAllCheckbox))->addClass(ZBX_STYLE_CELL_WIDTH);
}

$noData = match ((string) ($data['filter']['status'] ?? 'all')) {
	'current' => [_('No current official templates found.'), _('Change the status filter to view other templates.')],
	'update_available' => [_('No template updates are available.'), _('Installed official templates already match the current upstream catalog.')],
	'not_installed' => [_('No official templates are missing.'), _('All templates in the current official catalog are already installed.')],
	'not_applicable' => [_('No templates match this status.'), _('Change the status filter to view other templates.')],
	default => [_('No templates found.'), _('Change the filter or verify upstream catalog availability.')]
};

$templateTable = (new CTableInfo())
	->setNoDataMessage($noData[0], $noData[1])
	->setHeader([
		$selectAllHeader,
		_('Template'),
		_('Vendor'),
		_('Installed'),
		_('Available'),
		_('Status'),
		_('Upstream identity'),
		_('Linked hosts'),
		_('Action'),
		_('Backups'),
		_('UUID')
	])
	->setPageNavigation($data['paging']);

foreach ($data['templates'] as $template) {
	$isInstalled = ($template['installation_status'] ?? 'installed') === 'installed';
	$templateName = (string) $template['name'];
	if (($template['technical_name'] ?? '') !== '' && $template['technical_name'] !== $template['name']) {
		$templateName .= ' ('.$template['technical_name'].')';
	}

	$compareUrl = null;
	$installReviewUrl = null;

	if ($isInstalled && ($template['templateid'] ?? '') !== '') {
		$compareUrl = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.compare')
			->setArgument('templateid', $template['templateid']);
	}
	elseif (!$isInstalled) {
		$installReviewUrl = (new CUrl('zabbix.php'))
			->setArgument('action', 'ztum.template.install.review')
			->setArgument('uuid', $template['uuid']);
	}

	$templateCell = $templateName;
	if ($data['can_compare']) {
		if ($compareUrl !== null && ($template['upstream_status'] ?? null) === 'official_match') {
			$templateCell = new CLink($templateName, $compareUrl);
		}
		elseif ($installReviewUrl !== null) {
			$templateCell = new CLink($templateName, $installReviewUrl);
		}
	}

	$updateSelectionEligible = !$installSelectionMode
		&& $isInstalled
		&& $data['can_compare']
		&& !empty($data['can_prepare_updates'])
		&& ($template['upstream_status'] ?? null) === 'official_match'
		&& ($template['version_status'] ?? null) === 'update_available';

	$installSelectionEligible = $installSelectionMode
		&& !$isInstalled
		&& ($template['upstream_status'] ?? null) === 'official_catalog'
		&& ($template['version_status'] ?? null) === 'not_installed';

	$selectionEligible = $updateSelectionEligible || $installSelectionEligible;

	if ($installSelectionEligible) {
		$selectionCell = new CCheckBox('uuids['.$template['uuid'].']', $template['uuid']);
	}
	elseif ($updateSelectionEligible) {
		$selectionCell = new CCheckBox('templateids['.$template['templateid'].']', $template['templateid']);
	}
	else {
		$disabledKey = (string) ($template['uuid'] ?? $template['templateid'] ?? md5($templateName));
		$selectionCell = (new CCheckBox('ztum_disabled['.$disabledKey.']', '1'))
			->setEnabled(false)
			->setAttribute('title', _('This template is not eligible for the current bulk action.'));
	}

	$actionCell = '—';
	if ($selectionEligible && $compareUrl !== null) {
		$actionCell = new CLink(_('Review update'), $compareUrl);
	}
	elseif (!$isInstalled && $installReviewUrl !== null && $data['can_compare']) {
		$actionCell = new CLink(_('Review installation'), $installReviewUrl);
	}
	elseif ($isInstalled && $compareUrl !== null && $data['can_compare']
			&& ($template['upstream_status'] ?? null) === 'official_match') {
		$actionCell = new CLink(_('View'), $compareUrl);
	}

	$backupCell = '—';
	if ($isInstalled && $data['can_compare'] && ($template['templateid'] ?? '') !== '') {
		$backupCell = new CLink(
			_('View'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'ztum.template.backups')
				->setArgument('templateid', $template['templateid'])
		);
	}

	$templateTable->addRow([
		$selectionCell,
		$templateCell,
		$template['vendor_name'] !== '' ? $template['vendor_name'] : '—',
		$isInstalled && $template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
		($template['upstream_vendor_version'] ?? '') !== '' ? $template['upstream_vendor_version'] : '—',
		FrontendUi::status(
			$versionLabels[$template['version_status'] ?? 'not_applicable'] ?? _('Unknown'),
			$versionTones[$template['version_status'] ?? 'not_applicable'] ?? FrontendUi::MUTED
		),
		FrontendUi::status(
			$upstreamLabels[$template['upstream_status'] ?? 'repository_unavailable'] ?? _('Unknown'),
			$upstreamTones[$template['upstream_status'] ?? 'repository_unavailable'] ?? FrontendUi::MUTED
		),
		$isInstalled ? (int) $template['host_count'] : '—',
		$actionCell,
		$backupCell,
		$template['uuid'] !== '' ? FrontendUi::fingerprint($template['uuid'], 12) : '—'
	]);
}

if ($selectionForm !== null) {
	if ($installSelectionMode) {
		$actionButtons = new CActionButtonList('action', 'uuids', [
			'ztum.templates.install_prepare_selected' => [
				'name' => _('Prepare selected installations'),
				'attributes' => [
					'class' => ZBX_STYLE_BTN_ALT.' js-no-chkbxrange'
				]
			]
		], 'ztum_selected_installations');
	}
	else {
		$actionButtons = new CActionButtonList('action', 'templateids', [
			'ztum.templates.prepare_selected' => [
				'name' => _('Prepare selected updates'),
				'attributes' => [
					'class' => ZBX_STYLE_BTN_ALT.' js-no-chkbxrange'
				]
			]
		], 'ztum_selected_templates');
	}

	$selectionForm->addItem([$templateTable, $actionButtons]);
}

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->addItem(
		(new CList())
			->addClass(ZBX_STYLE_HOR_LIST)
			->addItem('ZTUM '.$data['version'])
			->addItem(_('Zabbix').' '.$data['zabbix_version'])
			->addItem(FrontendUi::status(
				$compatibility,
				$data['zabbix_supported'] ? FrontendUi::SUCCESS : FrontendUi::DANGER
			))
	);

$page->addItem(FrontendUi::message(
	_('Laboratory beta. Production use is not recommended. Validate backups and complete the field test plan before using this module on critical monitoring environments.'),
	FrontendUi::WARNING
));

if ($data['inventory_error'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['inventory_error'], FrontendUi::DANGER))->show();
	return;
}

$page
	->addItem(FrontendUi::section(_('Local inventory')))
	->addItem($localSummary);

if (is_array($data['upstream_source'])) {
	$upstreamCommit = isset($data['upstream_source']['commit'])
		? substr((string) $data['upstream_source']['commit'], 0, 12)
		: '—';

	$page
		->addItem(FrontendUi::section(_('Official catalog')))
		->addItem($catalogSummary)
		->addItem(
			(new CList())
				->addClass(ZBX_STYLE_HOR_LIST)
				->addItem(_('Zabbix').' '.(string) ($data['upstream_source']['line'] ?? '—'))
				->addItem([_('Commit').': ', FrontendUi::fingerprint($upstreamCommit, 12)])
				->addItem(_('Index cache').': '.(string) ($data['upstream_runtime']['cache_status'] ?? 'unknown'))
		);
}

if ($data['upstream_warning'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['upstream_warning'], FrontendUi::WARNING));
}

if ($data['upstream_error'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['upstream_error'], FrontendUi::DANGER));

	if (!empty($data['show_diagnostics']) && is_array($data['upstream_diagnostics'])) {
		$transports = is_array($data['upstream_diagnostics']['transports'] ?? null)
			? $data['upstream_diagnostics']['transports']
			: [];
		$page->addItem(
			(new CTableInfo())
				->setHeader([
					_('Requested index'),
					_('cURL'),
					_('allow_url_fopen'),
					_('OpenSSL'),
					_('Offline bundle'),
					_('Offline-only'),
					_('Failure detail')
				])
				->addRow([
					$data['upstream_diagnostics']['endpoint'] ?? '—',
					!empty($transports['curl']) ? _('Available') : _('Unavailable'),
					!empty($transports['allow_url_fopen']) ? _('Enabled') : _('Disabled'),
					!empty($transports['openssl']) ? _('Available') : _('Unavailable'),
					!empty($transports['offline_bundle']) ? _('Configured') : _('Not configured'),
					!empty($transports['offline_only']) ? _('Enabled') : _('Disabled'),
					$data['upstream_diagnostics']['detail'] ?? '—'
				])
		);
	}
}

$page
	->addItem($filter)
	->addItem(FrontendUi::description(_(
		$installSelectionMode
			? 'Select missing official templates to prepare a controlled installation. Preparation is read-only and blocked dependencies remain non-executable.'
			: 'Select official templates with newer upstream versions to prepare an update. Preparation is read-only; configuration changes require a later explicit confirmation.'
	)))
	->addItem(FrontendUi::section(_('Templates')));

if ($selectionForm !== null) {
	$page->addItem($selectionForm);
}
else {
	$page->addItem($templateTable);
}

$page->show();
