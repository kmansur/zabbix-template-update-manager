<?php
use Modules\ZabbixTemplateUpdateManager\Service\HistoricalDashboardCandidateCorrelation as Correlator;
require_once dirname(__DIR__, 2).'/src/Service/HistoricalDashboardCandidateCorrelation.php';

function checkCorrelator(mixed $want, mixed $got, string $label): void {
    if ($want !== $got) {
        throw new RuntimeException($label.': expected '.var_export($want, true).' got '.var_export($got, true));
    }
}
$uuid = str_repeat('a', 32);
$source = static fn(array $dashboards): string => json_encode([
    'zabbix_export' => ['templates' => [['dashboards' => $dashboards]]]
], JSON_THROW_ON_ERROR);
$local = [['uuid' => $uuid, 'auto_start' => '0', 'display_period' => '3600']];
$ok = Correlator::compare($source([['uuid' => $uuid, 'auto_start' => 'NO', 'display_period' => '3600']]), $local);
checkCorrelator(true, $ok['complete'], 'Matching UUID and known values');
checkCorrelator(1, $ok['matched'], 'Matching count');
$bad = Correlator::compare($source([['uuid' => $uuid, 'auto_start' => 'YES', 'display_period' => '3600']]), $local);
checkCorrelator(false, $bad['complete'], 'Changed auto_start must fail');
$missing = Correlator::compare($source([['uuid' => $uuid, 'display_period' => '3600']]), $local);
checkCorrelator(false, $missing['complete'], 'Missing auto_start cannot be assumed default');
$other = Correlator::compare($source([['uuid' => str_repeat('b', 32), 'auto_start' => 'NO', 'display_period' => '3600']]), $local);
checkCorrelator(false, $other['complete'], 'Changed dashboard UUID must fail');
$extra = Correlator::compare($source([['uuid' => $uuid, 'auto_start' => 'NO', 'display_period' => '3600']]), array_merge($local, $local));
checkCorrelator(false, $extra['complete'], 'Duplicate local UUID must fail');
checkCorrelator(false, str_contains(json_encode($ok), $uuid), 'No UUID disclosed');
echo "HistoricalDashboardCandidateCorrelation tests passed.\n";
