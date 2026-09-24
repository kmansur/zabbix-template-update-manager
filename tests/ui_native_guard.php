<?php

$root = dirname(__DIR__);
$views = glob($root.'/views/*.php') ?: [];

$fail = static function (string $message): void {
	fwrite(STDERR, $message."\n");
	exit(1);
};

if ($views === []) {
	$fail('No module views found for native UI validation.');
}

$forbiddenFragments = [
	"document.createElement('input')" => 'Raw DOM input creation bypasses native Zabbix CCheckBox rendering.',
	'document.createElement("input")' => 'Raw DOM input creation bypasses native Zabbix CCheckBox rendering.',
	'<input' => 'Raw HTML input markup is not allowed in module views.',
	'<button' => 'Raw HTML button markup is not allowed in module views.',
	'<select' => 'Raw HTML select markup is not allowed in module views.',
	"setAttribute('style'" => 'Inline CSS is not allowed in module views.',
	'style=' => 'Inline CSS is not allowed in module views.',
	'bootstrap' => 'Bootstrap must not be introduced into the native Zabbix frontend.',
	'tailwind' => 'Tailwind must not be introduced into the native Zabbix frontend.',
	'material-ui' => 'Material UI must not be introduced into the native Zabbix frontend.'
];

foreach ($views as $file) {
	$content = (string) file_get_contents($file);
	if (trim($content) === '') {
		$fail('Empty view stub must not remain in the repository: '.basename($file));
	}

	if (strpos($content, 'new CHtmlPage') === false) {
		$fail('User-facing view must be composed through CHtmlPage: '.basename($file));
	}

	foreach ($forbiddenFragments as $fragment => $message) {
		if (stripos($content, $fragment) !== false) {
			$fail($message.' File: '.basename($file));
		}
	}

	if (strpos($content, 'FrontendUi') === false) {
		$fail('User-facing views must use the shared native FrontendUi helper: '.basename($file));
	}
	if (strpos($content, "new CTag('h4'") !== false || strpos($content, 'new CTag("h4"') !== false) {
		$fail('Section headings must use FrontendUi::section() for consistent native Zabbix presentation: '.basename($file));
	}
	if (strpos($content, "new CTag('p'") !== false || strpos($content, 'new CTag("p"') !== false) {
		$fail('Descriptive copy must use FrontendUi::description()/message() for consistent native Zabbix presentation: '.basename($file));
	}

	if (preg_match('/#[0-9a-fA-F]{6}\b/', $content)) {
		$fail('Hard-coded six-digit theme color found in '.basename($file).'. Use native ZBX_STYLE_* classes.');
	}
}

$batchAssets = [
	$root.'/views/ztum.template.batch.prepare.php' => [
		'asset' => $root.'/assets/js/ztum-update-batch.js',
		'initializer' => 'ZTUMUpdateBatchInit'
	],
	$root.'/views/ztum.template.install.batch.prepare.php' => [
		'asset' => $root.'/assets/js/ztum-install-batch.js',
		'initializer' => 'ZTUMInstallBatchInit'
	]
];

foreach ($batchAssets as $batchView => $assetContract) {
	$content = (string) file_get_contents($batchView);
	if (strpos($content, 'setOnDocumentReady()') === false) {
		$fail('Batch JavaScript must use CScriptTag::setOnDocumentReady(): '.basename($batchView));
	}
	if (strpos($content, "<<<'JS'") !== false) {
		$fail('Behavior-heavy batch JavaScript must live in registered module assets: '.basename($batchView));
	}
	if (strpos($content, $assetContract['initializer']) === false) {
		$fail('Batch view must initialize its registered JavaScript asset: '.basename($batchView));
	}

	$asset = is_file($assetContract['asset'])
		? (string) file_get_contents($assetContract['asset'])
		: '';
	if ($asset === '' || strpos($asset, 'window.'.$assetContract['initializer']) === false) {
		$fail('Batch JavaScript asset is missing or does not expose its initializer: '.$assetContract['asset']);
	}
	foreach ($forbiddenFragments as $fragment => $message) {
		if (stripos($asset, $fragment) !== false) {
			$fail($message.' Asset: '.basename($assetContract['asset']));
		}
	}
	if (preg_match('/#[0-9a-fA-F]{6}\b/', $asset)) {
		$fail('Hard-coded theme color found in '.basename($assetContract['asset']).'. Use native ZBX_STYLE_* classes from server config.');
	}
}

$catalog = (string) file_get_contents($root.'/views/ztum.template.list.php');
if (strpos($catalog, 'setNoDataMessage') === false) {
	$fail('Template catalog must provide contextual native Zabbix no-data messaging.');
}

$helper = (string) file_get_contents($root.'/src/Support/FrontendUi.php');
foreach (['section(', 'description(', 'message(', 'fingerprint(', 'reason(', 'reasonList('] as $method) {
	if (strpos($helper, 'function '.$method) === false) {
		$fail('FrontendUi is missing shared presentation helper '.$method.'.');
	}
}

$updateBatch = (string) file_get_contents($root.'/views/ztum.template.batch.prepare.php');
if (strpos($updateBatch, "new CCheckBox('review_select['.\$templateId.']', '1')") === false) {
	$fail('Reviewed update selection must be rendered with native CCheckBox controls.');
}

foreach (['ZBX_STYLE_GREEN', 'ZBX_STYLE_ORANGE', 'ZBX_STYLE_RED', 'ZBX_STYLE_BLUE', 'ZBX_STYLE_GREY'] as $constant) {
	if (strpos($helper, "'".$constant."'") === false) {
		$fail('FrontendUi must map status tones to native Zabbix style constant '.$constant.'.');
	}
}

echo "Native Zabbix UI guard passed.\n";
