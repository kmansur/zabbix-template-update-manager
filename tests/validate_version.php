<?php

$root = dirname(__DIR__);
$versionFile = $root.'/VERSION';
$manifestFile = $root.'/manifest.json';

if (!is_file($versionFile) || !is_readable($versionFile)) {
	fwrite(STDERR, "VERSION file is missing or unreadable.\n");
	exit(1);
}

$version = trim((string) file_get_contents($versionFile));
if ($version === ''
		|| preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D', $version) !== 1) {
	fwrite(STDERR, sprintf("VERSION is not a supported semantic version: %s\n", $version));
	exit(1);
}

try {
	$manifest = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
}
catch (Throwable $exception) {
	fwrite(STDERR, 'Unable to parse manifest.json while validating VERSION: '.$exception->getMessage().PHP_EOL);
	exit(1);
}

$manifestVersion = trim((string) ($manifest['version'] ?? ''));
if (!hash_equals($version, $manifestVersion)) {
	fwrite(
		STDERR,
		sprintf("Version mismatch: VERSION=%s manifest.json=%s\n", $version, $manifestVersion)
	);
	exit(1);
}

if (str_contains($version, '-beta.') === false && str_contains($version, '-rc.') === false
		&& str_contains($version, '-alpha.') === false && str_contains($version, '-dev') === false) {
	fwrite(STDERR, "Warning: VERSION does not identify a prerelease.\n");
}

echo sprintf("Version validation passed: %s\n", $version);
