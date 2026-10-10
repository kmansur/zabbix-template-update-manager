<?php
namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

/** Manual, read-only GitHub release check. No code download or execution. */
final class ModuleReleaseCheckService {
    public const URL = 'https://api.github.com/repos/kmansur/zabbix-template-update-manager/releases?per_page=100';
    public const RELEASES = 'https://github.com/kmansur/zabbix-template-update-manager/releases';

    public static function compare(string $installed, array $releases): array {
        if (!self::validVersion($installed)) {
            throw new RuntimeException('Installed module version is invalid.');
        }
        $winner = null;
        foreach ($releases as $release) {
            if (!is_array($release) || !empty($release['draft'])) {
                continue;
            }
            $tag = (string) ($release['tag_name'] ?? '');
            $version = ltrim($tag, 'v');
            if (!self::validVersion($version)) {
                continue;
            }
            $url = (string) ($release['html_url'] ?? '');
            if (!preg_match('~^https://github\\.com/kmansur/zabbix-template-update-manager/releases/tag/[A-Za-z0-9._-]+$~D', $url)) {
                continue;
            }
            if ($winner === null || version_compare($version, $winner['version'], '>')) {
                $winner = ['version' => $version, 'url' => $url, 'prerelease' => !empty($release['prerelease'])];
            }
        }
        return [
            'installed' => $installed,
            'latest' => $winner['version'] ?? null,
            'url' => $winner['url'] ?? null,
            'prerelease' => $winner['prerelease'] ?? null,
            'update_available' => $winner !== null && version_compare($winner['version'], $installed, '>')
        ];
    }

    private static function validVersion(string $version): bool {
        return preg_match('/^(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)(?:-(?:0|[1-9A-Za-z-][0-9A-Za-z-]*)(?:\\.[0-9A-Za-z-]+)*)?$/D', $version) === 1;
    }

    public function check(string $installed): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is unavailable.');
        }
        $ch = curl_init(self::URL);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTPS transport.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'],
            CURLOPT_USERAGENT => 'ZTUM-release-check/1.0'
        ]);
        try {
            $payload = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (!is_string($payload) || $status !== 200 || strlen($payload) > 524288) {
                throw new RuntimeException('GitHub release API unavailable or response too large.');
            }
        }
        finally {
            curl_close($ch);
        }
        $releases = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($releases) || !array_is_list($releases)) {
            throw new RuntimeException('Invalid GitHub release response.');
        }
        return self::compare($installed, $releases);
    }
}
