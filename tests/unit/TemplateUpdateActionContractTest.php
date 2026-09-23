<?php

$root = dirname(__DIR__, 2);
$manifest = json_decode(file_get_contents($root.'/manifest.json'), true);
$action = file_get_contents($root.'/actions/TemplateUpdate.php');
$preflightView = file_get_contents($root.'/views/template.preflight.php');
$importService = file_get_contents($root.'/src/Service/TemplateConfigurationImportService.php');

function assertTemplateUpdateContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertTemplateUpdateContract(
	isset($manifest['actions']['ztum.template.update'])
		&& ($manifest['actions']['ztum.template.update']['class'] ?? null) === 'TemplateUpdate'
		&& ($manifest['actions']['ztum.template.update']['view'] ?? null) === 'template.update',
	'Controlled update action must be registered with its result view.'
);
assertTemplateUpdateContract(
	strpos($action, 'disableCsrfValidation') === false,
	'Controlled update action must keep native CSRF validation enabled.'
);
assertTemplateUpdateContract(
	strpos($action, 'USER_TYPE_SUPER_ADMIN') !== false
		&& strpos($action, 'USER_TYPE_ZABBIX_ADMIN') === false,
	'Controlled update action must be restricted to super administrators.'
);
assertTemplateUpdateContract(
	strpos($action, "'templateid' => 'required|db hosts.hostid'") !== false
		&& strpos($action, "'evidence_sha256' => 'required|string'") !== false
		&& strpos($action, "'confirm' => 'required|in 1'") !== false,
	'Controlled update action must require template ID, evidence fingerprint and explicit confirmation.'
);
assertTemplateUpdateContract(
	strpos($action, "'manual_override' => 'in 1'") !== false
		&& strpos($action, "'confirm_manual_override' => 'in 1'") !== false
		&& strpos($preflightView, "new CCheckBox('confirm_manual_override', '1')") !== false,
	'Reviewed update path must require a second explicit acknowledgement and bind manual override through POST.'
);
assertTemplateUpdateContract(
	strpos($action, "'confirm_local_overwrite' => 'in 1'") !== false
		&& strpos($preflightView, "new CCheckBox('confirm_local_overwrite', '1')") !== false
		&& strpos($action, '$localOverwriteConfirmed') !== false,
	'Individual local-customization overwrite must require its own explicit acknowledgement and pass it to the controlled service.'
);
assertTemplateUpdateContract(
	strpos($action, 'TemplateOperationLockService') !== false
		&& strpos($action, "->run(\n\t\t\t\t'update'") !== false,
	'Individual controlled update must acquire the global operation lock before fresh preflight/write orchestration.'
);
assertTemplateUpdateContract(
	strpos($action, 'TemplateControlledUpdateService') !== false,
	'Controlled update action must delegate write orchestration to TemplateControlledUpdateService.'
);
assertTemplateUpdateContract(
	preg_match('/API::Configuration\(\)->import\s*\(/', $action) !== 1,
	'Frontend update action must not call configuration.import directly.'
);
assertTemplateUpdateContract(
	strpos($preflightView, "CCsrfTokenHelper::get('ztum.template.update')") !== false
		&& strpos($preflightView, "new CCheckBox('confirm', '1')") !== false
		&& strpos($preflightView, "new CVar('evidence_sha256', \$evidenceSha)") !== false,
	'Preflight confirmation must use POST/CSRF, explicit checkbox confirmation and bound evidence fingerprint.'
);
assertTemplateUpdateContract(
	preg_match_all('/API::Configuration\(\)->import\s*\(/', $importService) === 1,
	'Controlled import service must contain exactly one configuration.import call.'
);
assertTemplateUpdateContract(
	strpos($importService, 'TemplateImportCompareService::rules()') !== false,
	'Controlled import must reuse the reviewed import-comparison rule profile.'
);

echo "Template update action contract tests passed.\n";
