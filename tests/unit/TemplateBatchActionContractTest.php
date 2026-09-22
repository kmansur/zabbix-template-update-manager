<?php

$root = dirname(__DIR__, 2);
$prepare = (string) file_get_contents($root.'/actions/TemplateBatchPrepare.php');
$prepareOne = (string) file_get_contents($root.'/actions/TemplateBatchPrepareOne.php');
$update = (string) file_get_contents($root.'/actions/TemplateBatchUpdate.php');
$updateOne = (string) file_get_contents($root.'/actions/TemplateBatchUpdateOne.php');
$prepareView = (string) file_get_contents($root.'/views/template.batch.prepare.php');
$updateView = (string) file_get_contents($root.'/views/template.batch.update.php');
$manifest = (string) file_get_contents($root.'/manifest.json');

function assertBatchContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach ([$prepare, $prepareOne, $update, $updateOne] as $controller) {
	assertBatchContract(strpos($controller, 'disableCsrfValidation') === false,
		'Batch actions must keep native CSRF validation enabled.');
	assertBatchContract(strpos($controller, 'USER_TYPE_SUPER_ADMIN') !== false,
		'Batch preparation and execution must be super-admin-only.');
}

assertBatchContract(strpos($prepare, "'templateids' => 'required|array_id'") !== false,
	'Batch shell must validate the selected template ID array.');
assertBatchContract(strpos($prepare, '->build(') === false,
	'Batch shell rendering must not synchronously prepare the full selected set.');

assertBatchContract(strpos($prepareOne, "'templateid' => 'required|id'") !== false,
	'Per-template preparation must validate exactly one template ID.');
assertBatchContract(strpos($prepareOne, 'build([$templateId], true)') !== false,
	'Per-template preparation must delegate exactly one candidate to TemplateBatchPlanService.');
assertBatchContract(strpos($prepareOne, 'disableView()') !== false,
	'Per-template controller must return raw main_block data to the JSON layout.');

assertBatchContract(strpos($manifest, '"ztum.templates.prepare_one"') !== false
		&& strpos($manifest, '"layout": "layout.json"') !== false
		&& strpos($manifest, '"view": null') !== false,
	'Per-template preparation route must be registered explicitly with layout.json and no view.');

assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.prepare_one')") !== false,
	'Queue requests must use the per-template action CSRF token.');
assertBatchContract(strpos($prepareView, 'fetch(config.prepareOneUrl') !== false,
	'Batch preparation view must drive one bounded HTTP request per template.');
assertBatchContract(strpos($prepareView, 'for (let index = 0; index < config.templateIds.length; index++)') !== false,
	'Batch preparation queue must be sequential and bounded.');
assertBatchContract(strpos($prepareView, 'Stop after current template') !== false,
	'Batch preparation queue must be cancellable between templates.');
assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.batch_update_one')") !== false,
	'Request-bounded batch execution must carry its per-template action CSRF token.');
assertBatchContract(strpos($prepareView, 'fetch(config.executeOneUrl') !== false,
	'Batch update execution must use one bounded HTTP request per Ready template.');
assertBatchContract(strpos($prepareView, 'for (let index = 0; index < entries.length; index++)') !== false,
	'Batch update execution queue must remain sequential in the browser.');
assertBatchContract(strpos($prepareView, "result.status !== 'updated'") !== false
		&& strpos($prepareView, 'labels.not_attempted') !== false,
	'Request-bounded execution must stop on first non-success and mark later templates not attempted.');
assertBatchContract(strpos($prepareView, "category === 'ready' && !isValidEvidence(evidence)") !== false
		&& strpos($prepareView, "reason = 'invalid_preflight_evidence'") !== false,
	'Browser batch state must fail closed when a server-reported Ready row lacks valid SHA-256 evidence.');
assertBatchContract(strpos($prepareView, 'const executionCount = readyEvidence.size + selectedReviewed;') !== false
		&& strpos($prepareView, 'fullyPrepared && executionCount > 0') !== false,
	'Completed plans must enable execution for Ready candidates plus explicitly selected reviewed overrides.');
assertBatchContract(strpos($prepareView, "if (category === 'ready') {") !== false
		&& strpos($prepareView, 'readyEvidence.set(templateId, evidence);') !== false,
	'Only Ready candidates may enter the request-bounded execution set.');
assertBatchContract(strpos($prepareView, 'Unavailable — no executable templates selected.') !== false
		&& strpos($prepareView, 'Available — {ready} Ready + {review} selected reviewed template(s).') !== false
		&& strpos($prepareView, 'Unavailable — preparation stopped.') !== false,
	'Batch execution availability must be explicit for Ready, selected reviewed, zero-executable and stopped plans.');
assertBatchContract(strpos($prepareView, "setText('ztum-batch-exec-status', labels.execution_none);") !== false,
	'Zero-Ready plans must synchronize the execution summary status instead of leaving Waiting for preparation.');
assertBatchContract(strpos($prepareView, 'Retry failed preparation') !== false
		&& strpos($prepareView, 'requestFailures = new Set()') !== false
		&& strpos($prepareView, 'retryFailedPreparation = async () =>') !== false,
	'Failed per-template preparation must expose an explicit operator-controlled retry path.');
