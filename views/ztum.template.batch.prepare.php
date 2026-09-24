<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$categoryLabels = [
	'ready' => _('Ready'),
	'review' => _('Manual review'),
	'conflict' => _('Conflict'),
	'blocked' => _('Blocked')
];

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(
					_('Back to template updates'),
					(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['error'] !== null || $data['templateids'] === [] || $data['templates'] === []) {
	$page->addItem(FrontendUi::message(
		(string) ($data['error'] ?? _('No templates were selected for preparation.')),
		FrontendUi::DANGER
	))->show();
	return;
}

$templateById = [];
foreach ($data['templates'] as $template) {
	$templateById[(string) $template['templateid']] = $template;
}

$selectedCount = count($data['templateids']);

$summaryTable = (new CTableInfo())
	->setHeader([_('Selected'), _('Completed'), _('Ready'), _('Manual review'), _('Conflict'), _('Blocked')])
	->addRow([
		$selectedCount,
		(new CSpan('0'))->setId('ztum-summary-completed'),
		(new CSpan('0'))->setId('ztum-summary-ready'),
		(new CSpan('0'))->setId('ztum-summary-review'),
		(new CSpan('0'))->setId('ztum-summary-conflict'),
		(new CSpan('0'))->setId('ztum-summary-blocked')
	]);

$progressText = (new CSpan(sprintf(_('Preparing %1$d of %2$d templates...'), 0, $selectedCount)))
	->setId('ztum-batch-progress-text');

$stopButton = (new CButton('ztum-batch-stop', _('Stop after current template')))
	->setId('ztum-batch-stop')
	->addClass(ZBX_STYLE_BTN_ALT);

$retryFailedButton = (new CButton('ztum-batch-retry-failed', _('Retry failed preparation')))
	->setId('ztum-batch-retry-failed')
	->setEnabled(false)
	->addClass(ZBX_STYLE_BTN_ALT);

$page
	->addItem(FrontendUi::section(_('Preparation')))
	->addItem($summaryTable)
	->addItem(new CDiv([
		$progressText,
		' ',
		$stopButton,
		' ',
		$retryFailedButton
	]))
	->addItem(FrontendUi::description(_(
		'Each template is analyzed in bounded requests. Preparation may create or refresh rollback evidence, but it does not import configuration.'
	)));

$selectAllReviewedButton = (new CButton('ztum-reviewed-select-all', _('Select all eligible')))
	->setId('ztum-reviewed-select-all')
	->addClass(ZBX_STYLE_BTN_ALT);

$clearReviewedButton = (new CButton('ztum-reviewed-clear-all', _('Clear reviewed selection')))
	->setId('ztum-reviewed-clear-all')
	->addClass(ZBX_STYLE_BTN_ALT);

$table = (new CTableInfo())
	->setHeader([
		_('Include'),
		_('Template'),
		_('Installed'),
		_('Available'),
		_('Linked hosts'),
		_('Readiness'),
		_('Review class'),
		_('Reason'),
		_('Execution')
	]);

foreach ($data['templateids'] as $templateId) {
	$template = $templateById[(string) $templateId] ?? [];
	$name = (string) ($template['name'] ?? $templateId);
	$installed = (string) ($template['vendor_version'] ?? '');
	$hostCount = (int) ($template['host_count'] ?? 0);

	$reviewCheckbox = (new CCheckBox('review_select['.$templateId.']', '1'))
		->setId('ztum-review-select-'.$templateId)
		->setEnabled(false)
		->setAttribute('title', _('Preparation has not classified this template yet.'));

	$table->addRow(
		(new CRow([
			$reviewCheckbox,
			$name,
			$installed !== '' ? $installed : '—',
			(new CSpan('—'))->setId('ztum-available-'.$templateId),
			$hostCount,
			(new CSpan(_('Pending')))->setId('ztum-readiness-'.$templateId),
			(new CSpan(_('Pending')))->setId('ztum-category-'.$templateId),
			(new CSpan('—'))->setId('ztum-reason-'.$templateId),
			(new CSpan(_('Pending')))->setId('ztum-execution-'.$templateId)
		]))->setId('ztum-row-'.$templateId)
	);
}

$page
	->addItem(FrontendUi::section(_('Prepared templates')))
	->addItem(new CDiv([
		$selectAllReviewedButton,
		' ',
		$clearReviewedButton
	]))
	->addItem($table);

$executionState = (new CSpan(_('Waiting for preparation.')))
	->setId('ztum-batch-execution-state');

$confirm = (new CCheckBox('confirm', '1'))
	->setId('ztum-batch-confirm')
	->setLabel(_('I reviewed the completed plan and accept the Manual review reasons for the selected templates.'))
	->setEnabled(false);

$confirmLocalOverwrite = (new CCheckBox('confirm_local_overwrite', '1'))
	->setId('ztum-batch-confirm-local-overwrite')
	->setLabel(_('I accept overwriting the identified local customizations for the selected templates.'))
	->setEnabled(false);

$submit = (new CButton('ztum-batch-update-submit', _('Update eligible templates')))
	->setId('ztum-batch-update-submit')
	->setEnabled(false);

$executionSummary = (new CTableInfo())
	->setHeader([_('Status'), _('Updated'), _('Failed'), _('Not attempted'), _('Any configuration write')])
	->addRow([
		(new CSpan(_('Waiting for preparation')))->setId('ztum-batch-exec-status'),
		(new CSpan('0'))->setId('ztum-batch-exec-updated'),
		(new CSpan('0'))->setId('ztum-batch-exec-failed'),
		(new CSpan('0'))->setId('ztum-batch-exec-not-attempted'),
		(new CSpan(_('No')))->setId('ztum-batch-exec-write')
	]);

$page
	->addItem(FrontendUi::section(_('Execution')))
	->addItem(FrontendUi::description(_(
		'Ready templates can be updated after preparation completes. Eligible Manual review rows require explicit selection. Each template receives a fresh preflight immediately before import; execution stops on the first failure and rollback is never automatic.'
	)))
	->addItem(FrontendUi::description($executionState))
	->addItem(new CDiv([$confirm]))
	->addItem(new CDiv([$confirmLocalOverwrite]))
	->addItem(new CDiv([$submit]))
	->addItem($executionSummary);

$prepareOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.prepare_one')
	->getUrl();

$executeOneUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.templates.batch_update_one')
	->getUrl();

$compareUrl = (new CUrl('zabbix.php'))
	->setArgument('action', 'ztum.template.compare')
	->getUrl();

$jsConfig = json_encode([
	'templateIds' => array_values(array_map('strval', $data['templateids'])),
	'prepareOneUrl' => $prepareOneUrl,
	'executeOneUrl' => $executeOneUrl,
	'compareUrl' => $compareUrl,
	'csrfName' => CSRF_TOKEN_NAME,
	'prepareCsrfToken' => CCsrfTokenHelper::get('ztum.templates.prepare_one'),
	'executeCsrfToken' => CCsrfTokenHelper::get('ztum.templates.batch_update_one'),
	'maxHistoricalContinuationRequests' => 12,
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
	'review' => _('Manual review'),
	'conflict' => _('Conflict'),
	'blocked' => _('Blocked'),
	'pending' => _('Pending'),
	'processing' => _('Processing...'),
	'history_continuing' => _('Continuing historical baseline scan ({attempt}/{max})...'),
	'request_failed' => _('Request failed'),
	'complete' => _('Preparation complete.'),
	'stopped' => _('Preparation stopped. Reload or return to the selection review to prepare the full set.'),
	'stopping' => _('Stop requested; the current template will finish first.'),
	'progress' => _('Preparing {current} of {total} templates...'),
	'execution_waiting' => _('Waiting for preparation.'),
	'execution_waiting_progress' => _('Waiting for preparation to finish — {completed} of {total} completed. You can select reviewed rows now; update execution starts only after preparation completes.'),
	'execution_available' => _('Available — {ready} Ready + {review} selected reviewed template(s).'),
	'execution_none' => _('Unavailable — no executable templates selected.'),
	'execution_review_only' => _('No unattended Ready templates. Select eligible Manual review rows below, or use Review details for individual handling.'),
	'execution_stopped' => _('Unavailable — preparation stopped.'),
	'retrying_failed' => _('Retrying failed preparation...'),
	'retry_complete' => _('Failed preparation retry complete.'),
	'execution_ready' => _('Ready for execution'),
	'select_reviewed' => _('Include reviewed update'),
	'select_all_reviewed' => _('Select all eligible reviewed updates'),
	'clear_all_reviewed' => _('Clear reviewed selection'),
	'review_batch_eligible' => _('Reviewed batch eligible'),
	'review_overwrite_eligible' => _('Reviewed overwrite eligible'),
	'review_details' => _('Review details'),
	'review_individual_only' => _('Individual review required'),
	'execution_running' => _('Running'),
	'execution_completed' => _('Completed'),
	'execution_failed' => _('Stopped on first failure'),
	'executing' => _('Updating...'),
	'updated' => _('Updated and validated'),
	'failed' => _('Failed'),
	'not_attempted' => _('Not attempted'),
	'yes' => _('Yes'),
	'no' => _('No'),
	'reason_historical_baseline_unavailable' => _('Historical baseline unavailable'),
	'reason_historical_baseline_ambiguous' => _('Historical baseline is ambiguous'),
	'reason_historical_baseline_time_budget_reached' => _('Historical baseline scan will continue'),
	'reason_historical_baseline_continuation_limit_reached' => _('Historical baseline scan limit reached'),
	'reason_invalid_preflight_evidence' => _('Invalid preflight evidence'),
	'reason_request_failed' => _('Request failed'),
	'reason_update_policy_never' => _('Template is marked Never update'),
	'reason_update_policy_unavailable' => _('Update policy is unavailable'),
	'reason_blocked_update_policy' => _('Blocked by update policy')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

$script = 'window.ZTUMUpdateBatchInit('.$jsConfig.', '.$jsLabels.');';

$page
	->addItem((new CScriptTag($script))->setOnDocumentReady())
	->show();
