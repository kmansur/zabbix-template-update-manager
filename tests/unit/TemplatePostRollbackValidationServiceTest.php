<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplatePostRollbackValidationService;

require_once dirname(__DIR__, 2).'/src/Service/TemplatePostRollbackValidationService.php';

function assertPostRollback($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$uuid = 'f8f7908280354f2abeed07dc788c3747';
$artifact = [
	'uuid' => $uuid,
	'vendor_version' => '7.0-3',
	'format' => 'yaml',
	'source' => "zabbix_export:\n  version: '7.0'\n"
];
$template = [
	'templateid' => '12345',
	'uuid' => $uuid,
	'vendor_version' => '7.0-3'
];

$observedFormat = null;
$service = new TemplatePostRollbackValidationService(
	static fn(string $templateId): array => $template,
	static function (string $source, string $format) use (&$observedFormat): array {
		$observedFormat = $format;
		return [];
	}
);
$result = $service->validate('12345', $artifact);
assertPostRollback(true, $result['valid'], 'Matching identity/version and zero import differences should validate rollback.');
assertPostRollback(0, $result['remaining_changes'], 'Validated rollback should have zero remaining differences.');
assertPostRollback('yaml', $observedFormat, 'Post-rollback validation must compare the restored artifact using its YAML format.');

$wrongVersion = $template;
$wrongVersion['vendor_version'] = '7.0-8';
$result = (new TemplatePostRollbackValidationService(
	static fn(string $templateId): array => $wrongVersion,
	static fn(string $source, string $format): array => []
))->validate('12345', $artifact);
assertPostRollback(false, $result['valid'], 'Vendor-version mismatch must fail rollback validation.');
assertPostRollback(true, in_array('vendor_version_mismatch', $result['reasons'], true), 'Version mismatch reason must be explicit.');

$remainingDiff = [
	'templates' => [
		'updated' => [[
			'before' => ['name' => 'A'],
			'after' => ['name' => 'B']
		]]
	]
];
$result = (new TemplatePostRollbackValidationService(
	static fn(string $templateId): array => $template,
	static fn(string $source, string $format): array => $remainingDiff
))->validate('12345', $artifact);
assertPostRollback(false, $result['valid'], 'Remaining import differences must fail rollback validation.');
assertPostRollback(1, $result['remaining_changes'], 'Remaining rollback differences must be counted.');

echo "TemplatePostRollbackValidationService tests passed.\n";
