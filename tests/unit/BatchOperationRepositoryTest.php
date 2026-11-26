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

$repo->begin($state['id'], 'template-101', $e1, 'update', '1');
$afterFirst = $repo->finish($state['id'], 'template-101', 'succeeded', 'updated', null);
assertBatch('succeeded', $afterFirst['entries'][0]['state'], 'Completed first entry must persist success.');
assertBatch('pending', $afterFirst['entries'][1]['state'], 'Next entry must remain pending.');


try {
	$repo->begin($state['id'], 'template-102', $e2, 'install', '1');
	assertBatch(true, false, 'Wrong operation type must be rejected.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'type'), 'Wrong operation type must fail explicitly.');
}

try {
	$repo->begin($state['id'], 'template-102', $e2, 'update', '2');
	assertBatch(true, false, 'Different operator must not execute another operator batch.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'different operator'), 'Operator binding must fail explicitly.');
}

try {
	$repo->begin($state['id'], 'template-102', hash('sha256', 'tampered'), 'update', '1');
	assertBatch(true, false, 'Changed evidence must not start a persisted batch entry.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'evidence'), 'Evidence mismatch must fail explicitly.');
}

$repo->begin($state['id'], 'template-102', $e2, 'update', '1');
$file = $dir.DIRECTORY_SEPARATOR.$state['id'].'.json';
$data = json_decode((string) file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
$data['entries'][1]['started_at'] = gmdate('c', time() - 3600);
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
$recovered = $repo->markStaleRunningUncertain($state['id'], 60);
assertBatch('uncertain', $recovered['status'], 'Interrupted stale running batch must become uncertain.');
assertBatch('uncertain', $recovered['entries'][1]['state'], 'Interrupted entry must never return to pending automatically.');

try {
	$repo->begin($state['id'], 'template-102', $e2, 'update', '1');
	assertBatch(true, false, 'Uncertain entry must not be retried automatically.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'not pending'), 'Uncertain retry must fail closed.');
}


$terminal = $repo->create('install', [
	['subject' => 'uuid-'.str_repeat('a', 32), 'evidence_sha256' => hash('sha256', 'terminal'), 'manual_override' => false]
], '1');
$repo->begin($terminal['id'], 'uuid-'.str_repeat('a', 32), hash('sha256', 'terminal'), 'install', '1');
$repo->finish($terminal['id'], 'uuid-'.str_repeat('a', 32), 'failed', 'blocked', 'fixture');
$terminalPath = $dir.DIRECTORY_SEPARATOR.$terminal['id'].'.json';
touch($terminalPath, time() - 2592001);

$active = $repo->create('update', [
	['subject' => 'template-999', 'evidence_sha256' => hash('sha256', 'active'), 'manual_override' => false]
], '1');
$activePath = $dir.DIRECTORY_SEPARATOR.$active['id'].'.json';
touch($activePath, time() - 2592001);

$repo->create('update', [
	['subject' => 'template-1000', 'evidence_sha256' => hash('sha256', 'trigger-prune'), 'manual_override' => false]
], '1');

assertBatch(false, file_exists($terminalPath), 'Expired terminal batch evidence may be pruned.');
assertBatch(true, file_exists($activePath), 'Expired-looking active batch evidence must never be pruned automatically.');

// Regression: abandoned non-terminal plans must not accumulate forever (200-file cap).
$activeState = $repo->load($active['id']);
assertBatch('abandoned', $activeState['status'], 'Expired never-started plan must become explicit abandoned evidence.');
try {
	$repo->begin($active['id'], 'template-999', hash('sha256', 'active'), 'update', '1');
	assertBatch(true, false, 'Abandoned plan must not be resumed.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'abandoned'), 'Abandoned plan resume must fail explicitly.');
}

$eA = hash('sha256', 'partial-a');
$eB = hash('sha256', 'partial-b');
$partial = $repo->create('update', [
	['subject' => 'template-2001', 'evidence_sha256' => $eA, 'manual_override' => false],
	['subject' => 'template-2002', 'evidence_sha256' => $eB, 'manual_override' => false]
], '1');
$repo->begin($partial['id'], 'template-2001', $eA, 'update', '1');
$repo->finish($partial['id'], 'template-2001', 'succeeded', 'updated', null);
assertBatch('running', $repo->load($partial['id'])['status'], 'Partially executed plan stays running.');
$partialPath = $dir.DIRECTORY_SEPARATOR.$partial['id'].'.json';

$eF = hash('sha256', 'inflight');
$inflight = $repo->create('update', [
	['subject' => 'template-3001', 'evidence_sha256' => $eF, 'manual_override' => false]
], '1');
$repo->begin($inflight['id'], 'template-3001', $eF, 'update', '1');
$inflightPath = $dir.DIRECTORY_SEPARATOR.$inflight['id'].'.json';
$inflightData = json_decode((string) file_get_contents($inflightPath), true, 128, JSON_THROW_ON_ERROR);
$inflightData['entries'][0]['started_at'] = gmdate('c', time() - 2592100);
file_put_contents($inflightPath, json_encode($inflightData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

touch($partialPath, time() - 2592001);
touch($inflightPath, time() - 2592001);
touch($activePath, time() - 2592001);

$repo->create('update', [
	['subject' => 'template-1001', 'evidence_sha256' => hash('sha256', 'trigger-prune-2'), 'manual_override' => false]
], '1');

assertBatch(false, file_exists($activePath), 'Abandoned evidence must be pruned after its own retention window.');
assertBatch('abandoned', $repo->load($partial['id'])['status'], 'Expired partially executed plan must become abandoned, not deleted.');
$inflightState = $repo->load($inflight['id']);
assertBatch('uncertain', $inflightState['status'], 'Expired in-flight entry must become uncertain evidence.');
assertBatch('uncertain', $inflightState['entries'][0]['state'], 'Interrupted entry must be marked uncertain.');
assertBatch(true, file_exists($inflightPath), 'Interrupted write evidence must be kept for a new retention window.');

// Regression: lock contention must return the entry to pending so the same plan can resume.
$eR1 = hash('sha256', 'release-1');
$eR2 = hash('sha256', 'release-2');
$releasable = $repo->create('update', [
	['subject' => 'template-4001', 'evidence_sha256' => $eR1, 'manual_override' => false],
	['subject' => 'template-4002', 'evidence_sha256' => $eR2, 'manual_override' => false]
], '1');
$repo->begin($releasable['id'], 'template-4001', $eR1, 'update', '1');
$released = $repo->release($releasable['id'], 'template-4001');
assertBatch('pending', $released['entries'][0]['state'], 'Released entry must be pending again.');
assertBatch(null, $released['entries'][0]['started_at'], 'Released entry must clear its start time.');
assertBatch('pending', $released['status'], 'Plan without successes returns to pending.');
$repo->begin($releasable['id'], 'template-4001', $eR1, 'update', '1');
$repo->finish($releasable['id'], 'template-4001', 'succeeded', 'updated', null);
$repo->begin($releasable['id'], 'template-4002', $eR2, 'update', '1');
$released = $repo->release($releasable['id'], 'template-4002');
assertBatch('running', $released['status'], 'Plan with prior successes stays running after release.');
$repo->begin($releasable['id'], 'template-4002', $eR2, 'update', '1');
$done = $repo->finish($releasable['id'], 'template-4002', 'succeeded', 'updated', null);
assertBatch('completed', $done['status'], 'Released plan must complete normally when resumed.');
try {
	$repo->release($releasable['id'], 'template-4002');
	assertBatch(true, false, 'Only running entries may be released.');
}
catch (RuntimeException $exception) {
	assertBatch(true, str_contains($exception->getMessage(), 'not running'), 'Release of a finished entry must fail explicitly.');
}

foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
	@unlink($path);
}
@rmdir($dir);

echo "BatchOperationRepository tests passed.\n";
