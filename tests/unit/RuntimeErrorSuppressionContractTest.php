<?php

$root = dirname(__DIR__, 2);
$lock = (string) file_get_contents($root.'/src/Service/TemplateOperationLockService.php');
$import = (string) file_get_contents($root.'/src/Service/TemplateConfigurationImportService.php');

function assertSuppressionContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

foreach (['@fwrite', '@fflush', '@chmod', '@flock', '@ftruncate', '@rewind'] as $forbidden) {
	assertSuppressionContract(
		strpos($lock, $forbidden) === false,
		'Critical operation-lock persistence must not suppress '.$forbidden.' failures.'
	);
}

assertSuppressionContract(
	substr_count($lock, '@fopen') === 1
		&& substr_count($lock, '@mkdir') === 1,
	'Only the reviewed fopen warning and mkdir race suppressions are allowed in the operation-lock service.'
);

assertSuppressionContract(
	strpos($import, '@') === false,
	'The controlled Zabbix configuration-import boundary must not suppress PHP errors.'
);

echo "Critical runtime error-suppression contracts passed.\n";
