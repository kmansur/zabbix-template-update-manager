<?php

$root = dirname(__DIR__, 2);
require_once $root.'/src/Repository/TemplateOperationHistoryRepository.php';

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateOperationHistoryRepository;

function assertOperationHistory($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$dir = sys_get_temp_dir().'/ztum-history-test-'.bin2hex(random_bytes(6));
if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
	fwrite(STDERR, "Unable to create operation-history test directory.\n");
	exit(1);
}

$path = $dir.'/operation-history.json';

try {
	$repository = new TemplateOperationHistoryRepository($path);
	assertOperationHistory([], $repository->recent(10), 'Missing history file must return an empty history.');

	$first = [
		'id' => str_repeat('a', 32),
		'created_at' => '2026-09-23T20:00:00Z',
		'operation' => 'update',
		'subject' => 'template-10001',
		'status' => 'updated',
		'write_performed' => true,
		'actor_userid' => '1',
		'detail' => ''
	];
	$second = [
		'id' => str_repeat('b', 32),
		'created_at' => '2026-09-23T20:01:00Z',
		'operation' => 'rollback',
		'subject' => 'template-10001',
		'status' => 'rolled_back',
		'write_performed' => true,
		'actor_userid' => '1',
		'detail' => 'validated'
	];

	$repository->append($first);
	$repository->append($second);

	assertOperationHistory(
		[str_repeat('b', 32), str_repeat('a', 32)],
		array_column($repository->recent(10), 'id'),
		'Recent history must be returned newest first.'
	);

	if (DIRECTORY_SEPARATOR === '/') {
		$mode = fileperms($path);
		assertOperationHistory(true, $mode !== false && (($mode & 0777) === 0600), 'History file must be private mode 0600.');
	}

	file_put_contents($path, '{broken');
	$thrown = false;
	try {
		$repository->recent(10);
	}
	catch (RuntimeException $exception) {
		$thrown = true;
	}
	assertOperationHistory(true, $thrown, 'Malformed history storage must fail closed for reads.');
}
finally {
	foreach (glob($dir.'/*') ?: [] as $file) {
		@unlink($file);
	}
	@rmdir($dir);
}

echo "TemplateOperationHistoryRepository tests passed.\n";
