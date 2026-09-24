<?php

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root.'/src/Service/TemplateConfigurationImportService.php');
$executeOne = (string) file_get_contents($root.'/actions/TemplateInstallBatchExecuteOne.php');
$controlledInstall = (string) file_get_contents($root.'/src/Service/TemplateControlledInstallService.php');
$batchView = (string) file_get_contents($root.'/views/ztum.template.install.batch.prepare.php')
	.(string) file_get_contents($root.'/assets/js/ztum-install-batch.js');
$preflight = (string) file_get_contents($root.'/src/Service/TemplateInstallPreflightService.php');

function assertImportDiagnosticContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertImportDiagnosticContract(
	strpos($service, 'use CMessageHelper;') !== false
		&& substr_count($service, 'CMessageHelper::getMessages()') >= 2,
	'Configuration import must inspect native frontend API messages when import returns false.'
);

assertImportDiagnosticContract(
	strpos($service, 'array_slice($messagesAfter, count($messagesBefore))') !== false,
	'Configuration import diagnostics must isolate messages created by the current API call.'
);

assertImportDiagnosticContract(
	strpos($service, 'MESSAGE_TYPE_ERROR') !== false
		&& strpos($service, "implode(' | '") !== false,
	'Configuration import failure must preserve native Zabbix error detail.'
);

assertImportDiagnosticContract(
	strpos($executeOne, '$exception->getMessage()') !== false,
	'Super Admin request-bounded install execution must return unexpected controller-level failure detail.'
);

assertImportDiagnosticContract(
	strpos($controlledInstall, "'status' => 'import_failed'") !== false
		&& strpos($controlledInstall, "'error_detail' => self::sanitizeError") !== false
		&& strpos($controlledInstall, "'write_outcome' => 'uncertain'") !== false,
	'Controlled installation must convert import-stage rejection into a structured uncertain result with Zabbix detail.'
);

assertImportDiagnosticContract(
	strpos($batchView, "result.status === 'import_failed'") !== false
		&& strpos($batchView, 'result.error_detail') !== false
		&& strpos($batchView, "setText('ztum-install-reason-' + uuid") !== false,
	'Batch installation UI must show structured import failure detail inline without mislabeling it as a request failure.'
);

assertImportDiagnosticContract(
	strpos($preflight, 'catch (TemplateIsolationSafetyException $exception)') !== false
		&& strpos($preflight, "'blocked_isolation'") !== false
		&& strpos($preflight, "'candidate' => \$candidate") !== false,
	'Known isolation safety failures must remain named, explicit blocked preflight states.'
);

echo "Template import diagnostic contracts passed.\n";
