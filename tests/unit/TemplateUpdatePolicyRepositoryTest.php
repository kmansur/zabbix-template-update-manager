<?php

$root = dirname(__DIR__, 2);
require_once $root.'/src/Repository/TemplateUpdatePolicyRepository.php';

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateUpdatePolicyRepository;

function assertPolicyRepository(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

$dir = sys_get_temp_dir().'/ztum-policy-test-'.bin2hex(random_bytes(6));
if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
	fwrite(STDERR, "Unable to create temporary policy test directory.\n");
	exit(1);
}

$path = $dir.'/update-policy.json';

try {
	$repository = new TemplateUpdatePolicyRepository($path);
	$empty = $repository->snapshot();
	assertPolicyRepository(($empty['never_update'] ?? null) === [], 'Missing policy file must mean an empty policy.');

	$templates = [
		[
			'templateid' => '10001',
			'uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
			'name' => 'Template A',
			'technical_name' => 'Template A',
			'installation_status' => 'installed',
			'upstream_status' => 'official_match'
		],
		[
			'templateid' => '10002',
			'uuid' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
			'name' => 'Template B',
			'technical_name' => 'Template B',
			'installation_status' => 'installed',
			'upstream_status' => 'official_match'
		]
	];

	$result = $repository->setNeverUpdate($templates, '42');
	assertPolicyRepository($result['total'] === 2, 'Two Never update records must be stored.');
	assertPolicyRepository(is_file($path) && !is_link($path), 'Policy must be stored as a regular file.');

	if (DIRECTORY_SEPARATOR === '/') {
		$mode = fileperms($path);
		assertPolicyRepository($mode !== false && (($mode & 0777) === 0600), 'Policy file must be private mode 0600.');
	}

	assertPolicyRepository(
		$repository->isNeverUpdate('10001', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
		'Never update lookup by UUID/template ID must succeed.'
	);
	assertPolicyRepository(
		$repository->isNeverUpdate('10002', ''),
		'Never update lookup by stored template ID must succeed.'
	);

	$annotated = $repository->annotate([
		$templates[0],
		[
			'templateid' => '10003',
			'uuid' => 'cccccccccccccccccccccccccccccccc',
			'name' => 'Template C',
			'technical_name' => 'Template C',
			'installation_status' => 'installed',
			'upstream_status' => 'official_match'
		]
	]);

	assertPolicyRepository(
		($annotated[0]['update_policy'] ?? null) === TemplateUpdatePolicyRepository::POLICY_NEVER_UPDATE,
		'Stored template must be annotated Never update.'
	);
	assertPolicyRepository(
		($annotated[1]['update_policy'] ?? null) === TemplateUpdatePolicyRepository::POLICY_MANAGED,
		'Unstored template must remain Managed.'
	);

	$nonOfficial = $repository->annotate([[
		'templateid' => '10004',
		'uuid' => 'dddddddddddddddddddddddddddddddd',
		'name' => 'Local Template',
		'technical_name' => 'Local Template',
		'installation_status' => 'installed',
		'upstream_status' => 'not_found'
	]]);
	assertPolicyRepository(
		($nonOfficial[0]['update_policy'] ?? null) === 'not_applicable'
			&& empty($nonOfficial[0]['never_update']),
		'Installed templates without an official UUID match must expose update policy as Not applicable.'
	);

	$repository->allowUpdates([$templates[0]], '42');
	assertPolicyRepository(
		!$repository->isNeverUpdate('10001', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
		'Allow updates must remove the selected UUID from the persistent policy.'
	);
	assertPolicyRepository(
		$repository->isNeverUpdate('10002', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'),
		'Allow updates must not remove other Never update templates.'
	);

	file_put_contents($path, '{broken-json');
	$thrown = false;
	try {
		$repository->snapshot();
	}
	catch (RuntimeException $exception) {
		$thrown = true;
	}
	assertPolicyRepository($thrown, 'Malformed policy storage must fail closed.');
}
finally {
	foreach (glob($dir.'/*') ?: [] as $file) {
		@unlink($file);
	}
	@rmdir($dir);
}

echo "Template update policy repository tests passed.\n";
