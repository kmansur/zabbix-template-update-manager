<?php

use Modules\ZabbixTemplateUpdateManager\Service\ZabbixExpressionHostExtractor;

require_once dirname(__DIR__, 2).'/src/Service/ZabbixExpressionHostExtractor.php';

function assertExpressionHosts(array $expected, string $expression, string $message): void {
	$actual = ZabbixExpressionHostExtractor::extract($expression);
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

assertExpressionHosts(
	['Target by agent'],
	'last(/Target by agent/target.metric)>0',
	'A simple history function must resolve its template host.'
);

assertExpressionHosts(
	['Jira Data Center by JMX'],
	'100*min(/Jira Data Center by JMX/jmx["com.atlassian.jira:name=BasicDataSource",NumActive],5m)'
		.'/last(/Jira Data Center by JMX/jmx["com.atlassian.jira:name=BasicDataSource",MaxTotal])>80',
	'Arithmetic division before last() must not invent an external host named last(.'
);

assertExpressionHosts(
	['Vyatta Virtual Router by SNMP'],
	'min(/Vyatta Virtual Router by SNMP/system.cpu.load.avg1,5m)'
		.'/last(/Vyatta Virtual Router by SNMP/system.cpu.num)>5',
	'Vyatta division expressions must resolve only the real template host.'
);

assertExpressionHosts(
	['External base', 'Target by agent'],
	'last(/Target by agent/target.metric)>0 and last(/External base/base.metric)>0',
	'Multiple real history-function hosts must still be preserved.'
);

function assertExpressionReferences(array $expected, string $expression, string $message): void {
	$actual = ZabbixExpressionHostExtractor::extractReferences($expression);
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

assertExpressionReferences(
	[
		['host' => 'Target by agent', 'item' => 'target.metric'],
		['host' => 'Target by agent', 'item' => 'target.other']
	],
	'min(/Target by agent/target.metric,5m)/last(/Target by agent/target.other)>1',
	'Trigger reference extraction must preserve both real host/item pairs across arithmetic division.'
);

echo "ZabbixExpressionHostExtractor tests passed.\n";
