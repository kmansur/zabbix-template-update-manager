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

foreach ([$prepare, $update] as $controller) {
	assertBatchContract(strpos($controller, "'templateids' => 'required|array_id'") !== false,
		'Batch selection/execution actions must validate selected template IDs.');
}

assertBatchContract(strpos($prepareOne, "'templateid' => 'required|id'") !== false,
	'Per-template batch preparation must validate exactly one template ID.');
assertBatchContract(strpos($prepare, '->build(') === false,
	'Batch shell rendering must not synchronously prepare the full selected set.');
assertBatchContract(strpos($prepareOne, 'TemplateBatchPlanService') !== false
		&& strpos($prepareOne, 'build([$templateId], true)') !== false,
	'Per-template preparation must delegate exactly one candidate to TemplateBatchPlanService.');
assertBatchContract(strpos($update, 'TemplateBatchUpdateService') !== false,
	'Batch update action must delegate to TemplateBatchUpdateService.');
assertBatchContract(strpos($update, "'confirm' => 'required|in 1'") !== false,
	'Batch update must require explicit confirmation.');

assertBatchContract(strpos($manifest, '"ztum.templates.prepare_one"') !== false,
	'Manifest must register the per-template preparation action.');
assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.prepare_one')") !== false,
	'Per-template preparation requests must carry their action-specific CSRF token.');
assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.batch_update')") !== false,
	'Batch execution form must carry the native action-specific CSRF token.');
assertBatchContract(strpos($prepareView, 'fetch(config.prepareOneUrl') !== false,
	'Batch preparation view must drive one bounded HTTP request per template.');
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
