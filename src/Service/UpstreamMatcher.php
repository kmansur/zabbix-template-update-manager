<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

final class UpstreamMatcher {

	public static function attach(array $templates, ?array $index): array {
		$summary = self::emptySummary();
		$records = $index['templates'] ?? null;
		$available = is_array($records);

		foreach ($templates as &$template) {
			$status = 'repository_unavailable';
			$upstream = null;

			if ($available) {
				$uuid = self::normalizeUuid((string) ($template['uuid'] ?? ''));

				if ($uuid === '') {
					$status = 'no_uuid';
				}
				elseif (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
					$status = 'invalid_uuid';
				}
				elseif (array_key_exists($uuid, $records)) {
					$status = 'official_match';
					$upstream = $records[$uuid];
				}
				else {
					$status = 'not_found';
				}
			}

			$template['upstream_status'] = $status;
			$template['upstream'] = $upstream;
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
			'official_match' => 0,
			'not_found' => 0,
			'no_uuid' => 0,
			'invalid_uuid' => 0,
			'repository_unavailable' => 0
		];
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
