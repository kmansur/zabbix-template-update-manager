<?php

$root = dirname(__DIR__);
$blockedPatterns = [
	'/configuration\.import(?!compare)/i' => 'configuration.import',
	'/template\.create/i' => 'template.create',
	'/template\.update/i' => 'template.update',
	'/template\.delete/i' => 'template.delete',
	'/DB::insert\s*\(/i' => 'DB::insert()',
	'/DB::update\s*\(/i' => 'DB::update()',
	'/DB::delete\s*\(/i' => 'DB::delete()'
];

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$violations = [];

foreach ($iterator as $file) {
	if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
		continue;
	}

	$path = $file->getPathname();
	if (str_starts_with($path, $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR)) {
		continue;
	}

	$content = file_get_contents($path);
	foreach ($blockedPatterns as $pattern => $label) {
		if (preg_match($pattern, $content)) {
			$violations[] = sprintf('%s: %s', $path, $label);
		}
	}
}

if ($violations !== []) {
	fwrite(STDERR, "Read-only guard failed:\n - ".implode("\n - ", $violations)."\n");
	exit(1);
}

echo "Read-only guard passed.\n";
