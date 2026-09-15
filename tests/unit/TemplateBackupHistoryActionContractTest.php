<?php

function assertBackupHistoryContract($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root.'/actions/TemplateBackupList.php');
$view = (string) file_get_contents($root.'/views/template.backup.list.php');
$listView = (string) file_get_contents($root.'/views/template.list.php');
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true);

$action = $manifest['actions']['ztum.template.backups'] ?? null;
assertBackupHistoryContract(true, is_array($action), 'Rollback backup history action must be registered.');
assertBackupHistoryContract('TemplateBackupList', $action['class'] ?? null, 'Rollback history action class must be exact.');
assertBackupHistoryContract('template.backup.list', $action['view'] ?? null, 'Rollback history action view must be exact.');

assertBackupHistoryContract(
	true,
	str_contains($controller, 'class TemplateBackupList extends CController'),
	'Rollback backup history must use a native Zabbix controller.'
);
assertBackupHistoryContract(
	true,
	str_contains($controller, 'disableCsrfValidation()'),
	'Read-only GET history controller may disable CSRF validation explicitly.'
);
assertBackupHistoryContract(
	true,
	str_contains($controller, 'USER_TYPE_ZABBIX_ADMIN') && str_contains($controller, 'USER_TYPE_SUPER_ADMIN'),
	'Rollback backup history must remain restricted to administrators and super administrators.'
);
assertBackupHistoryContract(
	true,
	str_contains($controller, 'TemplateBackupRepository'),
	'Rollback backup history must read through TemplateBackupRepository.'
);
assertBackupHistoryContract(
	true,
	str_contains($controller, 'inspectForTemplate($templateId, 50)'),
	'Rollback backup history must remain bounded to at most 50 inspected artifacts.'
);
assertBackupHistoryContract(
	true,
	str_contains($controller, "'repository_status' => 'repository_unavailable'"),
	'Rollback backup history must default to a fail-closed repository-unavailable state.'
);
assertBackupHistoryContract(
	false,
	str_contains($controller, 'TemplateBackupService'),
	'Rollback backup history controller must not create backups.'
);
assertBackupHistoryContract(
	false,
	str_contains($controller, 'TemplateExportService'),
	'Rollback backup history controller must not trigger fresh template exports.'
);

assertBackupHistoryContract(false, str_contains($view, 'source_path'), 'History view must never expose source filesystem paths.');
assertBackupHistoryContract(false, str_contains($view, 'manifest_path'), 'History view must never expose manifest filesystem paths.');
assertBackupHistoryContract(false, str_contains($view, 'new CSubmitButton'), 'History view must not expose submit actions.');
assertBackupHistoryContract(false, str_contains($view, 'new CButton'), 'History view must not expose action buttons.');
assertBackupHistoryContract(false, str_contains($view, 'CSRF_TOKEN_NAME'), 'Read-only history view must not contain a write form or CSRF token.');
assertBackupHistoryContract(
	true,
	str_contains($view, "if ($data['repository_status'] === 'repository_unavailable')"),
	'History view must distinguish an unavailable repository from a genuinely empty backup history.'
);
assertBackupHistoryContract(
	true,
	str_contains($view, 'No conclusion about stored backup availability can be made.'),
	'Unavailable repository messaging must fail closed instead of claiming that no backups exist.'
);
assertBackupHistoryContract(
	true,
	str_contains($listView, "setArgument('action', 'ztum.template.backups')"),
	'Template inventory must link administrators to rollback backup history.'
);

// The only explicit action target in the history view itself must be the read-only back link.
preg_match_all("/setArgument\\('action',\\s*'([^']+)'\\)/", $view, $matches);
$viewActions = array_values(array_unique($matches[1] ?? []));
assertBackupHistoryContract(['ztum.templates'], $viewActions, 'History view must not expose download/delete/restore/write actions.');

echo "TemplateBackup history action contract tests passed.\n";
