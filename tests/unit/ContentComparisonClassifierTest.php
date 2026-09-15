<?php

use Modules\ZabbixTemplateUpdateManager\Service\ContentComparisonClassifier;

require_once dirname(__DIR__, 2).'/src/Service/ContentComparisonClassifier.php';

function assertContentClass($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

assertContentClass(
	'matches_current_upstream',
	ContentComparisonClassifier::classify('current', ['total' => 0]),
	'An unchanged current-version template must match upstream.'
);
assertContentClass(
	'local_modifications_detected',
	ContentComparisonClassifier::classify('current', ['total' => 2]),
	'Changes against the same official version must indicate local modifications.'
);
assertContentClass(
	'preview_against_newer_upstream',
	ContentComparisonClassifier::classify('update_available', ['total' => 7]),
	'An outdated template must be treated as a preview against newer upstream content.'
);
assertContentClass(
	'historical_baseline_required',
	ContentComparisonClassifier::classify('installed_newer', ['total' => 1]),
	'Installed-newer content must not be misclassified without a historical baseline.'
);
assertContentClass(
	'not_available',
	ContentComparisonClassifier::classify('not_applicable', ['total' => 0]),
	'Non-official templates must not receive a content identity assumption.'
);

echo "ContentComparisonClassifier tests passed.\n";
