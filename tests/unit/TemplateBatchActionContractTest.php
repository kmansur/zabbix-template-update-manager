<?php

$root = dirname(__DIR__, 2);
$prepare = (string) file_get_contents($root.'/actions/TemplateBatchPrepare.php');
$update = (string) file_get_contents($root.'/actions/TemplateBatchUpdate.php');
$prepareView = (string) file_get_contents($root.'/views/template.batch.prepare.php');
$updateView = (string) file_get_contents($root.'/views/template.batch.update.php');

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
	assertBatchContract(strpos($controller, "'templateids' => 'required|array_id'") !== false,
		'Batch actions must validate selected template IDs.');
}

assertBatchContract(strpos($prepare, 'TemplateBatchPlanService') !== false,
	'Batch preparation must delegate to TemplateBatchPlanService.');
assertBatchContract(strpos($update, 'TemplateBatchUpdateService') !== false,
	'Batch update action must delegate to TemplateBatchUpdateService.');
assertBatchContract(strpos($update, "'confirm' => 'required|in 1'") !== false,
	'Batch update must require explicit confirmation.');

assertBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.batch_update')") !== false,
	'Batch execution form must carry the native action-specific CSRF token.');
assertBatchContract(strpos($prepareView, 'Update ready templates') !== false,
	'Batch preparation view must expose only the controlled ready-template action.');
assertBatchContract(strpos($prepareView, 'stops on the first failure') !== false || strpos($prepareView, 'stops on the first') !== false,
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
