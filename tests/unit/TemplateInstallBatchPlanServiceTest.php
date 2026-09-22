<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallBatchPlanService;

require_once dirname(__DIR__, 2).'/src/Service/TemplateInstallBatchPlanService.php';

function assertInstallBatchPlan($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$uuid1 = str_repeat('a', 32);
$uuid2 = str_repeat('b', 32);
$uuid3 = str_repeat('c', 32);
$uuid4 = str_repeat('d', 32);

$service = new TemplateInstallBatchPlanService(
	static function (string $uuid) use ($uuid1, $uuid2, $uuid3): array {
		if ($uuid === $uuid1) {
			return [
				'status' => 'passed',
				'write_enabled' => true,
				'evidence_sha256' => hash('sha256', 'install-'.$uuid),
				'candidate' => [
					'name' => 'Ready template',
					'vendor_version' => '7.0-2'
				],
				'dependencies' => [
					'required' => ['ICMP Ping'],
					'missing' => []
				]
			];
		}

		if ($uuid === $uuid2) {
			return [
				'status' => 'blocked_dependencies',
				'write_enabled' => false,
				'reason' => 'missing_template_dependencies',
				'evidence_sha256' => '',
				'candidate' => [
					'name' => 'Blocked template',
					'vendor_version' => '7.0-5'
				],
				'dependencies' => [
					'required' => ['Linux by Zabbix agent'],
					'missing' => ['Linux by Zabbix agent']
				]
			];
		}

		if ($uuid === $uuid3) {
			return [
				'status' => 'blocked_preview',
				'write_enabled' => false,
				'reason' => 'install_would_modify_existing_configuration',
				'evidence_sha256' => '',
				'candidate' => ['name' => 'Unsafe template', 'vendor_version' => '7.0-1'],
				'dependencies' => ['required' => [], 'missing' => []]
			];
		}

		return [
			'status' => 'blocked_references',
			'write_enabled' => false,
			'reason' => 'unresolved_internal_references',
			'evidence_sha256' => '',
			'candidate' => ['name' => 'Reference blocked template', 'vendor_version' => '7.0-3'],
			'dependencies' => ['required' => [], 'missing' => []],
			'reference_audit' => [
				'safe' => false,
				'issues' => [
					['code' => 'missing_value_map', 'reference' => 'State map']
				]
			]
		];
	}
);

$plan = $service->build([$uuid1, $uuid2, $uuid3, $uuid4]);

assertInstallBatchPlan(4, $plan['summary']['selected'], 'All selected install candidates must be represented.');
assertInstallBatchPlan(1, $plan['summary']['ready'], 'Only passed install preflight may become Ready.');
assertInstallBatchPlan(3, $plan['summary']['blocked'], 'Dependency/preview/reference failures must remain Blocked.');
assertInstallBatchPlan('ready', $plan['items'][0]['category'], 'Passed install candidate must be Ready.');
assertInstallBatchPlan(hash('sha256', 'install-'.$uuid1), $plan['items'][0]['evidence_sha256'],
	'Ready install candidate must retain bound evidence.');
assertInstallBatchPlan('blocked', $plan['items'][1]['category'], 'Missing dependency must block batch installation.');
assertInstallBatchPlan(['Linux by Zabbix agent'], $plan['items'][1]['missing_dependencies'],
	'Missing dependencies must be surfaced to the batch review.');
assertInstallBatchPlan('install_would_modify_existing_configuration', $plan['items'][2]['reason'],
	'Unsafe import preview reason must remain explicit.');
assertInstallBatchPlan(
	[['code' => 'missing_value_map', 'reference' => 'State map']],
	$plan['items'][3]['reference_issues'],
	'Structural reference issues must be propagated to batch review.'
);

echo "TemplateInstallBatchPlanService tests passed.\n";
