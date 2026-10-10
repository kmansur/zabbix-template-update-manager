<?php
namespace Modules\ZabbixTemplateUpdateManager\Service;

/** Read-only correlation; results must not authorize baseline selection. */
final class HistoricalDashboardCandidateCorrelation {
    public static function compare(string $source, array $local): array {
        $result = ['matched' => 0, 'candidate' => 0, 'local' => count($local), 'unverified' => 0];
        $data = json_decode($source, true);
        $dashboards = $data['zabbix_export']['templates'][0]['dashboards'] ?? null;
        if (!is_array($dashboards)) {
            $result['unverified']++;
            return $result;
        }
        $index = [];
        foreach ($local as $row) {
            $uuid = strtolower((string) ($row['uuid'] ?? ''));
            if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || isset($index[$uuid])) {
                $result['unverified']++;
                continue;
            }
            $index[$uuid] = $row;
        }
        foreach ($dashboards as $item) {
            $result['candidate']++;
            $uuid = strtolower((string) ($item['uuid'] ?? ''));
            if (!preg_match('/^[a-f0-9]{32}$/', $uuid) || !isset($index[$uuid])) {
                $result['unverified']++;
                continue;
            }
            $expected = $item['auto_start'] ?? null;
            $actual = $index[$uuid]['auto_start'] ?? null;
            $expected = $expected === 'NO' ? '0' : ($expected === 'YES' ? '1' : (string) $expected);
            if (!in_array($expected, ['0', '1'], true)
                || !in_array((string) $actual, ['0', '1'], true)
                || $expected !== (string) $actual
                || !isset($item['display_period'], $index[$uuid]['display_period'])
                || (string) $item['display_period'] !== (string) $index[$uuid]['display_period']) {
                $result['unverified']++;
                continue;
            }
            $result['matched']++;
        }
        $result['complete'] = $result['unverified'] === 0
            && $result['candidate'] > 0
            && $result['candidate'] === $result['matched']
            && $result['candidate'] === $result['local'];
        return $result;
    }
}
