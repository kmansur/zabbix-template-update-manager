<?php

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root.'/actions/TemplateSelectionReview.php');
$listView = (string) file_get_contents($root.'/views/template.list.php');
$reviewView = (string) file_get_contents($root.'/views/template.selection.review.php');

$requiredControllerFragments = [
	"'templateids' => 'required|array_id'",
	'USER_TYPE_ZABBIX_ADMIN',
	'USER_TYPE_SUPER_ADMIN',
	'MAX_SELECTED_TEMPLATES = 500',
	'UpstreamIndexRepository',
	'TemplateVersionComparator'
];

foreach ($requiredControllerFragments as $fragment) {
	if (strpos($controller, $fragment) === false) {
		fwrite(STDERR, "Selected-template controller contract missing: {$fragment}\n");
		exit(1);
	}
}

if (strpos($controller, 'disableCsrfValidation') !== false) {
	fwrite(STDERR, "Selected-template review must keep native CSRF validation enabled.\n");
	exit(1);
}

$requiredViewFragments = [
	"new CCheckBox('all_templates')",
	"checkAll('",
	"'templateids'",
	"new CCheckBox('templateids['",
	'CActionButtonList',
	'ztum.templates.review_selected',
	'Review selected updates'
];

foreach ($requiredViewFragments as $fragment) {
	if (strpos($listView, $fragment) === false) {
		fwrite(STDERR, "Native selected-template list contract missing: {$fragment}\n");
		exit(1);
	}
}

if (strpos($listView, "'ztum.templates.review_selected' => [\n\t\t\t\t'name' => _('Review selected updates')") === false) {
	fwrite(STDERR, "Review selected updates must use CActionButtonList native submit mode.\n");
	exit(1);
}

if (strpos($listView, "'content' => (new CSimpleButton(_('Review selected updates')))") !== false) {
	fwrite(STDERR, "Review selected updates must not use an unbound CSimpleButton content override.\n");
	exit(1);
}

foreach ([
	'ztum.templates.prepare_selected',
	'Prepare selected updates',
	'CCsrfTokenHelper::get',
	'one template per HTTP request'
] as $fragment) {
	if (strpos($reviewView, $fragment) === false) {
		fwrite(STDERR, "Selected-template review batch-preparation contract missing: {$fragment}\n");
		exit(1);
	}
}

if (strpos($controller, 'BATCH_PREPARE_LIMIT') !== false
		|| strpos($reviewView, 'controlled batch preparation is limited') !== false) {
	fwrite(STDERR, "The obsolete 25-template update preparation ceiling must not remain.\n");
	exit(1);
}

$forbidden = [
	'API::Configuration()->import(',
	'API::Template()->update(',
	'API::Template()->delete(',
	'DB::update(',
	'DB::delete('
];

foreach ($forbidden as $fragment) {
	if (strpos($controller.$reviewView, $fragment) !== false) {
		fwrite(STDERR, "Selected-template review contains forbidden write operation: {$fragment}\n");
		exit(1);
	}
}

echo "Template selection action contract tests passed.\n";
