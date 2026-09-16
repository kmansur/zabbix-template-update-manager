<?php

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;

require_once dirname(__DIR__).'/src/Support/ProjectVersion.php';
require_once dirname(__DIR__).'/src/Support/ZabbixVersion.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamIndexRepository.php';

$directory = $argv[1] ?? dirname(__DIR__).'/build/upstream';
$files = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.json');
if ($files === false || $files === []) {
	fwrite(STDERR, "No generated upstream indexes found in {$directory}.\n");
	exit(1);
}

sort($files, SORT_STRING);
foreach ($files as $file) {
	$line = basename($file, '.json');
	$content = file_get_contents($file);
	if (!is_string($content)) {
		fwrite(STDERR, "Unable to read generated index: {$file}\n");
		exit(1);
	}

	try {
		$decoded = UpstreamIndexRepository::decodeIndex($content, $line);
	}
	catch (Throwable $exception) {
		fwrite(STDERR, sprintf(
			"Generated index %s failed runtime validation: %s\n",
			$file,
			$exception->getMessage()
		));
		exit(1);
	}

	printf(
		"Validated generated Zabbix %s index: %d templates.\n",
		$line,
		count($decoded['templates'])
	);
}
