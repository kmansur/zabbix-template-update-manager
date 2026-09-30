<?php

use Modules\ZabbixTemplateUpdateManager\Repository\BatchOperationRepository;

require_once dirname(__DIR__, 2).'/src/Repository/BatchOperationRepository.php';

function assertBatch($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-batch-state-'.bin2hex(random_bytes(6));
$repo = new BatchOperationRepository($dir);
$e1 = hash('sha256', 'one');
$e2 = hash('sha256', 'two');

$state = $repo->create('update', [
	['subject' => 'template-101', 'evidence_sha256' => $e1, 'manual_override' => false],
	['subject' => 'template-102', 'evidence_sha256' => $e2, 'manual_override' => true]
], '1');

assertBatch('pending', $state['status'], 'New persisted batch must be pending.');
assertBatch(2, count($state['entries']), 'Persisted batch must preserve reviewed entry order.');

$repo->begin($state['id'], 'template-101', $e1);
$afterFirst = $repo->finish($state['id'], 'template-101', 'succeeded', 'updated', null);
assertBatch('succeeded', $afterFirst['entries'][0]['state'], 'Completed first entry must persist success.');
assertBatch('pending', $afterFirst['entries'][1]['state'], 'Next entry must remain pending.');

try {
	$repo->begin($state['id'], 'template-102', hash('sha256', 'tampered'));
	assertBatch(true, false, 'Changed evidence must not start a persisted batch entry.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'evidence'), 'Evidence mismatch must fail explicitly.');
}

$repo->begin($state['id'], 'template-102', $e2);
$file = $dir.DIRECTORY_SEPARATOR.$state['id'].'.json';
$data = json_decode((string) file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
$data['entries'][1]['started_at'] = gmdate('c', time() - 3600);
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
$recovered = $repo->markStaleRunningUncertain($state['id'], 60);
assertBatch('uncertain', $recovered['status'], 'Interrupted stale running batch must become uncertain.');
assertBatch('uncertain', $recovered['entries'][1]['state'], 'Interrupted entry must never return to pending automatically.');

try {
	$repo->begin($state['id'], 'template-102', $e2);
	assertBatch(true, false, 'Uncertain entry must not be retried automatically.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'not pending'), 'Uncertain retry must fail closed.');
}

foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
	@unlink($path);
}
@rmdir($dir);

echo "BatchOperationRepository tests passed.\n";
