<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallDependencyService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateInstallDependencyService.php';

function assertInstallDependency($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$template = [
	'template' => 'Target template',
	'name' => 'Target template',
	'templates' => [
		['name' => 'ICMP Ping']
	],
	'discovery_rules' => [[
		'host_prototypes' => [[
			'templates' => [
				['name' => 'Linux by Zabbix agent']
			]
		]]
	]]
];

$result = TemplateInstallDependencyService::analyze($template, [
	['host' => 'ICMP Ping', 'name' => 'ICMP Ping']
]);

assertInstallDependency(
	['ICMP Ping', 'Linux by Zabbix agent'],
	$result['required'],
	'All direct and nested template links must be discovered.'
);
assertInstallDependency(['ICMP Ping'], $result['installed'], 'Installed dependencies must be identified.');
assertInstallDependency(['Linux by Zabbix agent'], $result['missing'], 'Missing dependencies must fail closed.');
assertInstallDependency(false, $result['complete'], 'Dependency analysis must be incomplete while a linked template is absent.');

$complete = TemplateInstallDependencyService::analyze($template, [
	['host' => 'ICMP Ping', 'name' => 'ICMP Ping'],
	['host' => 'Linux by Zabbix agent', 'name' => 'Linux by Zabbix agent']
]);
assertInstallDependency([], $complete['missing'], 'All dependencies should resolve when locally installed.');
assertInstallDependency(true, $complete['complete'], 'Complete dependency set must be explicit.');

$structural = TemplateInstallDependencyService::analyze(
	$template,
	[
		['host' => 'ICMP Ping', 'name' => 'ICMP Ping'],
		['host' => 'Linux by Zabbix agent', 'name' => 'Linux by Zabbix agent'],
		['host' => 'External trigger template', 'name' => 'External trigger template']
	],
	['External trigger template']
);
assertInstallDependency(
	['External trigger template', 'ICMP Ping', 'Linux by Zabbix agent'],
	$structural['required'],
	'Cross-template trigger/graph/dashboard references must join linked-template dependencies.'
);
assertInstallDependency([], $structural['missing'],
	'Installed structural dependencies must satisfy installation preflight.');
assertInstallDependency(true, $structural['complete'],
	'Structural dependencies already installed locally must allow preflight to continue.');

echo "TemplateInstallDependencyService tests passed.\n";
