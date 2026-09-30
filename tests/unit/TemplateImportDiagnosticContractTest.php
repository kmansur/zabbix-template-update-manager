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
	strpos($service, 'API::getWrapper()') !== false
		&& strpos($service, "callMethod('configuration', 'import'") !== false
		&& strpos($service, 'errorCode') !== false
		&& strpos($service, 'errorMessage') !== false,
	'Configuration import must use the native API client response as the primary diagnostic source.'
);

assertImportDiagnosticContract(
	strpos($service, 'API::setWrapper();') !== false
		&& strpos($service, 'API::setWrapper($wrapper);') !== false
		&& strpos($service, 'finally') !== false,
	'Configuration import must restore the frontend API wrapper after the direct client call.'
);

assertImportDiagnosticContract(
	strpos($service, 'use CMessageHelper;') !== false
		&& substr_count($service, 'CMessageHelper::getMessages()') >= 2
		&& strpos($service, 'array_slice($messagesAfter, count($messagesBefore))') !== false
		&& strpos($service, 'MESSAGE_TYPE_ERROR') !== false,
	'CMessageHelper must remain only as a scoped compatibility fallback for unsupported wrapper shapes.'
);

assertImportDiagnosticContract(
	strpos($service, 'ConfigurationImportException') !== false,
	'Configuration import failures must use the stable typed ZTUM exception contract.'
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
