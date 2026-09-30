<?php

use Modules\ZabbixTemplateUpdateManager\Exception\LockContendedException;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateOperationLockService.php';

function assertOperationLock($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

assertOperationLock(
	'/var/lib/zabbix-template-update-manager/locks',
	TemplateOperationLockService::defaultDirectory(),
	'The default controlled-operation lock must live in the persistent private ZTUM runtime root.'
);

$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-lock-test-'.bin2hex(random_bytes(6));
$outer = new TemplateOperationLockService($dir);
$inner = new TemplateOperationLockService($dir);
$nestedBlocked = false;

$result = $outer->run('update', 'template-123', function () use ($inner, &$nestedBlocked) {
	try {
		$inner->run('rollback', 'template-123', static fn() => 'unexpected');
	}
	catch (LockContendedException $exception) {
		$nestedBlocked = $exception->getMachineCode() === 'lock_contended'
			&& str_contains($exception->getMessage(), 'already in progress');
	}

	return 'outer-ok';
});

assertOperationLock('outer-ok', $result, 'The holder of the global operation lock must execute normally.');

$metadataPath = $dir.DIRECTORY_SEPARATOR.'configuration-write.lock';
$metadata = json_decode((string) file_get_contents($metadataPath), true);
assertOperationLock(true, is_array($metadata), 'The global lock must persist valid metadata while/after the operation.');
assertOperationLock('update', (string) ($metadata['operation'] ?? ''), 'Lock metadata must identify the controlled operation.');
assertOperationLock('template-123', (string) ($metadata['subject'] ?? ''), 'Lock metadata must identify the controlled subject.');
assertOperationLock(true, $nestedBlocked, 'A second concurrent controlled operation must fail closed.');

$afterRelease = $inner->run('install', 'uuid-abcdef', static fn() => 'released-ok');
assertOperationLock('released-ok', $afterRelease, 'The operation lock must be released after the first callback finishes.');

@unlink($dir.DIRECTORY_SEPARATOR.'configuration-write.lock');
@rmdir($dir);

echo "TemplateOperationLockService tests passed.\n";
