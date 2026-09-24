<?php

$root = dirname(__DIR__, 2);

require_once $root.'/src/Support/ZabbixUiCompat.php';

use Modules\ZabbixTemplateUpdateManager\Support\ZabbixUiCompat;

function assertZabbixUiCompat(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertZabbixUiCompat(
	ZabbixUiCompat::firstDefinedStyle(['ZTUM_STYLE_DOES_NOT_EXIST']) === '',
	'Compatibility lookup must return an empty string when no candidate exists.'
);

if (!defined('ZBX_STYLE_TABLE_PAGING')) {
	define('ZBX_STYLE_TABLE_PAGING', 'ztum-test-pager-7');
}
if (!defined('ZBX_STYLE_PAGING_BTN_CONTAINER')) {
	define('ZBX_STYLE_PAGING_BTN_CONTAINER', 'ztum-test-pager-container-7');
}

assertZabbixUiCompat(
	ZabbixUiCompat::pagerClass() === 'ztum-test-pager-7',
	'Zabbix 7.x pager style must be used as the fallback.'
);
assertZabbixUiCompat(
	ZabbixUiCompat::pagerContainerClass() === 'ztum-test-pager-container-7',
	'Zabbix 7.x pager-container style must be used as the fallback.'
);

if (!defined('ZBX_STYLE_PAGER')) {
	define('ZBX_STYLE_PAGER', 'ztum-test-pager-8');
}
if (!defined('ZBX_STYLE_PAGER_CONTAINER')) {
	define('ZBX_STYLE_PAGER_CONTAINER', 'ztum-test-pager-container-8');
}

assertZabbixUiCompat(
	ZabbixUiCompat::pagerClass() === 'ztum-test-pager-8',
	'Zabbix 8.x pager style must take precedence when available.'
);
assertZabbixUiCompat(
	ZabbixUiCompat::pagerContainerClass() === 'ztum-test-pager-container-8',
	'Zabbix 8.x pager-container style must take precedence when available.'
);

assertZabbixUiCompat(
	ZabbixUiCompat::firstDefinedStyle([
		'ZTUM_STYLE_DOES_NOT_EXIST',
		'ZBX_STYLE_PAGER',
		'ZBX_STYLE_TABLE_PAGING'
	]) === 'ztum-test-pager-8',
	'Compatibility lookup must return the first defined native style candidate.'
);

echo "Zabbix UI compatibility tests passed.\n";
