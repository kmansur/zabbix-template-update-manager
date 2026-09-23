<?php

$root = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents($root.'/manifest.json'), true);

if (!is_array($manifest) || !is_array($manifest['actions'] ?? null)) {
	fwrite(STDERR, "Unable to validate module view namespace: invalid manifest.\n");
	exit(1);
}

foreach ($manifest['actions'] as $action => $config) {
	if (!array_key_exists('view', $config) || $config['view'] === null) {
		continue;
	}

	$view = (string) $config['view'];
	if (!str_starts_with($view, 'ztum.')) {
		fwrite(STDERR, "Module action {$action} uses an unnamespaced view: {$view}\n");
		exit(1);
	}

	$file = $root.'/views/'.$view.'.php';
	if (!is_file($file) || filesize($file) === 0) {
		fwrite(STDERR, "Namespaced module view is missing or empty: {$view}\n");
		exit(1);
	}
}

foreach (glob($root.'/views/*.php') ?: [] as $file) {
	$name = basename($file);
	if (!str_starts_with($name, 'ztum.')) {
		fwrite(STDERR, "Unnamespaced module view may override a Zabbix core view: {$name}\n");
		exit(1);
	}
}

echo "Module view namespace guard passed.\n";
