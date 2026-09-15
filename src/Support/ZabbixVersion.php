<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

final class ZabbixVersion {

	private const SUPPORTED_MAJOR_VERSIONS = [7, 8];

	public static function current(): string {
		return defined('ZABBIX_VERSION') ? (string) constant('ZABBIX_VERSION') : 'unknown';
	}

	public static function major(?string $version = null): ?int {
		if ($version === null) {
			$version = self::current();
		}

		if (!preg_match('/^(\d+)(?:\.|$)/', $version, $matches)) {
			return null;
		}

		return (int) $matches[1];
	}

	public static function isSupported(?string $version = null): bool {
		$major = self::major($version);

		return $major !== null && in_array($major, self::SUPPORTED_MAJOR_VERSIONS, true);
	}

	public static function supportedMajors(): array {
		return self::SUPPORTED_MAJOR_VERSIONS;
	}
}
