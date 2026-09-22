<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallReferenceAuditService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateInstallReferenceAuditService.php';

function assertReferenceAudit($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

function issueCodes(array $audit): array {
	return array_values(array_map(
		static fn(array $issue): string => (string) ($issue['code'] ?? ''),
		$audit['issues'] ?? []
	));
}

$base = [
	'template' => 'Test template by HTTP',
	'name' => 'Test template by HTTP',
	'items' => [
		[
			'name' => 'Master',
			'key' => 'test.master'
		],
		[
			'name' => 'Dependent',
			'key' => 'test.dependent',
			'master_item' => ['key' => 'test.master'],
			'valuemap' => ['name' => 'State map']
		]
	],
	'discovery_rules' => [
		[
			'name' => 'Discover',
			'key' => 'test.discovery',
			'item_prototypes' => [
				[
					'name' => 'Prototype',
					'key' => 'test.prototype[{#ID}]',
					'master_item' => ['key' => 'test.master']
				]
			],
			'graph_prototypes' => [
				[
					'name' => 'Graph prototype',
					'graph_items' => [
						['item' => ['host' => 'Test template by HTTP', 'key' => 'test.prototype[{#ID}]']]
					]
				]
			]
		]
	],
	'valuemaps' => [
		['name' => 'State map']
	],
	'dashboards' => [
		[
			'name' => 'Overview',
			'pages' => [
				[
					'widgets' => [
						[
							'type' => 'item',
							'fields' => [
								[
									'type' => 'ITEM',
									'name' => 'itemid.0',
									'value' => [
										'host' => 'Test template by HTTP',
										'key' => 'test.master'
									]
								]
							]
						]
					]
				]
			]
		]
	],
	'triggers' => [
		[
			'name' => 'Problem',
			'expression' => 'last(/Test template by HTTP/test.master)>0'
		]
	]
];

$audit = TemplateInstallReferenceAuditService::analyze($base, []);
assertReferenceAudit(true, $audit['safe'], 'Internally complete template references must pass.');
assertReferenceAudit([], $audit['issues'], 'Safe template must report no structural reference issues.');
assertReferenceAudit(2, $audit['counts']['master_item_references'], 'Master-item references must be counted.');
assertReferenceAudit(1, $audit['counts']['value_map_references'], 'Value-map references must be counted.');
assertReferenceAudit(1, $audit['counts']['dashboard_item_references'], 'Dashboard ITEM references must be counted.');
assertReferenceAudit(1, $audit['counts']['trigger_host_references'], 'Trigger host references must be counted.');
assertReferenceAudit(1, $audit['counts']['graph_item_host_references'], 'Graph-item host references must be counted.');

$missingMaster = $base;
$missingMaster['items'][1]['master_item']['key'] = 'missing.master';
$audit = TemplateInstallReferenceAuditService::analyze($missingMaster, []);
assertReferenceAudit(false, $audit['safe'], 'Missing internal master item must block when there are no linked templates.');
assertReferenceAudit(true, in_array('missing_master_item', issueCodes($audit), true),
	'Missing internal master item reason must be explicit.');

$audit = TemplateInstallReferenceAuditService::analyze($missingMaster, ['Linked base template']);
assertReferenceAudit(false, in_array('missing_master_item', issueCodes($audit), true),
	'Unresolved master key must not be falsely blocked when a linked template could provide it.');

$missingMap = $base;
$missingMap['items'][1]['valuemap']['name'] = 'Missing map';
$audit = TemplateInstallReferenceAuditService::analyze($missingMap, []);
assertReferenceAudit(true, in_array('missing_value_map', issueCodes($audit), true),
	'Missing value-map reference must be detected.');

$badDashboardHost = $base;
$badDashboardHost['dashboards'][0]['pages'][0]['widgets'][0]['fields'][0]['value']['host'] = 'Other template';
$audit = TemplateInstallReferenceAuditService::analyze($badDashboardHost, []);
assertReferenceAudit(true, in_array('unresolved_dashboard_item_host', issueCodes($audit), true),
	'Unknown dashboard item host must be detected.');

$badDashboardItem = $base;
$badDashboardItem['dashboards'][0]['pages'][0]['widgets'][0]['fields'][0]['value']['key'] = 'missing.item';
$audit = TemplateInstallReferenceAuditService::analyze($badDashboardItem, []);
assertReferenceAudit(true, in_array('missing_dashboard_item', issueCodes($audit), true),
	'Missing self dashboard item must be detected.');

$badTriggerHost = $base;
$badTriggerHost['triggers'][0]['expression'] = 'last(/Other template/test.master)>0';
$audit = TemplateInstallReferenceAuditService::analyze($badTriggerHost, []);
assertReferenceAudit(true, in_array('unresolved_trigger_host', issueCodes($audit), true),
	'Unknown trigger host must be detected.');

$linkedTrigger = $base;
$linkedTrigger['triggers'][0]['expression'] = 'last(/Linked base template/base.key)>0';
$audit = TemplateInstallReferenceAuditService::analyze($linkedTrigger, ['Linked base template']);
assertReferenceAudit(false, in_array('unresolved_trigger_host', issueCodes($audit), true),
	'Installed linked-template trigger host must be accepted.');

$badGraphHost = $base;
$badGraphHost['discovery_rules'][0]['graph_prototypes'][0]['graph_items'][0]['item']['host'] = 'Other template';
$audit = TemplateInstallReferenceAuditService::analyze($badGraphHost, []);
assertReferenceAudit(true, in_array('unresolved_graph_item_host', issueCodes($audit), true),
	'Unknown graph item host must be detected.');

echo "TemplateInstallReferenceAuditService tests passed.\n";
