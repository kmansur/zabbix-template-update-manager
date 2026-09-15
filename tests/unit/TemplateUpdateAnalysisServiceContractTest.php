<?php

function assertUpdateAnalysisContract($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(STDERR, $message."\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents($root.'/actions/TemplateCompare.php');
$service = (string) file_get_contents($root.'/src/Service/TemplateUpdateAnalysisService.php');

assertUpdateAnalysisContract(
	true,
	str_contains($controller, 'TemplateUpdateAnalysisService'),
	'Template comparison controller must delegate the analysis pipeline to TemplateUpdateAnalysisService.'
);
assertUpdateAnalysisContract(
	true,
	str_contains($controller, "->analyze(\n\t\t\t(string) \$this->getInput('templateid')"),
	'Template comparison controller must pass only the selected template ID to the analysis service.'
);
assertUpdateAnalysisContract(
	false,
	str_contains($controller, 'UpstreamTemplateSourceRepository'),
	'Template comparison controller must not own upstream source orchestration after the refactor.'
);
assertUpdateAnalysisContract(
	false,
	str_contains($controller, 'HistoricalTemplateBaselineService'),
	'Template comparison controller must not own historical baseline orchestration after the refactor.'
);
assertUpdateAnalysisContract(
	false,
	str_contains($controller, 'UpdateRiskAnalyzer'),
	'Template comparison controller must not own update-risk orchestration after the refactor.'
);
assertUpdateAnalysisContract(
	false,
	str_contains($controller, 'TemplateBackupVerificationService'),
	'Template comparison controller must not own rollback-verification orchestration after the refactor.'
);

foreach ([
	'TemplateRepository',
	'UpstreamIndexRepository',
	'UpstreamTemplateSourceRepository',
	'HistoricalTemplateBaselineService',
	'TemplateImportCompareService',
	'ThreeWayChangeAnalyzer',
	'UpdatePreviewAnalyzer',
	'UpdateRiskAnalyzer',
	'UpdateReadinessEvaluator',
	'TemplateBackupVerificationService'
] as $requiredStage) {
	assertUpdateAnalysisContract(
		true,
		str_contains($service, $requiredStage),
		'TemplateUpdateAnalysisService must retain the '.$requiredStage.' stage.'
	);
}

assertUpdateAnalysisContract(
	true,
	str_contains($service, "throw new RuntimeException('A valid numeric template ID is required for update analysis.')"),
	'Reusable update analysis must validate its template ID independently of the frontend controller.'
);
assertUpdateAnalysisContract(
	false,
	str_contains($service, 'API::Configuration()->import('),
	'Reusable update analysis must never perform a Zabbix configuration import.'
);
assertUpdateAnalysisContract(
	false,
	str_contains($service, 'API::Template()->update('),
	'Reusable update analysis must never update templates directly.'
);

$controllerLines = substr_count($controller, "\n") + 1;
assertUpdateAnalysisContract(
	true,
	$controllerLines <= 50,
	'Template comparison controller should remain thin after extracting update analysis.'
);

echo "TemplateUpdateAnalysisService contract tests passed.\n";
