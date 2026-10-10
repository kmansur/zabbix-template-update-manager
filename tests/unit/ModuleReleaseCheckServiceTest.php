<?php
use Modules\ZabbixTemplateUpdateManager\Service\ModuleReleaseCheckService as S;
require_once dirname(__DIR__, 2).'/src/Service/ModuleReleaseCheckService.php';

function expectRelease(bool $ok, string $reason): void {
    if (!$ok) {
        fwrite(STDERR, 'FAIL: '.$reason.PHP_EOL);
        exit(1);
    }
}

$url = 'https://github.com/kmansur/zabbix-template-update-manager/releases/tag/';
$fixtures = [
    ['tag_name' => 'v0.1.0-beta.60', 'html_url' => $url.'v0.1.0-beta.60', 'draft' => false, 'prerelease' => true],
    ['tag_name' => 'v0.1.0-beta.62', 'html_url' => $url.'v0.1.0-beta.62', 'draft' => false, 'prerelease' => true],
    ['tag_name' => 'v0.1.0-beta.99', 'html_url' => $url.'v0.1.0-beta.99', 'draft' => true, 'prerelease' => true],
    ['tag_name' => 'v0.1.0-beta.999', 'html_url' => 'https://attacker.example/release', 'draft' => false, 'prerelease' => true]
];
$result = S::compare('0.1.0-beta.61', $fixtures);
expectRelease($result['latest'] === '0.1.0-beta.62', 'Pick latest published prerelease; ignore drafts and external URLs.');
expectRelease($result['update_available'] === true, 'Detect newer prerelease.');
expectRelease(S::compare('0.1.0-beta.62', $fixtures)['update_available'] === false, 'Current version is not an update.');
expectRelease(S::compare('0.2.0', $fixtures)['update_available'] === false, 'Never downgrade stable version.');
expectRelease(S::compare('0.1.0-beta.61', [])['latest'] === null, 'Empty release list is safe.');
try {
    S::compare('unknown', $fixtures);
    expectRelease(false, 'Reject invalid installed versions.');
}
catch (RuntimeException $expected) {}
echo "PASS: release comparison, prereleases, drafts, URL allowlist and invalid versions\n";
