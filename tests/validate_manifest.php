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
	'ztum.template.compare' => ['class' => 'TemplateCompare', 'view' => 'template.compare'],
	'ztum.template.backup' => ['class' => 'TemplateBackup']
];

foreach ($expectedActions as $actionName => $expectedAction) {
	$action = $manifest['actions'][$actionName] ?? null;
	if (!is_array($action) || ($action['class'] ?? null) !== $expectedAction['class']) {
		fwrite(STDERR, sprintf("Action %s is not configured as expected.\n", $actionName));
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

$requiredFiles = [
	$root.'/Module.php',
	$root.'/actions/TemplateList.php',
	$root.'/views/template.list.php',
	$root.'/actions/TemplateCompare.php',
	$root.'/views/template.compare.php',
	$root.'/actions/TemplateBackup.php'
];

foreach ($requiredFiles as $file) {
	if (!is_file($file)) {
		fwrite(STDERR, 'Required module file not found: '.$file.PHP_EOL);
		exit(1);
	}
}

echo "manifest.json validation passed.\n";
