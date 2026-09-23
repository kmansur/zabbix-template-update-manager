<?php

$root = dirname(__DIR__, 2);
$manifest = json_decode(file_get_contents($root.'/manifest.json'), true);
$reviewAction = file_get_contents($root.'/actions/TemplateRollbackReview.php');
$rollbackAction = file_get_contents($root.'/actions/TemplateRollback.php');
$reviewView = file_get_contents($root.'/views/ztum.template.rollback.review.php');
$rollbackService = file_get_contents($root.'/src/Service/TemplateRollbackService.php');
$importService = file_get_contents($root.'/src/Service/TemplateConfigurationImportService.php');

function assertTemplateRollbackContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertTemplateRollbackContract(
	isset($manifest['actions']['ztum.template.rollback.review'])
		&& ($manifest['actions']['ztum.template.rollback.review']['class'] ?? null) === 'TemplateRollbackReview'
		&& ($manifest['actions']['ztum.template.rollback.review']['view'] ?? null) === 'ztum.template.rollback.review',
	'Rollback review action must be registered with its read-only review view.'
);
assertTemplateRollbackContract(
	isset($manifest['actions']['ztum.template.rollback'])
		&& ($manifest['actions']['ztum.template.rollback']['class'] ?? null) === 'TemplateRollback'
		&& ($manifest['actions']['ztum.template.rollback']['view'] ?? null) === 'ztum.template.rollback',
	'Controlled rollback action must be registered with its result view.'
);
assertTemplateRollbackContract(
	strpos($reviewAction, 'disableCsrfValidation') !== false,
	'Rollback review is read-only and may explicitly disable CSRF validation for GET navigation.'
);
assertTemplateRollbackContract(
	strpos($rollbackAction, 'disableCsrfValidation') === false,
	'Controlled rollback action must keep native CSRF validation enabled.'
);
assertTemplateRollbackContract(
	strpos($rollbackAction, 'USER_TYPE_SUPER_ADMIN') !== false
		&& strpos($rollbackAction, 'USER_TYPE_ZABBIX_ADMIN') === false,
	'Controlled rollback action must be restricted to super administrators.'
);
assertTemplateRollbackContract(
	strpos($rollbackAction, "'manifest_file' => 'required|string'") !== false
		&& strpos($rollbackAction, "'evidence_sha256' => 'required|string'") !== false
		&& strpos($rollbackAction, "'confirm' => 'required|in 1'") !== false,
	'Controlled rollback action must require explicit artifact, evidence and confirmation inputs.'
);
assertTemplateRollbackContract(
	strpos($reviewView, "CCsrfTokenHelper::get('ztum.template.rollback')") !== false
		&& strpos($reviewView, "new CCheckBox('confirm', '1')") !== false
		&& strpos($reviewView, "new CVar('evidence_sha256', \$evidenceSha)") !== false,
	'Rollback confirmation must use POST/CSRF, explicit checkbox confirmation and bound evidence fingerprint.'
);
assertTemplateRollbackContract(
	strpos($rollbackService, 'TemplateBackupService') !== false
		&& strpos($rollbackService, 'TemplateRollbackPreflightService') !== false
		&& strpos($rollbackService, 'TemplateConfigurationImportService') !== false
		&& strpos($rollbackService, 'TemplatePostRollbackValidationService') !== false,
	'Rollback orchestration must include fresh preflight, recovery backup, single import boundary and post-rollback validation.'
);
assertTemplateRollbackContract(
	preg_match('/API::Configuration\(\)->import\s*\(/', $rollbackService) !== 1
		&& preg_match_all('/API::Configuration\(\)->import\s*\(/', $importService) === 1,
	'Rollback must reuse the repository single configuration.import boundary rather than adding another write call.'
);

echo "Template rollback action contract tests passed.\n";
