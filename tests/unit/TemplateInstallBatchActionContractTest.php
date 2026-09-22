<?php

$root = dirname(__DIR__, 2);
$prepare = (string) file_get_contents($root.'/actions/TemplateInstallBatchPrepare.php');
$prepareOne = (string) file_get_contents($root.'/actions/TemplateInstallBatchPrepareOne.php');
$executeOne = (string) file_get_contents($root.'/actions/TemplateInstallBatchExecuteOne.php');
$prepareView = (string) file_get_contents($root.'/views/template.install.batch.prepare.php');
$listView = (string) file_get_contents($root.'/views/template.list.php');
$manifest = (string) file_get_contents($root.'/manifest.json');

function assertInstallBatchContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach ([$prepare, $prepareOne, $executeOne] as $controller) {
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
		&& strpos($manifest, '"ztum.templates.install_execute_one"') !== false
		&& substr_count($manifest, '"layout": "layout.json"') >= 2,
	'Per-template preparation and execution routes must use layout.json.'
);

assertInstallBatchContract(
	strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.install_prepare_one')") !== false
		&& strpos($prepareView, 'fetch(config.prepareOneUrl') !== false
		&& strpos($prepareView, 'for (let index = 0; index < config.uuids.length; index++)') !== false,
	'Batch install preparation must use one bounded HTTP request per UUID.'
);

assertInstallBatchContract(
	strpos($prepareView, "CCsrfTokenHelper::get('ztum.templates.install_execute_one')") !== false
		&& strpos($prepareView, 'fetch(config.executeOneUrl') !== false
		&& strpos($prepareView, 'for (let index = 0; index < entries.length; index++)') !== false,
	'Batch install execution must use one bounded HTTP request per Ready UUID.'
);

assertInstallBatchContract(strpos($prepareView, 'Stop after current template') !== false,
	'Batch install preparation must be cancellable between candidates.');
assertInstallBatchContract(strpos($prepareView, 'Install ready templates') !== false,
	'Batch install execution must require an explicit Ready-only action.');

assertInstallBatchContract(
	strpos($prepareView, 'No templates are eligible for installation.') !== false
		&& strpos($prepareView, 'Unavailable — no Ready templates') !== false
		&& strpos($prepareView, "readyEvidence.size === 0") !== false,
	'Zero-Ready batch plans must explain why confirmation/execution remains unavailable.'
);

assertInstallBatchContract(
	strpos($prepareView, 'reference_issues') !== false,
	'Batch preparation must display bounded structural-reference issues when present.'
);
assertInstallBatchContract(strpos($prepareView, 'stops on the first failure') !== false,
	'Request-bounded execution must preserve stop-on-first-failure behavior.');

assertInstallBatchContract(strpos($executeOne, "'confirm' => 'required|in 1'") !== false,
	'Each request-bounded installation write must require explicit confirmation.');
assertInstallBatchContract(strpos($executeOne, 'TemplateControlledInstallService') !== false,
	'Each request-bounded execution must reuse the authoritative controlled install service.');
assertInstallBatchContract(strpos($executeOne, 'disableView()') !== false,
	'Request-bounded execution must return raw JSON-layout data.');

assertInstallBatchContract(
	strpos($listView, "'not_installed'") !== false
		&& strpos($listView, "new CCheckBox('uuids['") !== false
		&& strpos($listView, 'Review selected installations') !== false
		&& strpos($listView, '->setEnabled(true)') !== false,
	'Not installed catalog mode must expose active multi-select/select-all installation review.'
);

$combined = $prepare.$prepareOne.$executeOne.$prepareView;
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
