<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplatePostUpdateValidationService;

require_once dirname(__DIR__, 2).'/src/Service/TemplatePostUpdateValidationService.php';

function assertPostUpdateValidation($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$candidate = [
	'uuid' => 'f8f7908280354f2abeed07dc788c3747',
	'vendor_version' => '7.0-8'
];
$goodAnalysis = [
	'comparison_error' => null,
	'template' => [
		'templateid' => '12345',
		'uuid' => 'f8f79082-8035-4f2a-beed-07dc788c3747',
		'upstream_status' => 'official_match',
		'vendor_version' => '7.0-8',
		'version_status' => 'current'
	],
	'content_status' => 'matches_current_upstream',
	'comparison_summary' => [
		'total' => 0
	]
];

$service = new TemplatePostUpdateValidationService(static fn(string $templateId): array => $goodAnalysis);
$result = $service->validate('12345', $candidate);
assertPostUpdateValidation('validated', $result['status'], 'Exact post-import current-upstream match should validate.');
assertPostUpdateValidation(true, $result['valid'], 'Exact post-import match must be valid.');
assertPostUpdateValidation([], $result['reasons'], 'Exact post-import match should have no validation reasons.');

$badAnalysis = $goodAnalysis;
$badAnalysis['template']['vendor_version'] = '7.0-7';
$badAnalysis['template']['version_status'] = 'update_available';
$badAnalysis['content_status'] = 'preview_against_newer_upstream';
$badAnalysis['comparison_summary']['total'] = 2;
$result = (new TemplatePostUpdateValidationService(static fn(string $templateId): array => $badAnalysis))
	->validate('12345', $candidate);
assertPostUpdateValidation('validation_failed', $result['status'], 'Incomplete update must fail post-import validation.');
assertPostUpdateValidation(false, $result['valid'], 'Incomplete update must not validate.');
assertPostUpdateValidation(true, in_array('vendor_version_mismatch', $result['reasons'], true), 'Version mismatch must be reported.');
assertPostUpdateValidation(true, in_array('remaining_import_differences', $result['reasons'], true), 'Remaining differences must be reported.');

$uuidMismatch = $goodAnalysis;
$uuidMismatch['template']['uuid'] = str_repeat('a', 32);
$result = (new TemplatePostUpdateValidationService(static fn(string $templateId): array => $uuidMismatch))
	->validate('12345', $candidate);
assertPostUpdateValidation(false, $result['valid'], 'Post-import UUID drift must fail validation.');
assertPostUpdateValidation(true, in_array('uuid_mismatch', $result['reasons'], true), 'UUID drift must be reported.');

$groupOnlyAnalysis = $goodAnalysis;
$groupOnlyAnalysis['content_status'] = 'local_modifications_detected';
$groupOnlyAnalysis['comparison_summary'] = [
	'added' => 0,
	'updated' => 2,
	'removed' => 0,
	'total' => 2,
	'by_entity' => [
		'host_groups' => ['added' => 0, 'updated' => 1, 'removed' => 0],
		'template_groups' => ['added' => 0, 'updated' => 1, 'removed' => 0]
	]
];

$groupOnlyService = new TemplatePostUpdateValidationService(
	static fn(string $templateId): array => $groupOnlyAnalysis
);
$result = $groupOnlyService->validateCreateOnlyInstall('12345', $candidate);
assertPostUpdateValidation('validated', $result['status'],
	'Create-only post-install validation must tolerate only pre-existing shared group differences.');
assertPostUpdateValidation(0, $result['remaining_changes'],
	'Shared group differences must not count as effective template-content differences after create-only install.');
assertPostUpdateValidation(2, $result['raw_remaining_changes'],
	'Raw post-install comparison differences must remain visible for diagnostics.');
assertPostUpdateValidation(2, $result['ignored_shared_changes'],
	'Ignored shared group differences must be counted explicitly.');
assertPostUpdateValidation(
	['host_groups', 'template_groups'],
	$result['ignored_shared_entities'],
	'Only the reviewed shared group entity classes may be ignored.'
);
assertPostUpdateValidation('matches_current_upstream', $result['content_status'],
	'Effective create-only content status should validate when only shared groups differ.');
assertPostUpdateValidation('local_modifications_detected', $result['raw_content_status'],
	'Raw content status must remain available for diagnostics.');

$result = $groupOnlyService->validate('12345', $candidate);
assertPostUpdateValidation(false, $result['valid'],
	'Standard post-update validation must continue to reject shared group differences.');

$mixedAnalysis = $groupOnlyAnalysis;
$mixedAnalysis['comparison_summary'] = [
	'added' => 0,
	'updated' => 3,
	'removed' => 0,
	'total' => 3,
	'by_entity' => [
		'template_groups' => ['added' => 0, 'updated' => 2, 'removed' => 0],
		'items' => ['added' => 0, 'updated' => 1, 'removed' => 0]
	]
];
$result = (new TemplatePostUpdateValidationService(
	static fn(string $templateId): array => $mixedAnalysis
))->validateCreateOnlyInstall('12345', $candidate);
assertPostUpdateValidation(false, $result['valid'],
	'Create-only post-install validation must fail closed on any non-group difference.');
assertPostUpdateValidation(1, $result['remaining_changes'],
	'Non-group differences must remain effective after shared-group filtering.');
assertPostUpdateValidation(true, in_array('remaining_import_differences', $result['reasons'], true),
	'Non-group post-install differences must be reported.');

echo "TemplatePostUpdateValidationService tests passed.\n";