assertBatchContract(strpos($prepareView, "throw new Error('HTTP ' + response.status + ' after '") !== false,
	'Preparation transport failures must include elapsed-request timing for field diagnostics.');
assertBatchContract(strpos($prepareView, "'compareUrl' => \$compareUrl") !== false
		&& strpos($prepareView, "link.href = config.compareUrl + '&templateid=' + encodeURIComponent(templateId);") !== false
		&& strpos($prepareView, "link.textContent = labels.review_details;") !== false,
	'Manual-review rows must retain a direct Review details link to the individual comparison flow.');
assertBatchContract(strpos($prepareView, "new CSpan('—'))->setId('ztum-select-'.\$templateId)") !== false
		&& strpos($prepareView, "checkbox.id = 'ztum-review-select-' + templateId;") !== false
		&& strpos($prepareView, 'setReviewedSelection(templateId, category, manualState);') !== false
		&& strpos($prepareView, 'reviewEvidence.set(templateId') !== false,
	'Eligible technical-risk Manual review rows must expose an explicit reviewed-update checkbox in the leading selection column.');
assertBatchContract(strpos($prepareView, 'No unattended Ready templates. Select eligible Manual review rows below') !== false
		&& strpos($prepareView, 'else if (counts.review > 0)') !== false,
	'Review-only plans must explain how to continue with explicit reviewed selection.');
assertBatchContract(strpos($prepareView, "body.append('manual_override', '1')") !== false
		&& strpos($prepareView, "body.append('confirm_manual_override', '1')") !== false,
	'Reviewed batch execution must send both explicit manual-override signals.');
assertBatchContract(strpos($prepareView, 'const reviewed = new Map(selectedReviewedEntries()') !== false,
	'Ready and explicitly selected reviewed rows must be merged into one ordered request-bounded execution queue.');
assertBatchContract(strpos($prepareView, "new CCheckBox('ztum-reviewed-select-all', '1')") !== false
		&& strpos($prepareView, "byId('ztum-reviewed-select-all').addEventListener('change'") !== false
		&& strpos($prepareView, 'const updateReviewedSelectAll = () =>') !== false
		&& strpos($prepareView, 'master.indeterminate = selected > 0 && selected < eligible.length;') !== false,
	'The leading reviewed-selection column must provide select-all with checked/indeterminate synchronization.');
assertBatchContract(strpos($prepareView, "->setId('ztum-reviewed-select-all');") !== false
		&& strpos($prepareView, "->setId('ztum-reviewed-select-all')\n\t->setEnabled(false)") === false,
	'Reviewed select-all must be clickable while preparation is still running.');
assertBatchContract(strpos($prepareView, 'let autoSelectReviewed = false;') !== false
		&& strpos($prepareView, 'checkbox.checked = autoSelectReviewed;') !== false
		&& strpos($prepareView, 'autoSelectReviewed = checked;') !== false,
	'Select-all chosen during preparation must automatically include reviewed rows that become eligible later.');
assertBatchContract(strpos($prepareView, 'master.disabled = executionStarted || retryInProgress;') !== false
		&& strpos($prepareView, 'eligible.length === 0') === false,
	'Select-all must not be disabled merely because no eligible reviewed row has completed preparation yet.');
assertBatchContract(strpos($prepareView, 'labels.review_batch_eligible') !== false
		&& strpos($prepareView, 'labels.review_individual_only') !== false,
	'Execution state must distinguish reviewed-batch-eligible rows from individual-review-only rows.');

assertBatchContract(strpos($update, "'templateids' => 'required|array_id'") !== false,
	'Legacy batch execution must validate selected template IDs.');
assertBatchContract(strpos($update, '$count === 1') !== false,
	'Legacy synchronous batch execution must fail fast for more than one template.');
assertBatchContract(strpos($updateOne, "'templateid' => 'required|id'") !== false,
	'Request-bounded execution must validate exactly one template ID.');
assertBatchContract(strpos($updateOne, "'evidence_sha256' => 'required|string'") !== false
		&& strpos($updateOne, "'confirm' => 'required|in 1'") !== false
		&& strpos($updateOne, "'manual_override' => 'in 1'") !== false
		&& strpos($updateOne, "'confirm_manual_override' => 'in 1'") !== false,
	'Request-bounded execution must require bound evidence and explicit confirmation, including a second acknowledgement for reviewed overrides.');
assertBatchContract(strpos($updateOne, 'TemplateControlledUpdateService') !== false,
	'Request-bounded execution must reuse TemplateControlledUpdateService.');
assertBatchContract(strpos($manifest, '"ztum.templates.batch_update_one"') !== false
		&& strpos($manifest, '"layout": "layout.json"') !== false
		&& strpos($manifest, '"view": null') !== false,
	'Request-bounded update route must be registered with layout.json and no view.');
assertBatchContract(strpos($updateView, 'No automatic rollback is performed') !== false,
	'Batch result view must state that rollback is not automatic.');

$combined = $prepare.$prepareOne.$update.$updateOne.$prepareView.$updateView;
foreach ([
	'API::Configuration()->import(',
	'API::Template()->update(',
	'API::Template()->delete(',
	'DB::update(',
	'DB::delete('
] as $fragment) {
	assertBatchContract(strpos($combined, $fragment) === false,
		'Batch controllers/views must not introduce a second configuration-write boundary: '.$fragment);
}

echo "Template batch action contract tests passed.\n";
