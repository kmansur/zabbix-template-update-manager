<?php

$root = dirname(__DIR__, 2);
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true);
$catalog = (string) file_get_contents($root.'/views/ztum.template.list.php');
$historyAction = (string) file_get_contents($root.'/actions/TemplateOperationHistory.php');
$historyView = (string) file_get_contents($root.'/views/ztum.operation.history.php');

function assertOperationHistoryContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertOperationHistoryContract(
	($manifest['actions']['ztum.operations']['class'] ?? null) === 'TemplateOperationHistory'
		&& ($manifest['actions']['ztum.operations']['view'] ?? null) === 'ztum.operation.history',
	'Manifest must register the operation-history route and view.'
);

assertOperationHistoryContract(
	strpos($historyAction, '[USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN]') !== false
		&& strpos($historyAction, 'TemplateOperationHistoryRepository') !== false
		&& strpos($historyAction, 'CPagerHelper::paginate') !== false,
	'Operation history must be administrator-only, private-repository backed and natively paginated.'
);

assertOperationHistoryContract(
	strpos($historyView, 'new CHtmlPage()') !== false
		&& strpos($historyView, 'FrontendUi') !== false
		&& stripos($historyView, 'supplemental local operator history') !== false,
	'Operation-history view must remain native and clearly non-authoritative.'
);

assertOperationHistoryContract(
	strpos($catalog, "_('Operation history')") !== false
		&& strpos($catalog, "setArgument('action', 'ztum.operations')") !== false,
	'Catalog must expose the operation-history navigation link.'
);

foreach ([
	'actions/TemplateUpdate.php',
	'actions/TemplateBatchUpdateOne.php',
	'actions/TemplateInstall.php',
	'actions/TemplateInstallBatchExecuteOne.php',
	'actions/TemplateRollback.php',
	'actions/TemplateUpdatePolicy.php',
	'actions/TemplateBackup.php'
] as $path) {
	$content = (string) file_get_contents($root.'/'.$path);
	assertOperationHistoryContract(
		strpos($content, 'TemplateOperationHistoryService') !== false
			&& strpos($content, 'recordBestEffort(') !== false,
		'Controlled/local write action must emit supplemental operation history: '.$path
	);
}

echo "Operation-history action/view contracts passed.\n";
