<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

final class ContentComparisonClassifier {

	public static function classify(string $versionStatus, array $comparisonSummary): string {
		$totalChanges = max(0, (int) ($comparisonSummary['total'] ?? 0));

		if ($versionStatus === 'current') {
			return $totalChanges === 0
				? 'matches_current_upstream'
				: 'local_modifications_detected';
		}

		if ($versionStatus === 'update_available') {
			return 'preview_against_newer_upstream';
		}

		if (in_array($versionStatus, [
			'installed_newer',
			'installed_version_missing',
			'upstream_version_missing',
			'version_uncomparable'
		], true)) {
			return 'historical_baseline_required';
		}

		return 'not_available';
	}
}
