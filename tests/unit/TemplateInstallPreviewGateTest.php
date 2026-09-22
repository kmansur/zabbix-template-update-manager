<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallPreviewGate;

require_once dirname(__DIR__, 2).'/src/Service/TemplateInstallPreviewGate.php';

function assertInstallGate($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$safe = TemplateInstallPreviewGate::evaluate(
	['added' => 12, 'updated' => 0, 'removed' => 0],
	['summary' => ['unresolved' => 0]]
);
assertInstallGate(true, $safe['safe'], 'Creation-only install preview must be eligible.');

$update = TemplateInstallPreviewGate::evaluate(
	['added' => 12, 'updated' => 1, 'removed' => 0],
	['summary' => ['unresolved' => 0]]
);
assertInstallGate(false, $update['safe'], 'Installation must not silently update existing configuration.');
assertInstallGate('install_would_modify_existing_configuration', $update['reason'], 'Update-block reason must be explicit.');

$remove = TemplateInstallPreviewGate::evaluate(
	['added' => 12, 'updated' => 0, 'removed' => 1],
	['summary' => ['unresolved' => 0]]
);
assertInstallGate(false, $remove['safe'], 'Installation must not remove existing configuration.');

$unresolved = TemplateInstallPreviewGate::evaluate(
	['added' => 12, 'updated' => 0, 'removed' => 0],
	['summary' => ['unresolved' => 1]]
);
assertInstallGate(false, $unresolved['safe'], 'Unresolved import identity must fail closed.');

$empty = TemplateInstallPreviewGate::evaluate(
	['added' => 0, 'updated' => 0, 'removed' => 0],
	['summary' => ['unresolved' => 0]]
);
assertInstallGate(false, $empty['safe'], 'Install preview without creation must fail closed.');
assertInstallGate('install_preview_contains_no_creations', $empty['reason'], 'No-creation block reason must be explicit.');

echo "TemplateInstallPreviewGate tests passed.\n";
