<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

require_once __DIR__.'/TemplateUpdateAnalysisService.php';

/**
 * Rebuilds authoritative analysis after configuration.import and verifies that
 * the installed template now matches the exact official candidate.
 */
final class TemplatePostUpdateValidationService {

	private $analyzer;

	public function __construct(?callable $analyzer = null) {
		$this->analyzer = $analyzer ?? static fn(string $templateId): array
			=> (new TemplateUpdateAnalysisService())->analyze($templateId);
	}

	public function validate(string $templateId, array $candidate): array {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for post-update validation.');
		}

		$expectedUuid = self::normalizeUuid((string) ($candidate['uuid'] ?? ''));
		$expectedVersion = trim((string) ($candidate['vendor_version'] ?? ''));
		if (!preg_match('/^[a-f0-9]{32}$/', $expectedUuid) || $expectedVersion === '') {
			throw new RuntimeException('The post-update candidate identity is invalid.');
		}

		$analysis = ($this->analyzer)($templateId);
		if (!is_array($analysis)) {
			throw new RuntimeException('Post-update analysis returned an invalid result.');
		}

		$reasons = [];
		if (($analysis['comparison_error'] ?? null) !== null) {
			$reasons[] = 'comparison_error';
		}

		$template = is_array($analysis['template'] ?? null) ? $analysis['template'] : [];
		if ((string) ($template['templateid'] ?? '') !== $templateId) {
			$reasons[] = 'templateid_mismatch';
		}
		if (self::normalizeUuid((string) ($template['uuid'] ?? '')) !== $expectedUuid) {
			$reasons[] = 'uuid_mismatch';
		}
		if ((string) ($template['upstream_status'] ?? '') !== 'official_match') {
			$reasons[] = 'upstream_identity_not_official';
		}
		if ((string) ($template['vendor_version'] ?? '') !== $expectedVersion) {
			$reasons[] = 'vendor_version_mismatch';
		}
		if ((string) ($template['version_status'] ?? '') !== 'current') {
			$reasons[] = 'version_not_current';
		}
		if ((string) ($analysis['content_status'] ?? '') !== 'matches_current_upstream') {
			$reasons[] = 'content_not_current_upstream';
		}

		$summary = is_array($analysis['comparison_summary'] ?? null) ? $analysis['comparison_summary'] : [];
		if ((int) ($summary['total'] ?? -1) !== 0) {
			$reasons[] = 'remaining_import_differences';
		}

		return [
			'status' => $reasons === [] ? 'validated' : 'validation_failed',
			'valid' => $reasons === [],
			'reasons' => $reasons,
			'templateid' => $templateId,
			'uuid' => $expectedUuid,
			'expected_version' => $expectedVersion,
			'installed_version' => (string) ($template['vendor_version'] ?? ''),
			'content_status' => (string) ($analysis['content_status'] ?? 'not_available'),
			'remaining_changes' => (int) ($summary['total'] ?? -1)
		];
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
