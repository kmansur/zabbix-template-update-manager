<?php

$root = dirname(__DIR__, 2);
$manifest = (string) file_get_contents($root.'/manifest.json');
$action = (string) file_get_contents($root.'/actions/TemplateUpdatePolicy.php');
$list = (string) file_get_contents($root.'/views/ztum.template.list.php');
$listController = (string) file_get_contents($root.'/actions/TemplateList.php');
$analysis = (string) file_get_contents($root.'/src/Service/TemplateUpdateAnalysisService.php');
$preflight = (string) file_get_contents($root.'/src/Service/TemplateUpdatePreflightService.php');
$controlled = (string) file_get_contents($root.'/src/Service/TemplateControlledUpdateService.php');

function assertUpdatePolicyContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertUpdatePolicyContract(
	strpos($manifest, '"ztum.templates.update_policy"') !== false
		&& strpos($manifest, '"class": "TemplateUpdatePolicy"') !== false,
	'Never update policy action must be registered.'
);

assertUpdatePolicyContract(
	strpos($action, 'disableCsrfValidation') === false
		&& strpos($action, 'USER_TYPE_SUPER_ADMIN') !== false
		&& strpos($action, "'templateids' => 'required|array_id'") !== false
		&& strpos($action, "'policy_operation' => 'required|in never_update,allow_updates'") !== false,
	'Policy mutation must use native CSRF, Super Admin permissions and bounded validated input.'
);

assertUpdatePolicyContract(
	strpos($action, 'TemplateOperationLockService') !== false
		&& strpos($action, "->run(\n\t\t\t\t'policy'") !== false
		&& strpos($action, 'TemplateUpdatePolicyRepository') !== false,
	'Policy mutation must serialize with controlled configuration operations and use the private policy repository.'
);

assertUpdatePolicyContract(
	strpos($action, 'UpstreamMatcher::attach') !== false
		&& strpos($action, "'official_match'") !== false,
	'Never update marking must be limited to authoritative official-template identities.'
);

assertUpdatePolicyContract(
	strpos($listController, 'never_update') !== false
		&& strpos($listController, 'TemplateUpdatePolicyRepository') !== false
		&& strpos($listController, "'can_manage_policy'") !== false,
	'Catalog controller must attach/filter persistent Never update policy state.'
);

foreach ([
	"->addValue(_('Never update'), 'never_update')",
	"_('Update policy')",
	"_('Never update')",
	"_('Allow updates')",
	"CCsrfTokenHelper::get('ztum.templates.update_policy')",
	"'policy_operation'",
	"'allow_updates'",
	"'never_update'"
] as $fragment) {
	assertUpdatePolicyContract(
		strpos($list, $fragment) !== false,
		'Catalog Never update UI contract missing: '.$fragment
	);
}

assertUpdatePolicyContract(
	strpos($analysis, 'applyUpdatePolicyGate') !== false
		&& strpos($analysis, "'blocked_update_policy'") !== false
		&& strpos($analysis, "'update_policy_never'") !== false,
	'Read-only analysis must expose Never update as a fail-closed readiness blocker.'
);

assertUpdatePolicyContract(
	strpos($preflight, 'TemplateUpdatePolicyRepository') !== false
		&& strpos($preflight, "'blocked_update_policy'") !== false
		&& strpos($preflight, "'update_policy_never'") !== false
		&& strpos($preflight, "'update_policy_unavailable'") !== false,
	'Fresh preflight must recheck the persistent policy and fail closed when unavailable.'
);

assertUpdatePolicyContract(
	strpos($controlled, '$this->policyChecker') !== false
		&& substr_count($controlled, "'blocked_update_policy'") >= 2
		&& strpos($controlled, "'write_performed' => false") !== false,
	'Controlled update must recheck policy immediately before candidate import.'
);

$combined = $action.$list.$listController.$analysis.$preflight;
foreach ([
	'API::Configuration()->import(',
	'API::Template()->update(',
	'API::Template()->delete(',
	'DB::update(',
	'DB::delete('
] as $fragment) {
	assertUpdatePolicyContract(
		strpos($combined, $fragment) === false,
		'Never update policy flow must not introduce a Zabbix configuration write: '.$fragment
	);
}

echo "Template update policy action contracts passed.\n";
