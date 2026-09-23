<?php

$root = dirname(__DIR__, 2);
$files = [
	'actions/TemplateUpdate.php' => "'update'",
	'actions/TemplateBatchUpdateOne.php' => "'update'",
	'actions/TemplateInstall.php' => "'install'",
	'actions/TemplateInstallBatchExecuteOne.php' => "'install'",
	'actions/TemplateRollback.php' => "'rollback'"
];

foreach ($files as $relative => $operation) {
	$content = (string) file_get_contents($root.'/'.$relative);
	if (strpos($content, 'TemplateOperationLockService') === false
			|| strpos($content, '->run(') === false
			|| strpos($content, $operation) === false) {
		fwrite(STDERR, 'Controlled write action does not acquire the global operation lock: '.$relative."\n");
		exit(1);
	}
}

echo "Template operation lock action contract tests passed.\n";
