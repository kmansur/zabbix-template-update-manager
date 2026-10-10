<?php
$root = dirname(__DIR__, 2);
$view = file_get_contents($root.'/views/ztum.template.batch.prepare.php');
$js = file_get_contents($root.'/assets/js/ztum-update-batch.js');
foreach (['new CHtmlPage()', 'new CCheckBox(', 'FrontendUi::message(', 'ztum-batch-loss-warning-text', 'local_loss_warning', 'identified local-customization losses'] as $needle) {
    if (!str_contains($view, $needle)) {
        throw new RuntimeException('Batch native UI missing: '.$needle);
    }
}
foreach (['reviewEvidence.get(entry.templateId)', 'requiresLocalOverwriteAck', 'labels.local_loss_warning', 'confirmedSelectionFingerprint', 'confirm.checked = false'] as $needle) {
    if (!str_contains($js, $needle)) {
        throw new RuntimeException('Batch selection protection missing: '.$needle);
    }
}
foreach (['Local customization risk', 'ztum-local-risk-', 'local_risk_known', 'local_risk_unknown', 'local_risk_not_reported'] as $needle) {
    if (!str_contains($view, $needle)) {
        throw new RuntimeException('Batch per-row risk labeling missing: '.$needle);
    }
}
foreach (['hasKnownLocalRisk', 'ztum-local-risk-', 'labels.local_risk_unknown', 'labels.local_risk_not_reported'] as $needle) {
    if (!str_contains($js, $needle)) {
        throw new RuntimeException('Batch per-row risk behavior missing: '.$needle);
    }
}
echo "Native batch loss-warning contract tests passed.\n";
