<?php

use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;

require_once dirname(__DIR__, 2).'/src/Support/ProjectVersion.php';

function assertProjectVersion($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$expected = trim((string) file_get_contents(dirname(__DIR__, 2).'/VERSION'));
assertProjectVersion($expected, ProjectVersion::current(), 'Runtime project version must come from VERSION.');
assertProjectVersion(
	'Zabbix-Template-Update-Manager/'.$expected,
	ProjectVersion::userAgent(),
	'HTTP user agent must include the runtime project version.'
);

echo "ProjectVersion tests passed.\n";
