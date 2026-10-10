<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use RuntimeException;

/**
 * Independent, read-only verification of effective template dashboard properties.
 * The native importcompare may omit a field in its "before" snapshot; such
 * omission is not evidence of an effective default or of baseline identity.
 */
final class TemplateDashboardEffectiveStateDiagnostic {
	public static function inspect(string $templateId, ?callable $loader = null): array {
		if (!ctype_digit($templateId) || (int) $templateId < 1) {
			throw new RuntimeException('A numeric template ID is required.');
		}
		$loader ??= static fn(string $id): array => API::TemplateDashboard()->get([
			'templateids' => [$id],
			'output' => ['dashboardid', 'templateid', 'uuid', 'name', 'auto_start', 'display_period']
		]);
		$dashboards = $loader($templateId);
		if (!is_array($dashboards)) {
			throw new RuntimeException('Invalid template dashboard API response.');
		}
		$result = ['count' => 0, 'auto_start_no' => 0, 'auto_start_yes' => 0,
			'auto_start_unknown' => 0, 'display_period_known' => 0, 'identity_complete' => true];
		foreach ($dashboards as $dashboard) {
			if (!is_array($dashboard) || (string) ($dashboard['templateid'] ?? '') !== $templateId) {
				throw new RuntimeException('Template dashboard API returned an invalid ownership record.');
			}
			$result['count']++;
			$uuid = (string) ($dashboard['uuid'] ?? '');
			if (!preg_match('/^[a-f0-9]{32}$/i', $uuid)) {
				$result['identity_complete'] = false;
			}
			switch ($dashboard['auto_start'] ?? null) {
				case 0:
				case '0': $result['auto_start_no']++; break;
				case 1:
				case '1': $result['auto_start_yes']++; break;
				default: $result['auto_start_unknown']++; break;
			}
			if (in_array((string) ($dashboard['display_period'] ?? ''), ['10','30','60','120','600','1800','3600'], true)) {
				$result['display_period_known']++;
			}
		}
		return $result;
	}
}
