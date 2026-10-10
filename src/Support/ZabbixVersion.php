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

	public static function line(?string $version = null): ?string {
		if ($version === null) {
			$version = self::current();
		}

		if (!preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
			return null;
		}

		return (int) $matches[1].'.'.(int) $matches[2];
	}

	/** Beta/RC/alpha source trees must not inherit stable initial-release baselines. */
	public static function isPrerelease(?string $version = null): bool {
		$version = $version ?? self::current();
		return preg_match('/^\\d+\\.\\d+\\.\\d+(?:[-._~]?(?:alpha|beta|rc|pre|dev)\\d*)/i', trim($version)) === 1;
	}

	public static function isSupported(?string $version = null): bool {
		$major = self::major($version);

		return $major !== null && in_array($major, self::SUPPORTED_MAJOR_VERSIONS, true);
	}

	public static function supportedMajors(): array {
		return self::SUPPORTED_MAJOR_VERSIONS;
	}
}
