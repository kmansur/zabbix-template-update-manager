<?php

$root = dirname(__DIR__, 2);
$manifest = json_decode(file_get_contents($root.'/manifest.json'), true);
$action = file_get_contents($root.'/actions/TemplatePreflight.php');
$prepareAction = file_get_contents($root.'/actions/TemplateUpdatePrepare.php');
$preparationService = file_get_contents($root.'/src/Service/TemplateUpdatePreparationService.php');
$view = file_get_contents($root.'/views/ztum.template.preflight.php');
$compareView = file_get_contents($root.'/views/ztum.template.compare.php');
$listView = file_get_contents($root.'/views/ztum.template.list.php');

function assertPreflightActionContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertPreflightActionContract(
	isset($manifest['actions']['ztum.template.preflight'])
		&& ($manifest['actions']['ztum.template.preflight']['class'] ?? null) === 'TemplatePreflight'
		&& ($manifest['actions']['ztum.template.preflight']['view'] ?? null) === 'ztum.template.preflight',
	'Preflight action must be registered with its native view.'
);

assertPreflightActionContract(
	strpos($action, 'disableCsrfValidation') === false,
	'Preflight action must keep native CSRF validation enabled.'
);
assertPreflightActionContract(
	strpos($action, 'TemplateUpdatePreflightService') !== false,
	'Preflight action must delegate to TemplateUpdatePreflightService.'
);
assertPreflightActionContract(
	strpos($action, "'templateid' => 'required|db hosts.hostid'") !== false,
	'Preflight action must validate the selected template ID.'
);
assertPreflightActionContract(
	strpos($action, "'manual_override' => 'in 1'") !== false
		&& strpos($preparationService, '$manual = $freshStatus === \'review_backup_verified\';') !== false
		&& strpos($preparationService, '($this->preflight)($templateId, $manual)') !== false,
	'Reviewed preflight mode must be explicit in input validation and derived from fresh verified readiness during preparation.'
);
assertPreflightActionContract(
	strpos($action, '$this->getUserType() === USER_TYPE_SUPER_ADMIN') !== false
		&& strpos($action, 'USER_TYPE_ZABBIX_ADMIN') === false,
	'Preflight action must be restricted to Super Admin.'
);
assertPreflightActionContract(
	preg_match('/Configuration\(\)->import\s*\(/', $action) !== 1,
	'Preflight action must not perform configuration.import.'
);

assertPreflightActionContract(
	strpos($compareView, "CCsrfTokenHelper::get('ztum.template.prepare')") !== false
		&& strpos($compareView, "setArgument('action', 'ztum.template.prepare')") !== false
		&& strpos($prepareAction, 'TemplateUpdatePreparationService') !== false
		&& strpos($prepareAction, 'USER_TYPE_SUPER_ADMIN') !== false
		&& strpos($prepareAction, 'disableCsrfValidation') === false
		&& strpos($compareView, "new CForm('post')") !== false
		&& strpos($compareView, "'backup_verified'") !== false,
	'Comparison must invoke verified preparation and fresh preflight through a CSRF-protected POST form.'
);
assertPreflightActionContract(
	strpos($listView, "ztum.template.compare") !== false
		&& strpos($listView, "Review") !== false,
	'Inventory must route update candidates into the comparison/review workflow rather than bypassing it.'
);
assertPreflightActionContract(
	strpos($view, "'write_enabled'") !== false || strpos($view, "write_enabled") !== false,
	'Preflight view must display whether configuration writes are enabled.'
);
assertPreflightActionContract(
	strpos($view, 'evidence_sha256') !== false,
	'Preflight view must display the deterministic evidence fingerprint when available.'
);
assertPreflightActionContract(
	strpos($view, 'This preflight was recomputed') !== false
		&& strpos($view, 'This operation writes Zabbix configuration') !== false,
	'Preflight view must distinguish read-only evidence recomputation from the later confirmed configuration write.'
);

echo "Template preflight action contract tests passed.\n";
