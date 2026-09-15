<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

final class ProjectVersion {

	private const FALLBACK = 'unknown';

	public static function current(): string {
		$path = dirname(__DIR__, 2).'/VERSION';
		if (!is_file($path) || !is_readable($path)) {
			return self::FALLBACK;
		}

		$version = trim((string) @file_get_contents($path));
		if ($version === ''
				|| preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/D', $version) !== 1) {
			return self::FALLBACK;
		}

		return $version;
	}

	public static function userAgent(): string {
		return 'Zabbix-Template-Update-Manager/'.self::current();
	}
}
