<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

final class TemplateVersionComparator {

	public static function attach(array $templates): array {
		$summary = self::emptySummary();

		foreach ($templates as &$template) {
			$status = 'not_applicable';
			$upstreamVersion = '';

			if (($template['installation_status'] ?? null) === 'not_installed'
					&& is_array($template['upstream'] ?? null)) {
				$upstreamVersion = self::stringValue($template['upstream']['vendor_version'] ?? '');
				$status = 'not_installed';
			}
			elseif (($template['upstream_status'] ?? null) === 'official_match'
					&& is_array($template['upstream'] ?? null)) {
				$installedVersion = self::stringValue($template['vendor_version'] ?? '');
				$upstreamVersion = self::stringValue($template['upstream']['vendor_version'] ?? '');

				if ($installedVersion === '') {
					$status = 'installed_version_missing';
				}
				elseif ($upstreamVersion === '') {
					$status = 'upstream_version_missing';
				}
				else {
					$installed = self::parse($installedVersion);
					$upstream = self::parse($upstreamVersion);

					if ($installed === null || $upstream === null) {
						$status = 'version_uncomparable';
					}
					else {
						$comparison = self::compare($installed, $upstream);
						$status = $comparison === 0
							? 'current'
							: ($comparison < 0 ? 'update_available' : 'installed_newer');
					}
				}
			}

			$template['upstream_vendor_version'] = $upstreamVersion;
			$template['version_status'] = $status;
			$summary[$status]++;
		}
		unset($template);

		return [
			'templates' => $templates,
			'summary' => $summary
		];
	}

	public static function emptySummary(): array {
		return [
			'current' => 0,
			'update_available' => 0,
			'installed_newer' => 0,
			'installed_version_missing' => 0,
			'upstream_version_missing' => 0,
			'version_uncomparable' => 0,
			'not_applicable' => 0,
			'not_installed' => 0
		];
	}

	private static function stringValue($value): string {
		return is_string($value) || is_numeric($value)
			? trim((string) $value)
			: '';
	}

	private static function parse(string $version): ?array {
		if (!preg_match('/^(\d+)\.(\d+)-(\d+)$/', trim($version), $matches)) {
			return null;
		}

		return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
	}

	private static function compare(array $left, array $right): int {
		for ($index = 0; $index < 3; $index++) {
			if ($left[$index] < $right[$index]) {
				return -1;
			}
			if ($left[$index] > $right[$index]) {
				return 1;
			}
		}

		return 0;
	}
}
