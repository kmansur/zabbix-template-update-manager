<?php

$root = dirname(__DIR__);
$views = glob($root.'/views/*.php') ?: [];

$failures = [];

foreach ($views as $file) {
	$content = (string) file_get_contents($file);
	$navCount = substr_count($content, "new CTag('nav', true");
	$ariaCount = substr_count($content, "setAttribute('aria-label'");

	if ($navCount > $ariaCount) {
		$failures[] = basename($file).': every native nav control must have an aria-label.';
	}
}

$catalog = (string) file_get_contents($root.'/views/ztum.template.list.php');
foreach ([
	"new CLabel(_('Name'), 'filter_name')",
	"new CLabel(_('Status'), 'filter_status')"
] as $required) {
	if (strpos($catalog, $required) === false) {
		$failures[] = 'Catalog filter is missing an explicit native label contract: '.$required;
	}
}

$confirmationContracts = [
	'views/ztum.template.batch.prepare.php' => [
		'ztum-batch-confirm',
		'ztum-batch-confirm-local-overwrite'
	],
	'views/ztum.template.install.batch.prepare.php' => [
		'ztum-install-batch-confirm'
	]
];

foreach ($confirmationContracts as $relative => $ids) {
	$content = (string) file_get_contents($root.'/'.$relative);
	foreach ($ids as $id) {
		$position = strpos($content, "->setId('".$id."')");
		if ($position === false) {
			$failures[] = $relative.': missing confirmation control '.$id.'.';
			continue;
		}

		$window = substr($content, max(0, $position - 350), 700);
		if (strpos($window, '->setLabel(') === false) {
			$failures[] = $relative.': confirmation control '.$id.' must have an explicit label.';
		}
	}
}

if ($failures !== []) {
	foreach ($failures as $failure) {
		fwrite(STDERR, $failure."\n");
	}
	exit(1);
}

echo "Accessibility contracts passed.\n";
