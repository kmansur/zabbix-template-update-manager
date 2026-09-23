<?php

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;

require_once dirname(__DIR__, 2).'/src/Support/ZabbixVersion.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/UpstreamTemplateHistoryRepository.php';

function assertHistory($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$commit = str_repeat('a', 40);
$url = UpstreamTemplateHistoryRepository::buildUrl(
	'templates/os/linux/template_os_linux.yaml',
	$commit,
	25,
	10
);
assertHistory(true, str_starts_with($url, 'https://git.zabbix.com/rest/api/1.0/projects/ZBX/repos/zabbix/commits?'), 'History URL must use the canonical endpoint.');
assertHistory(true, str_contains($url, 'path=templates%2Fos%2Flinux%2Ftemplate_os_linux.yaml'), 'History URL must encode the path.');
assertHistory(true, str_contains($url, 'until='.$commit), 'History URL must pin the immutable source commit.');
assertHistory(true, str_contains($url, 'followRenames=true'), 'History URL must enable rename following.');

$page = UpstreamTemplateHistoryRepository::decodePage(json_encode([
	'values' => [
		['id' => str_repeat('b', 40), 'message' => 'first'],
		['id' => str_repeat('c', 40), 'message' => 'second']
	],
	'isLastPage' => false,
	'nextPageStart' => 2
]));
assertHistory(2, count($page['commits']), 'History page must normalize commit records.');
assertHistory(false, $page['is_last_page'], 'History page must retain pagination state.');
assertHistory(2, $page['next_page_start'], 'History page must retain the next cursor.');

$renameCommit = str_repeat('d', 40);
$changesUrl = UpstreamTemplateHistoryRepository::buildChangesUrl($renameCommit);
assertHistory(
	true,
	str_contains($changesUrl, '/commits/'.$renameCommit.'/changes?'),
	'Rename-path lookup must use the immutable commit changes endpoint.'
);

$previousPath = UpstreamTemplateHistoryRepository::decodePreviousPath(json_encode([
	'values' => [[
		'type' => 'MOVE',
		'path' => ['components' => ['templates', 'cloud', 'AWS', 'aws_http', 'template_cloud_aws_http.yaml']],
		'srcPath' => ['components' => ['templates', 'cloud', 'aws', 'template_aws_http.yaml']]
	]],
	'isLastPage' => true
]), 'templates/cloud/AWS/aws_http/template_cloud_aws_http.yaml');
assertHistory(
	'templates/cloud/aws/template_aws_http.yaml',
	$previousPath,
	'Rename-path decoding must return the source path that existed before the move.'
);

$noRename = UpstreamTemplateHistoryRepository::decodePreviousPath(json_encode([
	'values' => [[
		'type' => 'MODIFY',
		'path' => ['components' => ['templates', 'os', 'linux', 'template_os_linux.yaml']],
		'srcPath' => ['components' => ['templates', 'os', 'linux', 'template_os_linux.yaml']]
	]],
	'isLastPage' => true
]), 'templates/os/linux/template_os_linux.yaml');
assertHistory(null, $noRename, 'Ordinary modifications must not synthesize a rename path.');

$rejected = false;
try {
	UpstreamTemplateHistoryRepository::buildUrl('templates/os/../secret.yaml', $commit);
}
catch (RuntimeException $exception) {
	$rejected = true;
}
assertHistory(true, $rejected, 'History path traversal must be rejected.');

echo "UpstreamTemplateHistoryRepository tests passed.\n";
