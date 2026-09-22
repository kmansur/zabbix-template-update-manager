<?php

$root = dirname(__DIR__, 2);
$prepare = (string) file_get_contents($root.'/actions/TemplateBatchPrepare.php');
$prepareOne = (string) file_get_contents($root.'/actions/TemplateBatchPrepareOne.php');
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

foreach ([$prepare, $prepareOne, $update] as $controller) {
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
assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.batch_update')") !== false,
	'Batch execution form must carry its action-specific CSRF token.');

assertBatchContract(strpos($update, "'templateids' => 'required|array_id'") !== false,
	'Batch execution must validate selected template IDs.');
assertBatchContract(strpos($update, "'confirm' => 'required|in 1'") !== false,
	'Batch execution must require explicit confirmation.');
assertBatchContract(strpos($update, 'TemplateBatchUpdateService') !== false,
	'Batch execution must delegate to TemplateBatchUpdateService.');
assertBatchContract(strpos($updateView, 'No automatic rollback is performed') !== false,
	'Batch result view must state that rollback is not automatic.');

$combined = $prepare.$prepareOne.$update.$prepareView.$updateView;
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
