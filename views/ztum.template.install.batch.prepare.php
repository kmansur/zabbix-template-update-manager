<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(
					_('Back to template catalog'),
					(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['uuids'] === []) {
	$page->addItem(FrontendUi::message(_('No templates were selected for installation preparation.'), FrontendUi::DANGER))->show();
	return;
}

$selectedCount = count($data['uuids']);

$summaryTable = (new CTableInfo())
	->setHeader([_('Selected'), _('Completed'), _('Ready'), _('Blocked')])
	->addRow([
		$selectedCount,
		(new CSpan('0'))->setId('ztum-install-summary-completed'),
		(new CSpan('0'))->setId('ztum-install-summary-ready'),
		(new CSpan('0'))->setId('ztum-install-summary-blocked')
	]);

$progressText = (new CSpan(sprintf(_('Preparing %1$d of %2$d installations...'), 0, $selectedCount)))
	->setId('ztum-install-batch-progress-text');

$stopButton = (new CButton('ztum-install-batch-stop', _('Stop after current template')))
	->setId('ztum-install-batch-stop')
	->addClass(ZBX_STYLE_BTN_ALT);

$page
	->addItem(FrontendUi::section(_('Installation preparation')))
	->addItem($summaryTable)
	->addItem(new CDiv([$progressText, ' ', $stopButton]))
	->addItem(FrontendUi::description(_(
		'Each template is analyzed in its own request. Preparation is read-only and does not import configuration.'
	)))
	->addItem(FrontendUi::description(_(
		'Templates with missing dependencies remain Blocked. Install the required dependencies first, then prepare the dependent template again.'
	)));

$table = (new CTableInfo())
	->setHeader([
		_('Template'),
		_('Available'),
		_('Preflight'),
		_('Review class'),
		_('Required dependencies'),
		_('Missing dependencies'),
		_('Reason'),
		_('Execution')
	]);

foreach ($data['uuids'] as $uuid) {
	$table->addRow(
		(new CRow([
			(new CSpan($uuid))->setId('ztum-install-name-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-version-'.$uuid),
			(new CSpan(_('Pending')))->setId('ztum-install-preflight-'.$uuid),
			(new CSpan(_('Pending')))->setId('ztum-install-category-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-required-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-missing-'.$uuid),
			(new CSpan('—'))->setId('ztum-install-reason-'.$uuid),
			(new CSpan(_('Pending')))->setId('ztum-install-execution-'.$uuid)
		]))->setId('ztum-install-row-'.$uuid)
	);
}

$page
	->addItem(FrontendUi::section(_('Prepared templates')))
	->addItem($table);

$confirm = (new CCheckBox('confirm', '1'))
	->setId('ztum-install-batch-confirm')
	->setLabel(_('I reviewed the completed plan and want to install the Ready templates.'))
	->setEnabled(false);

$submit = (new CButton('ztum-install-batch-submit', _('Install ready templates')))
	->setId('ztum-install-batch-submit')
	->setEnabled(false);

$noReadyMessage = (new CSpan(''))
	->setId('ztum-install-no-ready-message');

$executionSummary = (new CTableInfo())
	->setHeader([
		_('Status'),
		_('Installed'),
		_('Failed'),
		_('Not attempted'),
		_('Confirmed configuration write'),
		_('Uncertain import')
	])
	->addRow([
		(new CSpan(_('Waiting for preparation')))->setId('ztum-install-exec-status'),
		(new CSpan('0'))->setId('ztum-install-exec-installed'),
		(new CSpan('0'))->setId('ztum-install-exec-failed'),
		(new CSpan('0'))->setId('ztum-install-exec-not-attempted'),
		(new CSpan(_('No')))->setId('ztum-install-exec-write'),
		(new CSpan('0'))->setId('ztum-install-exec-uncertain')
	]);

$executionNotice = (new CSpan(''))
	->setId('ztum-install-exec-notice');

$page
	->addItem(FrontendUi::section(_('Execution')))
	->addItem(FrontendUi::description(_(
		'Only Ready templates can be installed. Each template receives a fresh preflight immediately before import and is validated before the next template. Execution stops on the first failure and uninstall is never automatic.'
	)))
	->addItem(new CDiv([$confirm, ' ', $submit]))
	->addItem(new CDiv($noReadyMessage))
	->addItem($executionSummary)
	->addItem(new CDiv($executionNotice));

$prepareOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.install_prepare_one')
	->getUrl();

$executeOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.install_execute_one')
	->getUrl();

$jsConfig = json_encode([
	'uuids' => array_values(array_map('strval', $data['uuids'])),
	'prepareOneUrl' => $prepareOneUrl,
	'executeOneUrl' => $executeOneUrl,
	'csrfName' => CSRF_TOKEN_NAME,
	'prepareCsrfToken' => CCsrfTokenHelper::get('ztum.templates.install_prepare_one'),
	'executeCsrfToken' => CCsrfTokenHelper::get('ztum.templates.install_execute_one'),
	'statusClasses' => [
		'success' => ZBX_STYLE_GREEN,
		'warning' => ZBX_STYLE_ORANGE,
		'danger' => ZBX_STYLE_RED,
		'info' => ZBX_STYLE_BLUE,
		'muted' => ZBX_STYLE_GREY
	]
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$jsLabels = json_encode([
	'ready' => _('Ready'),
	'blocked' => _('Blocked'),
	'pending' => _('Pending'),
	'processing' => _('Processing...'),
	'request_failed' => _('Request failed'),
	'complete' => _('Preparation complete.'),
	'stopped' => _('Preparation stopped. Reload or return to the catalog to prepare the full set.'),
	'stopping' => _('Stop requested; the current template will finish first.'),
	'progress' => _('Preparing {current} of {total} installations...'),
	'execution_ready' => _('Ready for execution'),
	'execution_running' => _('Running'),
	'execution_completed' => _('Completed'),
	'execution_stopped' => _('Stopped on first failure'),
	'execution_stopped_uncertain' => _('Stopped — inspect uncertain import state'),
	'executing' => _('Installing...'),
	'installed' => _('Installed and validated'),
	'import_failed' => _('Import failed — inspect state'),
	'validation_failed' => _('Installed but validation failed'),
	'validation_reasons' => _('Validation reasons'),
	'remaining_differences' => _('remaining differences'),
	'raw_differences' => _('raw comparison differences'),
	'ignored_shared_differences' => _('ignored shared-group differences'),
	'create_only_validated' => _('Validated with create-only installation policy'),
	'failed' => _('Failed'),
	'not_attempted' => _('Not attempted'),
	'yes' => _('Yes'),
	'no' => _('No'),
	'import_failure_notice' => _('The failed import reached the controlled Zabbix import stage but did not return confirmed success. Remaining templates were not attempted. Inspect the catalog/local template state before any retry.'),
	'request_failure_notice' => _('The execution request did not complete cleanly. Its write outcome cannot be proven from the browser response, so remaining templates were not attempted. Inspect local template state before any retry.'),
	'execution_unavailable' => _('Unavailable — no Ready templates'),
	'no_ready' => _('No templates are eligible for installation. {blocked} selected template(s) were blocked during safety analysis. Review the blocked reasons above or return to the catalog.'),
	'reason_unsupported_zabbix_version' => _('Unsupported Zabbix version'),
	'reason_official_template_not_found' => _('Official template not found'),
	'reason_template_already_installed' => _('Template is already installed'),
	'reason_technical_name_collision' => _('Technical name is already used by another template'),
	'reason_missing_template_dependencies' => _('Required template dependencies are missing'),
	'reason_unresolved_internal_references' => _('Template references could not be resolved safely'),
	'reason_install_would_modify_existing_configuration' => _('Installation would modify existing configuration'),
	'reason_install_preview_contains_no_creations' => _('Installation preview contains no new objects'),
	'reason_post_install_validation_failed' => _('Post-install validation failed'),
	'reason_content_not_current_upstream' => _('Installed content does not match current upstream'),
	'reason_remaining_import_differences' => _('Import differences remain after validation')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$script = 'window.ZTUMInstallBatchInit('.$jsConfig.', '.$jsLabels.');';

$page
	->addItem((new CScriptTag($script))->setOnDocumentReady())
	->show();
