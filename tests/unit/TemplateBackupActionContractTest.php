<?php

function assertTemplateBackupAction($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$root = dirname(__DIR__, 2);
$action = (string) file_get_contents($root.'/actions/TemplateBackup.php');
$view = (string) file_get_contents($root.'/views/ztum.template.compare.php');
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true);

assertTemplateBackupAction(
	true,
	str_contains($action, 'class TemplateBackup extends CController'),
	'Backup action must remain a native Zabbix controller.'
);
assertTemplateBackupAction(
	false,
	str_contains($action, 'disableCsrfValidation'),
	'Backup action must not disable native Zabbix CSRF validation.'
);
assertTemplateBackupAction(
	true,
	str_contains($action, 'USER_TYPE_ZABBIX_ADMIN') && str_contains($action, 'USER_TYPE_SUPER_ADMIN'),
	'Backup action must remain restricted to Zabbix administrators and super administrators.'
);
assertTemplateBackupAction(
	true,
	str_contains($action, 'TemplateBackupService'),
	'Backup action must delegate export/persistence to TemplateBackupService.'
);

$backupAction = $manifest['actions']['ztum.template.backup'] ?? null;
assertTemplateBackupAction(true, is_array($backupAction), 'Backup action must be registered in manifest.json.');
assertTemplateBackupAction('TemplateBackup', $backupAction['class'] ?? null, 'Backup action class must be TemplateBackup.');
assertTemplateBackupAction(false, array_key_exists('view', $backupAction), 'Backup POST action must not register a view.');

$prepareAction = $manifest['actions']['ztum.template.prepare'] ?? null;
assertTemplateBackupAction(true, is_array($prepareAction), 'Controlled update preparation must be registered.');
assertTemplateBackupAction('TemplateUpdatePrepare', $prepareAction['class'] ?? null, 'Preparation must use the reviewed controller.');
assertTemplateBackupAction('ztum.template.preflight', $prepareAction['view'] ?? null, 'Preparation must lead to native preflight review.');
$prepareController = (string) file_get_contents($root.'/actions/TemplateUpdatePrepare.php');
assertTemplateBackupAction(true, str_contains($prepareController, 'USER_TYPE_SUPER_ADMIN'), 'Preparation must require Super Admin.');
assertTemplateBackupAction(false, str_contains($prepareController, 'disableCsrfValidation'), 'Preparation must keep native CSRF validation.');
assertTemplateBackupAction(true, str_contains($prepareController, 'TemplateUpdatePreparationService'), 'Preparation must delegate to verified backup and fresh preflight.');
assertTemplateBackupAction(true, str_contains($view, "setArgument('action', 'ztum.template.prepare')"), 'Comparison view must post to the controlled preparation action.');
assertTemplateBackupAction(
	true,
	str_contains($view, "new CForm('post')"),
	'Preparation form must explicitly use POST.'
);
assertTemplateBackupAction(
	true,
	str_contains($view, "CCsrfTokenHelper::get('ztum.template.prepare')"),
	'Preparation form must carry the native module CSRF token for the exact action name.'
);
assertTemplateBackupAction(
	true,
	str_contains($view, "new CVar('templateid'"),
	'Preparation form must submit the selected template ID as business input.'
);

echo "TemplateBackup action contract tests passed.\n";
