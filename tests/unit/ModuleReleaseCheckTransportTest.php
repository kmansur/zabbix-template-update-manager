<?php
use Modules\ZabbixTemplateUpdateManager\Service\ModuleReleaseCheckService as S;
require_once dirname(__DIR__, 2).'/src/Service/ModuleReleaseCheckService.php';

function checkTransport(bool $ok, string $reason): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: ".$reason.PHP_EOL);
        exit(1);
    }
}
function expectRejected(callable $transport, string $label): void {
    try {
        (new S($transport))->check('0.1.0-beta.61');
    }
    catch (Throwable $expected) {
        echo "PASS: ".$label." rejected safely\n";
        return;
    }
    checkTransport(false, $label.' unexpectedly accepted');
}

foreach ([403, 429, 500] as $status) {
    expectRejected(static fn(): array => ['status' => $status, 'body' => '[]'], 'HTTP '.$status);
}
expectRejected(static fn(): array => ['status' => 0, 'body' => false], 'timeout / connection failure');
expectRejected(static fn(): array => ['status' => 200, 'body' => '{invalid'], 'invalid JSON');
expectRejected(static fn(): array => ['status' => 200, 'body' => str_repeat('x', 524289)], 'oversized response');
expectRejected(static fn(): array => ['status' => 200, 'body' => '{"message":"rate limit"}'], 'unexpected JSON structure');
expectRejected(static fn(): string => 'bad', 'invalid transport structure');

$empty = (new S(static fn(): array => ['status' => 200, 'body' => '[]']))->check('0.1.0-beta.61');
checkTransport($empty['latest'] === null && $empty['update_available'] === false, 'Empty releases must remain safe.');
echo "PASS: empty release response returns no update\n";
