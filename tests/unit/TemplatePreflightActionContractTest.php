<?php

$root = dirname(__DIR__, 2);
$manifest = json_decode(file_get_contents($root.'/manifest.json'), true);
$action = file_get_contents($root.'/actions/TemplatePreflight.php');
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
		&& strpos($compareView, "new CVar('manual_override', '1')") !== false,
	'Reviewed preflight mode must be explicit in both the comparison form and controller input validation.'
);
assertPreflightActionContract(
	strpos($action, 'USER_TYPE_ZABBIX_ADMIN') !== false
		&& strpos($action, 'USER_TYPE_SUPER_ADMIN') !== false,
	'Preflight action must be restricted to Zabbix administrators and super administrators.'
);
assertPreflightActionContract(
	preg_match('/Configuration\(\)->import\s*\(/', $action) !== 1,
	'Preflight action must not perform configuration.import.'
);

assertPreflightActionContract(
	strpos($compareView, "CCsrfTokenHelper::get('ztum.template.preflight')") !== false
		&& strpos($compareView, "new CForm('post')") !== false
		&& strpos($compareView, "'backup_verified'") !== false,
	'Comparison must invoke preflight through a CSRF-protected POST form only after backup verification.'
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
	stripos($view, 'configuration import') !== false,
	'Preflight view must explicitly state that it does not perform a configuration import.'
);

echo "Template preflight action contract tests passed.\n";
