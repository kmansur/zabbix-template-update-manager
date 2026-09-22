<?php

$root = dirname(__DIR__);
$manifestPath = $root.'/manifest.json';

try {
	$manifest = json_decode(
		file_get_contents($manifestPath),
		true,
		512,
		JSON_THROW_ON_ERROR
	);
}
catch (Throwable $exception) {
	fwrite(STDERR, 'Invalid manifest.json: '.$exception->getMessage().PHP_EOL);
	exit(1);
}

$required = [
	'manifest_version',
	'id',
	'name',
	'namespace',
	'version',
	'author',
	'description',
	'actions'
];

foreach ($required as $key) {
	if (!array_key_exists($key, $manifest)) {
		fwrite(STDERR, sprintf("manifest.json is missing required key: %s\n", $key));
		exit(1);
	}
}

$expected = [
	'manifest_version' => 2.0,
	'id' => 'kmansur_zabbix_template_update_manager',
	'namespace' => 'ZabbixTemplateUpdateManager'
];

foreach ($expected as $key => $value) {
	if ($manifest[$key] !== $value) {
		fwrite(
			STDERR,
			sprintf(
				"Unexpected %s in manifest.json. Expected %s, got %s.\n",
				$key,
				var_export($value, true),
				var_export($manifest[$key], true)
			)
		);
		exit(1);
	}
}

$expectedActions = [
	'ztum.templates' => ['class' => 'TemplateList', 'view' => 'template.list'],
	'ztum.templates.review_selected' => ['class' => 'TemplateSelectionReview', 'view' => 'template.selection.review'],
	'ztum.templates.prepare_selected' => ['class' => 'TemplateBatchPrepare', 'view' => 'template.batch.prepare'],
	'ztum.templates.prepare_one' => ['class' => 'TemplateBatchPrepareOne', 'layout' => 'layout.json', 'view' => null],
	'ztum.templates.batch_update_one' => ['class' => 'TemplateBatchUpdateOne', 'layout' => 'layout.json', 'view' => null],
	'ztum.templates.batch_update' => ['class' => 'TemplateBatchUpdate', 'view' => 'template.batch.update'],
	'ztum.templates.install_prepare_selected' => ['class' => 'TemplateInstallBatchPrepare', 'view' => 'template.install.batch.prepare'],
	'ztum.templates.install_prepare_one' => ['class' => 'TemplateInstallBatchPrepareOne', 'layout' => 'layout.json', 'view' => null],
	'ztum.templates.install_execute_one' => ['class' => 'TemplateInstallBatchExecuteOne', 'layout' => 'layout.json', 'view' => null],
	'ztum.template.install.review' => ['class' => 'TemplateInstallReview', 'view' => 'template.install.review'],
	'ztum.template.install' => ['class' => 'TemplateInstall', 'view' => 'template.install'],
	'ztum.template.compare' => ['class' => 'TemplateCompare', 'view' => 'template.compare'],
	'ztum.template.backup' => ['class' => 'TemplateBackup'],
	'ztum.template.backups' => ['class' => 'TemplateBackupList', 'view' => 'template.backup.list'],
	'ztum.template.preflight' => ['class' => 'TemplatePreflight', 'view' => 'template.preflight'],
	'ztum.template.update' => ['class' => 'TemplateUpdate', 'view' => 'template.update'],
	'ztum.template.rollback.review' => ['class' => 'TemplateRollbackReview', 'view' => 'template.rollback.review'],
	'ztum.template.rollback' => ['class' => 'TemplateRollback', 'view' => 'template.rollback']
];

foreach ($expectedActions as $actionName => $expectedAction) {
	$action = $manifest['actions'][$actionName] ?? null;
	if (!is_array($action) || ($action['class'] ?? null) !== $expectedAction['class']) {
		fwrite(STDERR, sprintf("Action %s is not configured as expected.\n", $actionName));
		exit(1);
	}

	if (array_key_exists('layout', $expectedAction)
			&& ($action['layout'] ?? null) !== $expectedAction['layout']) {
		fwrite(STDERR, sprintf("Action %s layout is not configured as expected.\n", $actionName));
		exit(1);
	}

	if (array_key_exists('view', $expectedAction)) {
		if (($action['view'] ?? null) !== $expectedAction['view']) {
			fwrite(STDERR, sprintf("Action %s view is not configured as expected.\n", $actionName));
			exit(1);
		}
	}
	elseif (array_key_exists('view', $action)) {
		fwrite(STDERR, sprintf("Action %s must not register a view.\n", $actionName));
		exit(1);
	}
}

if (count($manifest['actions']) !== count($expectedActions)) {
	fwrite(STDERR, "manifest.json contains an unexpected action count.\n");
	exit(1);
}

$requiredFiles = [
	$root.'/Module.php',
	$root.'/actions/TemplateList.php',
	$root.'/views/template.list.php',
	$root.'/actions/TemplateSelectionReview.php',
	$root.'/views/template.selection.review.php',
	$root.'/actions/TemplateBatchPrepare.php',
	$root.'/views/template.batch.prepare.php',
	$root.'/actions/TemplateBatchPrepareOne.php',
	$root.'/actions/TemplateBatchUpdateOne.php',
	$root.'/actions/TemplateBatchUpdate.php',
	$root.'/views/template.batch.update.php',
	$root.'/actions/TemplateInstallBatchPrepare.php',
	$root.'/views/template.install.batch.prepare.php',
	$root.'/actions/TemplateInstallBatchPrepareOne.php',
	$root.'/actions/TemplateInstallBatchExecuteOne.php',
	$root.'/actions/TemplateInstallReview.php',
	$root.'/views/template.install.review.php',
	$root.'/actions/TemplateInstall.php',
	$root.'/views/template.install.php',
	$root.'/actions/TemplateCompare.php',
	$root.'/views/template.compare.php',
	$root.'/actions/TemplateBackup.php',
	$root.'/actions/TemplateBackupList.php',
	$root.'/views/template.backup.list.php',
	$root.'/actions/TemplatePreflight.php',
	$root.'/views/template.preflight.php',
	$root.'/actions/TemplateUpdate.php',
	$root.'/views/template.update.php',
	$root.'/actions/TemplateRollbackReview.php',
	$root.'/views/template.rollback.review.php',
	$root.'/actions/TemplateRollback.php',
	$root.'/views/template.rollback.php'
];

foreach ($requiredFiles as $file) {
	if (!is_file($file) || filesize($file) === 0) {
		fwrite(STDERR, 'Required module file not found or empty: '.$file.PHP_EOL);
		exit(1);
	}
}

echo "manifest.json validation passed.\n";
