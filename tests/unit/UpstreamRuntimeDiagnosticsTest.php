<?php

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;

require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';

function assertRuntimeDiagnostics($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

assertRuntimeDiagnostics(
	'https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/upstream-index/indexes/7.0.json',
	UpstreamIndexRepository::endpointForVersion('7.0.30'),
	'Zabbix 7.0 must resolve to the 7.0 upstream index.'
);
assertRuntimeDiagnostics(null, UpstreamIndexRepository::endpointForVersion('unknown'), 'Unknown versions must fail closed.');

$capabilities = UpstreamIndexRepository::transportCapabilities();
foreach (['curl', 'allow_url_fopen', 'openssl'] as $key) {
	if (!array_key_exists($key, $capabilities) || !is_bool($capabilities[$key])) {
		fwrite(STDERR, "Transport capability {$key} must be reported as a boolean.\n");
		exit(1);
	}
}

$controller = (string) file_get_contents(dirname(__DIR__, 2).'/actions/TemplateList.php');
$repository = (string) file_get_contents(dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php');
if (str_contains($controller, "'version' => '0.1.0-dev'") || !str_contains($controller, 'ProjectVersion::current()')) {
	fwrite(STDERR, "TemplateList must use the runtime VERSION source instead of a hard-coded development version.\n");
	exit(1);
}
if (str_contains($repository, 'Zabbix-Template-Update-Manager/0.1.0-dev')) {
	fwrite(STDERR, "Upstream HTTP requests must not use a stale hard-coded development user agent.\n");
	exit(1);
}

echo "Upstream runtime diagnostics tests passed.\n";
