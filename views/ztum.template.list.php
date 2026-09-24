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
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', (string) $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				)
			])
			->addItem([
				new CLabel(_('Status'), 'filter_status'),
				new CFormField(
					(new CRadioButtonList('filter_status', (string) $data['filter']['status']))
						->addValue(_('All'), 'all')
						->addValue(_('Current'), 'current')
						->addValue(_('Not applicable'), 'not_applicable')
						->addValue(_('Update available'), 'update_available')
						->addValue(_('Not installed'), 'not_installed')
						->addValue(_('Never update'), 'never_update')
						->setModern(true)
				)
			])
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

$catalogSourceLine = is_array($data['upstream_source'])
	? trim((string) ($data['upstream_source']['line'] ?? ''))
	: '';
$catalogCommit = is_array($data['upstream_source'])
	? trim((string) ($data['upstream_source']['commit'] ?? ''))
	: '';
$indexCacheStatus = is_array($data['upstream_runtime'])
	? trim((string) ($data['upstream_runtime']['cache_status'] ?? ''))
	: '';
$indexCacheLabel = $indexCacheStatus !== ''
	? ucfirst(str_replace(['_', '-'], ' ', $indexCacheStatus))
	: '—';
$indexCacheTone = match ($indexCacheStatus) {
	'fresh' => FrontendUi::SUCCESS,
	'stale' => FrontendUi::WARNING,
	default => FrontendUi::MUTED
};

$catalogSummary = (new CTableInfo())
	->setHeader([
		_('Templates'),
		_('Installed'),
		_('Not installed'),
		_('Updates available'),
		_('Never update'),
		_('Source'),
		_('Commit'),
		_('Index cache')
	])
	->addRow([
		$data['catalog_summary']['official_catalog_total'],
		$data['catalog_summary']['official_installed'],
		$data['catalog_summary']['not_installed'],
		$data['version_summary']['update_available'],
		(int) ($data['policy_summary']['never_update'] ?? 0),
		$catalogSourceLine !== '' ? _('Zabbix').' '.$catalogSourceLine : '—',
		$catalogCommit !== '' ? FrontendUi::fingerprint($catalogCommit, 12) : '—',
		FrontendUi::status($indexCacheLabel, $indexCacheTone)
	]);

$filterStatus = (string) ($data['filter']['status'] ?? 'all');
$installSelectionMode = $data['can_install'] && $filterStatus === 'not_installed';
$allowUpdatesMode = !empty($data['can_manage_policy']) && $filterStatus === 'never_update';
$updatePrepareMode = !empty($data['can_prepare_updates']) && $filterStatus === 'update_available';
$policyMarkMode = !empty($data['can_manage_policy'])
	&& !$installSelectionMode
	&& !$allowUpdatesMode;

$selectionGuidance = match (true) {
	$installSelectionMode => _(
		'Select one or more missing official templates to prepare installation. Preparation is read-only; installation requires confirmation.'
	),
	$allowUpdatesMode => _(
		'Select one or more protected templates to allow ZTUM updates again.'
	),
	$updatePrepareMode => _(
		'Select update candidates to prepare an update or mark them Never update.'
	),
	$policyMarkMode => _(
		'Select installed official templates to mark Never update.'
	),
	default => ''
};

$selectionForm = null;
$selectAllCheckbox = (new CCheckBox('all_templates'))
	->setEnabled(false)
	->setAttribute('title', _('Select all is unavailable for the current view.'));
$selectAllHeader = (new CColHeader($selectAllCheckbox))->addClass(ZBX_STYLE_CELL_WIDTH);

$canRenderSelectionForm = $installSelectionMode || $allowUpdatesMode || $policyMarkMode;

if ($canRenderSelectionForm) {
	$selectionForm = (new CForm())
		->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
		->setId('ztum-template-list')
		->setName('ztum_template_list');

	$selectionNamespace = $installSelectionMode ? 'uuids' : 'templateids';

	if (!$installSelectionMode) {
		$selectionForm->addItem(
			(new CVar(
				'policy_operation',
				$allowUpdatesMode ? 'allow_updates' : 'never_update'
			))->removeId()
		);
	}

	$selectAllCheckbox = (new CCheckBox('all_templates'))
		->setEnabled(true)
		->onClick(
			"checkAll('".$selectionForm->getName()."', 'all_templates', '".$selectionNamespace."');"
		)
		->setAttribute(
			'title',
			match (true) {
				$installSelectionMode => _('Select all visible Not installed templates.'),
				$allowUpdatesMode => _('Select all visible Never update templates.'),
				$updatePrepareMode => _('Select all visible update candidates.'),
				default => _('Select all visible official templates.')
			}
		);

	$selectAllHeader = (new CColHeader($selectAllCheckbox))->addClass(ZBX_STYLE_CELL_WIDTH);
}

$filterName = trim((string) ($data['filter']['name'] ?? ''));

