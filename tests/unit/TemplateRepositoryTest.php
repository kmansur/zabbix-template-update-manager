<?php

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;

require_once dirname(__DIR__, 2).'/src/Repository/TemplateRepository.php';

function assertSameValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$options = TemplateRepository::queryOptions();

assertSameValue(
	['templateid', 'host', 'name', 'uuid', 'vendor_name', 'vendor_version'],
	$options['output'],
	'Template repository output fields changed unexpectedly.'
);
assertSameValue('count', $options['selectHosts'], 'Host relation must be requested as a count.');
assertSameValue(['groupid', 'name'], $options['selectTemplateGroups'], 'Template groups query changed unexpectedly.');
assertSameValue('name', $options['sortfield'], 'Templates must be sorted by visible name.');
assertSameValue('ASC', $options['sortorder'], 'Template sort order must be ascending.');

echo "TemplateRepository tests passed.\n";
