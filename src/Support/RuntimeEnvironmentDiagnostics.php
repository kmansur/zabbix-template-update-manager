<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

/**
 * Read-only checks for the default private runtime storage.
 * Do not create directories, change permissions, or execute shell commands.
 */
final class RuntimeEnvironmentDiagnostics {

	public static function check(): array {
		$base = '/var/lib/zabbix-template-update-manager';
		$paths = [$base, $base.'/backups', $base.'/offline', $base.'/locks', $base.'/batches'];
		$uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
		$user = $uid !== null && function_exists('posix_getpwuid') ? posix_getpwuid($uid) : null;
		$expectedOwner = is_array($user) ? (string) ($user['name'] ?? '') : '';
		$problems = [];

		foreach ($paths as $path) {
			clearstatcache(true, $path);
			if (is_link($path) || !is_dir($path)) {
				$problems[] = $path.': missing or unsafe directory';
				continue;
			}

			$mode = fileperms($path);
			if ($mode === false || ($mode & 0777) !== 0700) {
				$problems[] = $path.': permissions must be 0700';
			}
			if ($uid !== null && fileowner($path) !== $uid) {
				$problems[] = $path.': must be owned by the PHP runtime account';
			}
			if (!is_readable($path) || !is_writable($path) || !is_executable($path)) {
				$problems[] = $path.': PHP runtime cannot read, write or traverse';
			}
		}

		return ['ok' => $problems === [], 'problems' => $problems, 'runtime_user' => $expectedOwner];
	}
}
