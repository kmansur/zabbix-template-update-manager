<?php
$root = dirname(__DIR__, 2);
$compare = file_get_contents($root.'/views/ztum.template.compare.php');
$preflight = file_get_contents($root.'/views/ztum.template.preflight.php');
foreach ([
    'FrontendUi::message(',
    'The historical baseline is not verified.',
    'the official update will overwrite or remove the local customizations',
    'Three-way analysis'
] as $needle) {
    if (!str_contains($compare, $needle)) {
        throw new RuntimeException('Missing native comparison warning: '.$needle);
    }
}
foreach ([
    'FrontendUi::message(',
    'local_customization_overwrite',
    'rollback backup does not prevent the overwrite',
    "new CCheckBox('confirm', '1')",
    'explicitly accept the official update'
] as $needle) {
    if (!str_contains($preflight, $needle)) {
        throw new RuntimeException('Missing native confirmation requirement: '.$needle);
    }
}
echo "Native customization review contract tests passed.\n";
