<?php

use Modules\ZabbixTemplateUpdateManager\Service\ImportCompareEntityExtractor;

require_once dirname(__DIR__, 2).'/src/Service/ImportCompareEntityExtractor.php';

function assertEntityExtractor($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$templateUuid = str_repeat('a', 32);
$itemUuid = str_repeat('b', 32);
$diff = [
	'templates' => [
		'updated' => [[
			'before' => ['uuid' => $templateUuid, 'template' => 'T', 'name' => 'T'],
			'after' => ['uuid' => $templateUuid, 'template' => 'T', 'name' => 'T'],
			'items' => [
				'updated' => [[
					'before' => ['uuid' => $itemUuid, 'name' => 'CPU', 'key' => 'cpu', 'delay' => '30s'],
					'after' => ['uuid' => $itemUuid, 'name' => 'CPU', 'key' => 'cpu', 'delay' => '1m']
				]]
			]
		]]
	]
];

$entities = ImportCompareEntityExtractor::extract($diff);
$templatePath = '/templates:uuid-'.$templateUuid;
$itemPath = $templatePath.'/items:uuid-'.$itemUuid;

assertEntityExtractor(true, isset($entities[$templatePath]), 'Template UUID must form a stable parent path.');
assertEntityExtractor(true, isset($entities[$itemPath]), 'Nested item UUID must form a stable child path.');
assertEntityExtractor('30s', $entities[$itemPath]['before']['delay'], 'Before state must be retained.');
assertEntityExtractor('1m', $entities[$itemPath]['after']['delay'], 'After state must be retained.');
assertEntityExtractor(true, $entities[$itemPath]['identity_reliable'], 'UUID identity must be authoritative.');

$fallback = ImportCompareEntityExtractor::extract([
	'template_groups' => [
		'added' => [[
			'after' => ['name' => 'Templates/Test']
		]]
	]
]);
$fallbackRecord = reset($fallback);
assertEntityExtractor(true, str_starts_with($fallbackRecord['identity'], 'key-'), 'Known unique fields must provide a deterministic fallback identity.');
assertEntityExtractor(true, $fallbackRecord['identity_reliable'], 'Known unique fields are a reliable fallback when UUID is absent.');

// Zabbix importcompare identifies graphs by name plus graph_items[].item.host,
// not by graph name alone. Two same-name graphs that point at different hosts
// must therefore remain distinct even when UUID is absent.
$graphs = ImportCompareEntityExtractor::extract([
	'graphs' => [
		'updated' => [
			[
				'before' => [
					'name' => 'Interface traffic',
					'graph_items' => [[
						'item' => ['host' => 'Template A', 'key' => 'net.if.in[a]']
					]],
					'width' => '900'
				],
				'after' => [
					'name' => 'Interface traffic',
					'graph_items' => [[
						'item' => ['host' => 'Template A', 'key' => 'net.if.in[a]']
					]],
					'width' => '1000'
				]
			],
			[
				'before' => [
					'name' => 'Interface traffic',
					'graph_items' => [[
						'item' => ['host' => 'Template B', 'key' => 'net.if.in[b]']
					]],
					'width' => '900'
				],
				'after' => [
					'name' => 'Interface traffic',
					'graph_items' => [[
						'item' => ['host' => 'Template B', 'key' => 'net.if.in[b]']
					]],
					'width' => '1000'
				]
			]
		]
	]
]);
assertEntityExtractor(2, count($graphs), 'Same-name graphs with different Zabbix graph-item hosts must not collide.');
$graphIdentities = array_values(array_map(static fn(array $entity): string => $entity['identity'], $graphs));
assertEntityExtractor(2, count(array_unique($graphIdentities)), 'Composite graph fallback identities must remain distinct.');

// For no-UUID updated entities, LOCAL/before is the common pivot shared by
// LOCAL->BASE and LOCAL->UPSTREAM comparisons. The fallback path must remain
// stable even when the candidate side changes an identity field such as key.
$historicalEntity = ImportCompareEntityExtractor::extract([
	'items' => [
		'updated' => [[
			'before' => ['name' => 'Legacy item', 'key' => 'legacy.key'],
			'after' => ['name' => 'Legacy item', 'key' => 'baseline.key']
		]]
	]
]);
$currentEntity = ImportCompareEntityExtractor::extract([
	'items' => [
		'updated' => [[
			'before' => ['name' => 'Legacy item', 'key' => 'legacy.key'],
			'after' => ['name' => 'Legacy item', 'key' => 'upstream.key']
		]]
	]
]);
assertEntityExtractor(
	array_key_first($historicalEntity),
	array_key_first($currentEntity),
	'Fallback identity must use the shared LOCAL state as the stable three-way pivot.'
);

// A real-world importcompare may still contain two no-UUID records that our
// fallback cannot distinguish. That must never abort the whole template
// analysis. Both records are retained and marked unreliable so downstream
// readiness remains fail-closed while other entities can still be analyzed.
$ambiguous = ImportCompareEntityExtractor::extract([
	'dashboards' => [
		'added' => [
			['after' => ['name' => 'Overview', 'display_period' => '30']],
			['after' => ['name' => 'Overview', 'display_period' => '60']]
		]
	]
]);
assertEntityExtractor(2, count($ambiguous), 'Ambiguous fallback identities must retain both native records.');
$ambiguousRecords = array_values($ambiguous);
assertEntityExtractor(false, $ambiguousRecords[0]['identity_reliable'], 'First colliding identity must be downgraded to unreliable.');
assertEntityExtractor(false, $ambiguousRecords[1]['identity_reliable'], 'Second colliding identity must be downgraded to unreliable.');
assertEntityExtractor('ambiguous_identity', $ambiguousRecords[0]['identity_issue'], 'First collision must expose the ambiguity reason.');
assertEntityExtractor('ambiguous_identity', $ambiguousRecords[1]['identity_issue'], 'Second collision must expose the ambiguity reason.');

// CConfigurationImportcompare strips direct before/after state from an updated
// structured entity when only nested children changed. This shape occurs in
// real templates (for example discovery-rule containers whose prototypes
// changed). The extractor must keep the nested diff instead of throwing, but it
// cannot prove the anonymous parent identity and must therefore fail closed for
// descendants.
$structuralOnly = ImportCompareEntityExtractor::extract([
	'discovery_rules' => [
		'updated' => [[
			'item_prototypes' => [
				'updated' => [[
					'before' => [
						'uuid' => str_repeat('c', 32),
						'name' => 'Prototype',
						'key' => 'prototype.key',
						'delay' => '30s'
					],
					'after' => [
						'uuid' => str_repeat('c', 32),
						'name' => 'Prototype',
						'key' => 'prototype.key',
						'delay' => '1m'
					]
				]]
			]
		]]
	]
]);
assertEntityExtractor(1, count($structuralOnly), 'Structural-only wrappers must retain nested entity changes.');
$structuralRecord = reset($structuralOnly);
assertEntityExtractor(false, $structuralRecord['identity_reliable'], 'A child under an anonymous structural wrapper must remain fail-closed.');
assertEntityExtractor('unresolved_parent_identity', $structuralRecord['identity_issue'], 'Structural wrapper descendants must expose the unresolved parent reason.');
assertEntityExtractor('30s', $structuralRecord['before']['delay'], 'Structural-wrapper child before state must be retained.');
assertEntityExtractor('1m', $structuralRecord['after']['delay'], 'Structural-wrapper child after state must be retained.');

echo "ImportCompareEntityExtractor tests passed.\n";
