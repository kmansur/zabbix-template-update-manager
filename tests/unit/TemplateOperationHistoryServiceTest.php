<?php

$root = dirname(__DIR__, 2);
require_once $root.'/src/Repository/TemplateOperationHistoryRepository.php';
require_once $root.'/src/Service/TemplateOperationHistoryService.php';

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateOperationHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;

function assertHistoryService($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$dir = sys_get_temp_dir().'/ztum-history-service-'.bin2hex(random_bytes(6));
mkdir($dir, 0700, true);

try {
	$repository = new TemplateOperationHistoryRepository($dir.'/history.json');
	$service = new TemplateOperationHistoryService($repository);

	$entry = $service->record(
		'update',
		'template-123',
		['status' => 'updated', 'write_performed' => true, 'reason' => null],
		null,
		'42'
	);

	assertHistoryService('updated', $entry['status'], 'Successful controlled result status must be recorded.');
	assertHistoryService(true, $entry['write_performed'], 'Configuration-write state must be preserved.');
	assertHistoryService('42', $entry['actor_userid'], 'Actor user ID must be preserved.');

	$exception = new RuntimeException("simulated\nsecret-looking multiline failure");
	$error = $service->record('install', 'uuid-abc', null, $exception, '42');
	assertHistoryService('error', $error['status'], 'Exceptions must produce an explicit error history state.');
	assertHistoryService(null, $error['write_performed'], 'Unknown write outcome must remain unknown rather than be guessed false.');
	assertHistoryService(false, str_contains($error['detail'], "\n"), 'History detail must be single-line sanitized.');

	$recent = $repository->recent(10);
	assertHistoryService(2, count($recent), 'Two history records must be persisted.');
}
finally {
	foreach (glob($dir.'/*') ?: [] as $file) {
		@unlink($file);
	}
	@rmdir($dir);
}

echo "TemplateOperationHistoryService tests passed.\n";
