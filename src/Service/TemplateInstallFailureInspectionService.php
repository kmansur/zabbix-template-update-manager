<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;

require_once dirname(__DIR__).'/Repository/TemplateRepository.php';

/**
 * Read-only post-failure inspection for a controlled installation attempt.
 *
 * This does not claim that no other configuration object changed. It reports
 * only whether the target template UUID is visible after an import failure.
 */
final class TemplateInstallFailureInspectionService {

	public function inspect(string $uuid): array {
		$uuid = self::normalizeUuid($uuid);
		$matches = [];

		foreach ((new TemplateRepository())->findAll() as $record) {
			if (!is_array($record)) {
				continue;
			}
			if (self::normalizeUuid((string) ($record['uuid'] ?? '')) === $uuid) {
				$matches[] = $record;
			}
		}

		if ($matches === []) {
			return [
				'state' => 'target_absent_after_failure',
				'match_count' => 0,
				'templateid' => '',
				'vendor_version' => ''
			];
		}

		if (count($matches) !== 1) {
			return [
				'state' => 'target_ambiguous_after_failure',
				'match_count' => count($matches),
				'templateid' => '',
				'vendor_version' => ''
			];
		}

		$record = $matches[0];
		return [
			'state' => 'target_present_after_failure',
			'match_count' => 1,
			'templateid' => (string) ($record['templateid'] ?? ''),
			'vendor_version' => (string) ($record['vendor_version'] ?? '')
		];
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
