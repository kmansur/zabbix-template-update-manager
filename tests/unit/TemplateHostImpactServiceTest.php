<?php

$root = dirname(__DIR__, 2);
require_once $root.'/src/Service/TemplateHostImpactService.php';

use Modules\ZabbixTemplateUpdateManager\Service\TemplateHostImpactService;

function assertHostImpact($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$graph = [
	['templateid' => '100', 'parentTemplates' => []],
	['templateid' => '200', 'parentTemplates' => [['templateid' => '100']]],
	['templateid' => '300', 'parentTemplates' => [['templateid' => '200']]],
	['templateid' => '400', 'parentTemplates' => [['templateid' => '100']]],
	['templateid' => '500', 'parentTemplates' => []]
];

$hostCounts = [
	'100' => 2,
	'100,200,300,400' => 11
];

$service = new TemplateHostImpactService(
	static fn(): array => $graph,
	static function (array $templateIds) use ($hostCounts): int {
		sort($templateIds, SORT_STRING);
		return $hostCounts[implode(',', $templateIds)] ?? 0;
	}
);

$result = $service->analyze('100');
assertHostImpact('complete', $result['status'], 'Host-impact status must be complete.');
assertHostImpact(2, $result['direct_host_count'], 'Direct host count must include only the selected template.');
assertHostImpact(9, $result['indirect_host_count'], 'Indirect host count must be the additional inherited reach.');
assertHostImpact(11, $result['total_host_count'], 'Total host count must be deduplicated by the host API aggregate.');
assertHostImpact(3, $result['dependent_template_count'], 'All descendant templates must be counted.');
assertHostImpact(['100', '200', '300', '400'], $result['impacted_template_ids'], 'Impacted template IDs must include recursive descendants.');

$thrown = false;
try {
	$service->analyze('999');
}
catch (RuntimeException $exception) {
	$thrown = str_contains($exception->getMessage(), 'absent');
}
assertHostImpact(true, $thrown, 'Unknown template IDs must fail closed.');

$cycleGraph = [
	['templateid' => '100', 'parentTemplates' => [['templateid' => '200']]],
	['templateid' => '200', 'parentTemplates' => [['templateid' => '100']]]
];
$cycle = new TemplateHostImpactService(
	static fn(): array => $cycleGraph,
	static fn(array $ids): int => count($ids)
);
$cycleResult = $cycle->analyze('100');
assertHostImpact(1, $cycleResult['dependent_template_count'], 'Cycle-safe traversal must not count the selected template as its own descendant.');
assertHostImpact(['100', '200'], $cycleResult['impacted_template_ids'], 'Cycle-safe traversal must terminate with unique impacted IDs.');

echo "TemplateHostImpactService tests passed.\n";
