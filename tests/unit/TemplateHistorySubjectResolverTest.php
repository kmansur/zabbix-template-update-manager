<?php

use Modules\ZabbixTemplateUpdateManager\Service\TemplateHistorySubjectResolver;
require_once dirname(__DIR__, 2).'/src/Service/TemplateHistorySubjectResolver.php';

$uuid = 'a525b86c57da4d34853ff249b8b1f099';
$entries = [
    ['subject'=>'template-10773', 'operation'=>'update'],
    ['subject'=>'uuid-'.$uuid, 'operation'=>'install'],
    ['subject'=>'template-99999', 'operation'=>'rollback'],
    ['subject'=>'uuid-00000000000000000000000000000000', 'operation'=>'install'],
    ['subject'=>'other-event', 'operation'=>'policy']
];
$copy = $entries;
$result = TemplateHistorySubjectResolver::resolve($entries, [
    ['templateid'=>'10773','uuid'=>$uuid,'name'=>'Domain RDAP by HTTP'],
    ['templateid'=>'10774','uuid'=>str_repeat('f',32),'name'=>'GitHub repository by HTTP']
]);
foreach ([0,1] as $i) {
    if ($result[$i]['display_subject'] !== 'Domain RDAP by HTTP'
        || $result[$i]['subject'] !== $copy[$i]['subject']) {
        throw new RuntimeException('Template ID/UUID display lookup or audit immutability failed.');
    }
}
foreach ([2,3,4] as $i) {
    if ($result[$i]['display_subject'] !== $copy[$i]['subject']) {
        throw new RuntimeException('Unknown or deleted template must keep original subject.');
    }
}
if ($entries !== $copy) {
    throw new RuntimeException('Historical entries must not be mutated by display resolver.');
}
echo "Historical template-name lookup tests passed.\n";
