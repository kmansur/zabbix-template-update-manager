<?php

$root = dirname(__DIR__, 2);
$prepare = (string) file_get_contents($root.'/actions/TemplateBatchPrepare.php');
$update = (string) file_get_contents($root.'/actions/TemplateBatchUpdate.php');
$prepareView = (string) file_get_contents($root.'/views/template.batch.prepare.php');
$updateView = (string) file_get_contents($root.'/views/template.batch.update.php');
$manifest = (string) file_get_contents($root.'/manifest.json');

function assertBatchContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach ([$prepare, $update] as $controller) {
	assertBatchContract(strpos($controller, 'disableCsrfValidation') === false,
		'Batch actions must keep native CSRF validation enabled.');
	assertBatchContract(strpos($controller, 'USER_TYPE_SUPER_ADMIN') !== false,
		'Batch preparation and execution must be super-admin-only.');
}

assertBatchContract(strpos($prepare, "'templateids' => 'array_id'") !== false,
	'Batch preparation shell must validate the selected template ID array.');
assertBatchContract(strpos($prepare, "'templateid' => 'id'") !== false
		&& strpos($prepare, "'async' => 'in 1'") !== false,
	'Per-template preparation mode must validate one template ID and explicit async mode.');
assertBatchContract(strpos($prepare, 'build([$templateId], true)') !== false,
	'Per-template asynchronous preparation must delegate exactly one candidate to TemplateBatchPlanService.');
assertBatchContract(strpos($prepare, 'if ((int) $this->getInput(\'async\', 0) === 1)') !== false,
	'The existing preparation action must explicitly branch between shell and one-template JSON modes.');
assertBatchContract(strpos($prepare, 'disableView()') !== false,
	'Per-template asynchronous preparation must return raw main_block JSON without a normal HTML view.');
assertBatchContract(strpos($prepare, 'TemplateBatchPlanService())->build($templateIds') === false,
	'Batch shell rendering must never synchronously prepare the complete selected set.');

assertBatchContract(strpos($update, "'templateids' => 'required|array_id'") !== false,
	'Batch execution must validate selected template IDs.');
assertBatchContract(strpos($update, 'TemplateBatchUpdateService') !== false,
	'Batch update action must delegate to TemplateBatchUpdateService.');
assertBatchContract(strpos($update, "'confirm' => 'required|in 1'") !== false,
	'Batch update must require explicit confirmation.');

assertBatchContract(strpos($manifest, '"ztum.templates.prepare_one"') === false,
	'Batch queue must not depend on a second module route for per-template preparation.');
assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.prepare_selected')") !== false,
	'Per-template queue requests must reuse the registered preparation action CSRF token.');
assertBatchContract(strpos($prepareView, "body.append('async', '1')") !== false,
	'Queue requests must explicitly select one-template asynchronous mode.');
assertBatchContract(strpos($prepareView, 'fetch(config.prepareSelectedUrl') !== false,
	'Batch preparation view must drive one bounded HTTP request per template through the existing action.');
assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.batch_update')") !== false,
	'Batch execution form must carry the native action-specific CSRF token.');
assertBatchContract(strpos($prepareView, 'for (let index = 0; index < config.templateIds.length; index++)') !== false,
	'Batch preparation queue must be sequential and bounded.');
assertBatchContract(strpos($prepareView, 'Stop after current template') !== false,
	'Batch preparation queue must be cancellable between templates.');
assertBatchContract(strpos($prepareView, 'Update ready templates') !== false,
	'Batch preparation view must expose only the controlled ready-template action.');
assertBatchContract(strpos($prepareView, 'execution stops on the first failure') !== false
		|| strpos($prepareView, 'stops on the first') !== false,
	'Batch preparation view must disclose stop-on-first-failure behavior.');
assertBatchContract(strpos($updateView, 'No automatic rollback is performed') !== false,
	'Batch result view must explicitly state that rollback is not automatic.');

$combined = $prepare.$update.$prepareView.$updateView;
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