if ($filterName !== '') {
	$noData = [
		_('No templates match the current name filter.'),
		_('Change or clear the Name filter to view other templates.')
	];
}
else {
	$noData = match ((string) ($data['filter']['status'] ?? 'all')) {
		'current' => [_('No current official templates found.'), _('Change the status filter to view other templates.')],
		'update_available' => [_('No template updates are available.'), _('Installed official templates already match the current upstream catalog.')],
		'not_installed' => [_('No official templates are missing.'), _('All templates in the current official catalog are already installed.')],
		'never_update' => [_('No templates are marked Never update.'), _('Templates marked Never update will appear here.')],
		'not_applicable' => [_('No templates match this status.'), _('Change the status filter to view other templates.')],
		default => [_('No templates found.'), _('Change the filter or verify upstream catalog availability.')]
	};
}

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
		_('Update policy'),
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

	$officialInstalled = $isInstalled
		&& ($template['upstream_status'] ?? null) === 'official_match'
		&& trim((string) ($template['uuid'] ?? '')) !== '';
	$neverUpdate = !empty($template['never_update']);

	$updateSelectionEligible = $updatePrepareMode
		&& $officialInstalled
		&& !$neverUpdate
		&& ($template['version_status'] ?? null) === 'update_available';

	$policyMarkEligible = $policyMarkMode
		&& $officialInstalled
		&& !$neverUpdate;

	$allowUpdatesEligible = $allowUpdatesMode
		&& $isInstalled
		&& $neverUpdate
		&& trim((string) ($template['templateid'] ?? '')) !== ''
		&& trim((string) ($template['uuid'] ?? '')) !== '';

	$installSelectionEligible = $installSelectionMode
		&& !$isInstalled
		&& ($template['upstream_status'] ?? null) === 'official_catalog'
		&& ($template['version_status'] ?? null) === 'not_installed';

	$selectionEligible = $updateSelectionEligible
		|| $policyMarkEligible
		|| $allowUpdatesEligible
		|| $installSelectionEligible;

	if ($installSelectionEligible) {
		$selectionCell = new CCheckBox('uuids['.$template['uuid'].']', $template['uuid']);
	}
	elseif ($updateSelectionEligible || $policyMarkEligible || $allowUpdatesEligible) {
		$selectionCell = new CCheckBox('templateids['.$template['templateid'].']', $template['templateid']);
	}
	else {
		$disabledKey = (string) ($template['uuid'] ?? $template['templateid'] ?? md5($templateName));
		$selectionCell = (new CCheckBox('ztum_disabled['.$disabledKey.']', '1'))
			->setEnabled(false)
			->setAttribute('title', _('This template is not eligible for the current bulk action.'));
	}

	$actionCell = '—';
	if ($updateSelectionEligible && $compareUrl !== null) {
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
		FrontendUi::status(
			match ((string) ($template['update_policy'] ?? 'managed')) {
				'never_update' => _('Never update'),
				'unavailable' => _('Unavailable'),
				default => _('Managed')
			},
			match ((string) ($template['update_policy'] ?? 'managed')) {
				'never_update' => FrontendUi::WARNING,
				'unavailable' => FrontendUi::DANGER,
				default => FrontendUi::MUTED
			}
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
				'csrf_token' => CCsrfTokenHelper::get('ztum.templates.install_prepare_selected'),
				'attributes' => [
					'class' => ZBX_STYLE_BTN_ALT.' js-no-chkbxrange'
				]
			]
		], 'ztum_selected_installations');
	}
	elseif ($allowUpdatesMode) {
		$actionButtons = new CActionButtonList('action', 'templateids', [
			'ztum.templates.update_policy' => [
				'name' => _('Allow updates'),
				'csrf_token' => CCsrfTokenHelper::get('ztum.templates.update_policy'),
				'confirm_singular' => _('Allow ZTUM updates for the selected template?'),
				'confirm_plural' => _('Allow ZTUM updates for the selected templates?'),
				'attributes' => [
					'class' => ZBX_STYLE_BTN_ALT.' js-no-chkbxrange'
				]
			]
		], 'ztum_policy_allow_updates');
	}
	else {
		$buttons = [];

		if ($updatePrepareMode) {
			$buttons['ztum.templates.prepare_selected'] = [
				'name' => _('Prepare selected updates'),
				'csrf_token' => CCsrfTokenHelper::get('ztum.templates.prepare_selected'),
				'attributes' => [
					'class' => ZBX_STYLE_BTN_ALT.' js-no-chkbxrange'
				]
			];
		}

		if (!empty($data['can_manage_policy'])) {
			$buttons['ztum.templates.update_policy'] = [
				'name' => _('Never update'),
				'csrf_token' => CCsrfTokenHelper::get('ztum.templates.update_policy'),
				'confirm_singular' => _('Mark the selected template Never update?'),
				'confirm_plural' => _('Mark the selected templates Never update?'),
				'attributes' => [
					'class' => ZBX_STYLE_BTN_ALT.' js-no-chkbxrange'
				]
			];
		}

		$actionButtons = new CActionButtonList(
			'action',
			'templateids',
			$buttons,
			$updatePrepareMode ? 'ztum_selected_updates' : 'ztum_policy_never_update'
		);
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

$page
	->addItem(FrontendUi::section(_('Official catalog')))
	->addItem($catalogSummary);

if ($data['upstream_warning'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['upstream_warning'], FrontendUi::WARNING));
}

if ($data['policy_error'] !== null) {
	$page->addItem(FrontendUi::message((string) $data['policy_error'], FrontendUi::DANGER));
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
	->addItem(FrontendUi::section(_('Templates')));

if ((int) $data['filtered_count'] > 0 && $selectionGuidance !== '') {
	$page->addItem(FrontendUi::description(
		FrontendUi::status($selectionGuidance, FrontendUi::MUTED)
	));
}

if ($selectionForm !== null) {
	$page->addItem($selectionForm);
}
else {
	$page->addItem($templateTable);
}

$page->show();
