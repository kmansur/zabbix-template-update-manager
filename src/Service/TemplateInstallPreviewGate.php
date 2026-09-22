<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Installation-specific import-preview gate.
 *
 * A missing-template install may create configuration but must not silently
 * update/remove existing entities or proceed with unresolved identity.
 */
final class TemplateInstallPreviewGate {

	public static function evaluate(array $summary, array $preview): array {
		$previewSummary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];

		if ((int) ($summary['updated'] ?? 0) > 0
				|| (int) ($summary['removed'] ?? 0) > 0
				|| (int) ($previewSummary['unresolved'] ?? 0) > 0) {
			return [
				'safe' => false,
				'reason' => 'install_would_modify_existing_configuration'
			];
		}

		if ((int) ($summary['added'] ?? 0) <= 0) {
			return [
				'safe' => false,
				'reason' => 'install_preview_contains_no_creations'
			];
		}

		return [
			'safe' => true,
			'reason' => null
		];
	}
}
