<?php

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;

require_once dirname(__DIR__, 2).'/src/Support/ZabbixVersion.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamTemplateSourceRepository.php';

function assertSourceValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function assertSourceThrows(callable $callback, string $message): void {
	try {
		$callback();
	}
	catch (RuntimeException $exception) {
		return;
	}

	fwrite(STDERR, $message."\n");
	exit(1);
}

$commit = str_repeat('a', 40);
$path = 'templates/os/linux/template_os_linux.yaml';
$url = UpstreamTemplateSourceRepository::buildUrl($commit, $path);
assertSourceValue(
	'https://git.zabbix.com/projects/ZBX/repos/zabbix/raw/templates/os/linux/template_os_linux.yaml?at='.$commit,
	$url,
	'Canonical source URL was not built as expected.'
);
$mirrorUrl = UpstreamTemplateSourceRepository::buildMirrorUrl($commit, $path);
assertSourceValue(
	'https://raw.githubusercontent.com/zabbix/zabbix/'.$commit.'/templates/os/linux/template_os_linux.yaml',
	$mirrorUrl,
	'Official mirror source URL was not built as expected.'
);
assertSourceValue(true, UpstreamIndexRepository::isValidTemplatePath($path), 'Valid official source path was rejected.');
assertSourceValue(false, UpstreamIndexRepository::isValidTemplatePath('templates/os/../secret.yaml'), 'Traversal path must be rejected.');
assertSourceThrows(
	fn() => UpstreamTemplateSourceRepository::buildUrl('not-a-commit', $path),
	'Invalid source commits must be rejected.'
);
assertSourceThrows(
	fn() => UpstreamTemplateSourceRepository::buildUrl($commit, '../secret.yaml'),
	'Invalid source paths must be rejected.'
);

echo "UpstreamTemplateSourceRepository tests passed.\n";
