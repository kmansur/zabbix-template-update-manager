<?php

$root = dirname(__DIR__);
$controlledImportRelative = 'src/Service/TemplateConfigurationImportService.php';
$controlledImportPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $controlledImportRelative);

$alwaysBlockedPatterns = [
	'/template\.create/i' => 'template.create',
	'/template\.update/i' => 'template.update',
	'/template\.delete/i' => 'template.delete',
	'/DB::insert\s*\(/i' => 'DB::insert()',
	'/DB::update\s*\(/i' => 'DB::update()',
	'/DB::delete\s*\(/i' => 'DB::delete()',
	'/\bDBexecute\s*\(/i' => 'DBexecute()'
];
$apiWritePattern = '/API::[A-Za-z0-9_]+\(\)->(?:create|update|delete|massadd|massremove|import|replace|push)\s*\(/i';
$approvedImportPattern = '/API::Configuration\(\)->import\s*\(/';

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$violations = [];
$approvedImportCalls = 0;

foreach ($iterator as $file) {
	if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
		continue;
	}

	$path = $file->getPathname();
	if (str_starts_with($path, $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)) {
		continue;
	}

	$content = file_get_contents($path);
	if (!is_string($content)) {
		$violations[] = sprintf('%s: unreadable PHP source', $path);
		continue;
	}

	foreach ($alwaysBlockedPatterns as $pattern => $label) {
		if (preg_match($pattern, $content)) {
			$violations[] = sprintf('%s: %s', $path, $label);
		}
	}

	if ($path === $controlledImportPath) {
		$count = preg_match_all($approvedImportPattern, $content);
		if ($count !== 1) {
			$violations[] = sprintf(
				'%s: controlled import boundary must contain exactly one API::Configuration()->import() call',
				$path
			);
		}
		else {
			$approvedImportCalls += $count;
		}

		$withoutApprovedImport = preg_replace($approvedImportPattern, 'APPROVED_CONFIGURATION_IMPORT(', $content, 1);
		if (!is_string($withoutApprovedImport) || preg_match($apiWritePattern, $withoutApprovedImport)) {
			$violations[] = sprintf('%s: additional Zabbix API write method', $path);
		}

		if (strpos($content, 'TemplateImportCompareService::rules()') === false) {
			$violations[] = sprintf('%s: controlled import must reuse reviewed import-comparison rules', $path);
		}
		continue;
	}

	if (preg_match($apiWritePattern, $content)) {
		$violations[] = sprintf('%s: Zabbix API write method outside controlled import boundary', $path);
	}
}

if (!is_file($controlledImportPath)) {
	$violations[] = $controlledImportRelative.': controlled import boundary is missing';
}
elseif ($approvedImportCalls !== 1) {
	$violations[] = sprintf(
		'%s: expected exactly one approved configuration import call, found %d',
		$controlledImportRelative,
		$approvedImportCalls
	);
}

if ($violations !== []) {
	fwrite(STDERR, "Controlled-write guard failed:\n - ".implode("\n - ", $violations)."\n");
	exit(1);
}

echo "Controlled-write guard passed: exactly one configuration import boundary is approved.\n";
