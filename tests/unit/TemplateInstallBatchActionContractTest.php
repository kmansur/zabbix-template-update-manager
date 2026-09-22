<?php

$root = dirname(__DIR__, 2);
$prepare = (string) file_get_contents($root.'/actions/TemplateInstallBatchPrepare.php');
$prepareOne = (string) file_get_contents($root.'/actions/TemplateInstallBatchPrepareOne.php');
$install = (string) file_get_contents($root.'/actions/TemplateInstallBatch.php');
$prepareView = (string) file_get_contents($root.'/views/template.install.batch.prepare.php');
$resultView = (string) file_get_contents($root.'/views/template.install.batch.php');
$listView = (string) file_get_contents($root.'/views/template.list.php');
$manifest = (string) file_get_contents($root.'/manifest.json');

function assertInstallBatchContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach ([$prepare, $prepareOne, $install] as $controller) {
	assertInstallBatchContract(strpos($controller, 'disableCsrfValidation') === false,
		'Batch installation actions must keep native CSRF validation enabled.');
	assertInstallBatchContract(strpos($controller, 'USER_TYPE_SUPER_ADMIN') !== false,
		'Batch installation preparation/execution must be Super Admin only.');
}

assertInstallBatchContract(strpos($prepare, "'uuids' => 'required|array'") !== false,
	'Batch install shell must validate a UUID array.');
assertInstallBatchContract(strpos($prepareOne, "'uuid' => 'required|string'") !== false,
	'Per-template install preparation must validate exactly one UUID.');
assertInstallBatchContract(strpos($prepareOne, '->build([$uuid])') !== false,
	'Per-template install preparation must prepare exactly one candidate.');
assertInstallBatchContract(strpos($prepareOne, 'disableView()') !== false,
	'Per-template install preparation must return raw JSON-layout data.');

assertInstallBatchContract(
	strpos($manifest, '"ztum.templates.install_prepare_one"') !== false
		&& strpos($manifest, '"layout": "layout.json"') !== false,
	'Per-template install preparation route must use layout.json.'
);

assertInstallBatchContract(
	strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.install_prepare_one')") !== false
		&& strpos($prepareView, 'fetch(config.prepareOneUrl') !== false
		&& strpos($prepareView, 'for (let index = 0; index < config.uuids.length; index++)') !== false,
	'Batch install preparation must use one bounded HTTP request per UUID.'
);
assertInstallBatchContract(strpos($prepareView, 'Stop after current template') !== false,
	'Batch install preparation must be cancellable between candidates.');
assertInstallBatchContract(strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.install_batch')") !== false,
	'Batch install execution must carry its action-specific CSRF token.');
assertInstallBatchContract(strpos($prepareView, 'Install ready templates') !== false,
	'Batch install execution must require an explicit Ready-only submit.');

assertInstallBatchContract(strpos($install, "'confirm' => 'required|in 1'") !== false,
	'Batch install execution must require explicit confirmation.');
assertInstallBatchContract(strpos($install, 'TemplateInstallBatchService') !== false,
	'Batch install action must delegate to the sequential service.');
assertInstallBatchContract(strpos($resultView, 'No automatic uninstall is performed') !== false,
	'Batch install result must explicitly state that automatic uninstall is not performed.');

assertInstallBatchContract(
	strpos($listView, "'not_installed'") !== false
		&& strpos($listView, "new CCheckBox('uuids['") !== false
		&& strpos($listView, 'Review selected installations') !== false,
	'Not installed catalog mode must expose multi-select installation review.'
);

$combined = $prepare.$prepareOne.$install.$prepareView.$resultView;
foreach ([
	'API::Configuration()->import(',
	'API::Template()->create(',
	'API::Template()->update(',
	'API::Template()->delete(',
	'DB::update(',
	'DB::delete('
] as $fragment) {
	assertInstallBatchContract(strpos($combined, $fragment) === false,
		'Batch install controllers/views must not introduce a second write boundary: '.$fragment);
}

echo "Template install batch action contract tests passed.\n";
