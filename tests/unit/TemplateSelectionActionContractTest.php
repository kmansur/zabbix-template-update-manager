<?php

$root = dirname(__DIR__, 2);
$listController = (string) file_get_contents($root.'/actions/TemplateList.php');
$listView = (string) file_get_contents($root.'/views/ztum.template.list.php');
$manifest = (string) file_get_contents($root.'/manifest.json');

function assertSelectionContract(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

assertSelectionContract(
	strpos($listController, "'can_prepare_updates' => \$this->getUserType() === USER_TYPE_SUPER_ADMIN") !== false,
	'Bulk update preparation capability must remain super-admin-only.'
);

foreach ([
	"new CCheckBox('all_templates')",
	"checkAll('",
	"'templateids'",
	"new CCheckBox('templateids['",
	'CActionButtonList',
	'ztum.templates.prepare_selected',
	'Prepare selected updates'
] as $fragment) {
	assertSelectionContract(
		strpos($listView, $fragment) !== false,
		'Direct selected-update preparation contract missing: '.$fragment
	);
}

assertSelectionContract(
	strpos($listView, "'ztum.templates.prepare_selected' => [\n\t\t\t\t'name' => _('Prepare selected updates')") !== false,
	'Prepare selected updates must use CActionButtonList native submit mode.'
);

assertSelectionContract(
	strpos($listView, "CCsrfTokenHelper::get(\n\t\t\t\t\$installSelectionMode\n\t\t\t\t\t? 'ztum.templates.install_prepare_selected'\n\t\t\t\t\t: 'ztum.templates.prepare_selected'") !== false,
	'Direct bulk update submission must bind the prepare-selected CSRF token.'
);

assertSelectionContract(
	strpos($listView, "&& !empty(\$data['can_prepare_updates'])") !== false,
	'Bulk update checkboxes must not be actionable for users who cannot prepare updates.'
);

assertSelectionContract(
	strpos($listView, 'Preparation is read-only') !== false
		&& strpos($listView, 'requires confirmation') !== false,
	'The catalog must explain that preparation remains read-only and the later write requires confirmation.'
);

foreach ([
	'ztum.templates.review_selected',
	'Review selected updates',
	'ztum.template.selection.review'
] as $obsolete) {
	assertSelectionContract(
		strpos($listView.$manifest, $obsolete) === false,
		'Redundant selected-review workflow must not remain reachable: '.$obsolete
	);
}

assertSelectionContract(
	!is_file($root.'/actions/TemplateSelectionReview.php')
		&& !is_file($root.'/views/ztum.template.selection.review.php'),
	'Redundant selected-review controller/view must be removed from the module.'
);

foreach ([
	'API::Configuration()->import(',
	'API::Template()->update(',
	'API::Template()->delete(',
	'DB::update(',
	'DB::delete('
] as $fragment) {
	assertSelectionContract(
		strpos($listController.$listView, $fragment) === false,
		'Catalog selection flow contains forbidden write operation: '.$fragment
	);
}

echo "Template selection action contract tests passed.\n";
