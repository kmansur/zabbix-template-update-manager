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

	if (preg_match('/#[0-9a-fA-F]{6}\b/', $content)) {
		$fail('Hard-coded six-digit theme color found in '.basename($file).'. Use native ZBX_STYLE_* classes.');
	}
}

foreach ([
	$root.'/views/template.batch.prepare.php',
	$root.'/views/template.install.batch.prepare.php'
] as $batchView) {
	$content = (string) file_get_contents($batchView);
	if (strpos($content, 'setOnDocumentReady()') === false) {
		$fail('Batch JavaScript must use CScriptTag::setOnDocumentReady(): '.basename($batchView));
	}
}

$updateBatch = (string) file_get_contents($root.'/views/template.batch.prepare.php');
if (strpos($updateBatch, "new CCheckBox('review_select['.\$templateId.']', '1')") === false) {
	$fail('Reviewed update selection must be rendered with native CCheckBox controls.');
}

$helper = (string) file_get_contents($root.'/src/Support/FrontendUi.php');
foreach (['ZBX_STYLE_GREEN', 'ZBX_STYLE_ORANGE', 'ZBX_STYLE_RED', 'ZBX_STYLE_BLUE', 'ZBX_STYLE_GREY'] as $constant) {
	if (strpos($helper, "'".$constant."'") === false) {
		$fail('FrontendUi must map status tones to native Zabbix style constant '.$constant.'.');
	}
}

echo "Native Zabbix UI guard passed.\n";
