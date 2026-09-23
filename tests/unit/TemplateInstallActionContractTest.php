<?php

$root = dirname(__DIR__, 2);
$review = (string) file_get_contents($root.'/actions/TemplateInstallReview.php');
$install = (string) file_get_contents($root.'/actions/TemplateInstall.php');
$preflight = (string) file_get_contents($root.'/src/Service/TemplateInstallPreflightService.php');
$controlled = (string) file_get_contents($root.'/src/Service/TemplateControlledInstallService.php');
$post = (string) file_get_contents($root.'/src/Service/TemplatePostInstallValidationService.php');
$listController = (string) file_get_contents($root.'/actions/TemplateList.php');
$list = (string) file_get_contents($root.'/views/template.list.php');
$reviewView = (string) file_get_contents($root.'/views/template.install.review.php');
$resultView = (string) file_get_contents($root.'/views/template.install.php');

function assertInstallContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertInstallContract(strpos($review, 'disableCsrfValidation') !== false,
	'Read-only installation review may disable CSRF validation.');
assertInstallContract(strpos($install, 'disableCsrfValidation') === false,
	'Controlled installation write must keep native CSRF validation enabled.');
assertInstallContract(strpos($install, 'USER_TYPE_SUPER_ADMIN') !== false,
	'Controlled installation must be super-admin-only.');
assertInstallContract(strpos($install, "'confirm' => 'required|in 1'") !== false,
	'Controlled installation must require explicit confirmation.');

assertInstallContract(strpos($preflight, 'TemplateImportCompareService') !== false,
	'Install preflight must use native configuration.importcompare.');
assertInstallContract(strpos($preflight, 'TemplateInstallDependencyService') !== false,
	'Install preflight must verify linked-template dependencies.');
assertInstallContract(strpos($preflight, 'TemplateInstallReferenceAuditService') !== false
		&& strpos($preflight, "'blocked_references'") !== false
		&& strpos($preflight, "'reference_audit'") !== false,
	'Install preflight must run the structural reference audit and fail closed on proven unresolved references.');
assertInstallContract(strpos($preflight, 'TemplateInstallPreviewGate') !== false,
	'Install preflight must enforce creation-only preview semantics.');
assertInstallContract(
	strpos($preflight, 'TemplateImportCompareService::PROFILE_INSTALL') !== false
		&& strpos($preflight, "'import_rule_profile'") !== false,
	'Install preflight must bind the reviewed create-only import rule profile into fresh evidence.'
);
assertInstallContract(
	strpos($preflight, 'source_sha256') !== false
		&& strpos($preflight, 'content_sha256') !== false
		&& strpos($preflight, 'import_sha256') !== false,
	'Install preflight evidence must bind immutable source/content/import fingerprints.'
);
assertInstallContract(strpos($preflight, 'technical_name_collision') !== false,
	'Install preflight must fail closed on local technical-name collision.');

assertInstallContract(strpos($controlled, 'TemplateInstallPreflightService') !== false,
	'Controlled install must rerun fresh server-side preflight.');
assertInstallContract(strpos($controlled, 'hash_equals') !== false,
	'Controlled install must reject changed evidence before writing.');
assertInstallContract(strpos($controlled, 'TemplateConfigurationImportService') !== false,
	'Controlled install must reuse the single approved configuration-import boundary.');
assertInstallContract(
	strpos($controlled, 'TemplateImportCompareService::PROFILE_INSTALL') !== false
		&& strpos($controlled, "'status' => 'import_failed'") !== false
		&& strpos($controlled, "'write_outcome' => 'uncertain'") !== false
		&& strpos($controlled, 'TemplateInstallFailureInspectionService') !== false,
	'Controlled install must keep create-only rules and classify import rejection as an uncertain write requiring inspection.'
);
assertInstallContract(strpos($controlled, 'TemplatePostInstallValidationService') !== false,
	'Controlled install must perform fresh post-install validation.');
assertInstallContract(strpos($controlled, 'API::Configuration()->import(') === false,
	'Controlled install service must not introduce a second direct configuration.import call.');
assertInstallContract(strpos($post, 'TemplatePostUpdateValidationService') !== false,
	'Post-install validation must reuse authoritative current-upstream validation.');

assertInstallContract(strpos($list, 'Review installation') !== false,
	'Catalog must expose a distinct installation-review action.');
assertInstallContract(strpos($listController, 'CPagerHelper::paginate') !== false
		&& strpos($listController, "CPagerHelper::savePage('ztum.template.catalog'") !== false
		&& strpos($list, 'setPageNavigation') !== false,
	'Expanded official catalog must use native Zabbix pagination end to end.');
assertInstallContract(strpos($reviewView, 'Structural reference audit') !== false
		&& strpos($reviewView, "'blocked_references'") !== false
		&& strpos($reviewView, "'unresolved_internal_references'") !== false,
	'Installation review must expose structural-reference audit state and blockers.');
assertInstallContract(strpos($reviewView, 'there is no prior local rollback artifact') !== false,
	'Installation review must disclose the absence of a prior rollback artifact.');
assertInstallContract(strpos($resultView, 'does not automatically uninstall') !== false,
	'Install result must disclose that failed validation does not trigger automatic uninstall.');
assertInstallContract(
	strpos($resultView, "'import_failed'") !== false
		&& strpos($resultView, 'Import attempted') !== false
		&& strpos($resultView, 'Confirmed configuration write') !== false
		&& strpos($resultView, 'Read-only post-failure inspection') !== false,
	'Individual install result must distinguish import attempt, confirmed write and post-failure target inspection.'
);

echo "Template install action contract tests passed.\n";
