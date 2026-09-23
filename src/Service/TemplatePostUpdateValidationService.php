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

	private const CREATE_ONLY_INSTALL_IGNORED_ENTITIES = ['host_groups', 'template_groups'];

	public function validate(string $templateId, array $candidate): array {
		return $this->validateWithIgnoredEntities($templateId, $candidate, []);
	}

	/**
	 * Validates a successful create-only installation without requiring ZTUM
	 * to mutate pre-existing shared host/template groups. All template-owned
	 * entities remain authoritative and any non-group difference fails closed.
	 */
	public function validateCreateOnlyInstall(string $templateId, array $candidate): array {
		return $this->validateWithIgnoredEntities(
			$templateId,
			$candidate,
			self::CREATE_ONLY_INSTALL_IGNORED_ENTITIES
		);
	}

	private function validateWithIgnoredEntities(string $templateId, array $candidate, array $ignoredEntities): array {
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
		$summary = is_array($analysis['comparison_summary'] ?? null) ? $analysis['comparison_summary'] : [];
		$comparison = self::effectiveComparison($summary, $ignoredEntities);
		$rawContentStatus = (string) ($analysis['content_status'] ?? 'not_available');
		$contentStatus = $rawContentStatus;

		if ($comparison['remaining_changes'] === 0
				&& $comparison['ignored_changes'] > 0
				&& $rawContentStatus === 'local_modifications_detected') {
			$contentStatus = 'matches_current_upstream';
		}

		if ($contentStatus !== 'matches_current_upstream') {
			$reasons[] = 'content_not_current_upstream';
		}
		if ($comparison['remaining_changes'] !== 0) {
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
			'content_status' => $contentStatus,
			'raw_content_status' => $rawContentStatus,
			'remaining_changes' => $comparison['remaining_changes'],
			'raw_remaining_changes' => $comparison['raw_remaining_changes'],
			'ignored_shared_changes' => $comparison['ignored_changes'],
			'ignored_shared_entities' => $comparison['ignored_entities']
		];
	}

	private static function effectiveComparison(array $summary, array $ignoredEntities): array {
		$rawRemainingChanges = array_key_exists('total', $summary)
			? (int) $summary['total']
			: -1;

		if ($rawRemainingChanges < 0 || $ignoredEntities === []) {
			return [
				'raw_remaining_changes' => $rawRemainingChanges,
				'remaining_changes' => $rawRemainingChanges,
				'ignored_changes' => 0,
				'ignored_entities' => []
			];
		}

		$byEntity = is_array($summary['by_entity'] ?? null) ? $summary['by_entity'] : [];
		$ignoredChanges = 0;
		$ignoredWithChanges = [];

		foreach (array_values(array_unique(array_map('strval', $ignoredEntities))) as $entity) {
			$counts = is_array($byEntity[$entity] ?? null) ? $byEntity[$entity] : [];
			$entityChanges = max(0, (int) ($counts['added'] ?? 0))
				+ max(0, (int) ($counts['updated'] ?? 0))
				+ max(0, (int) ($counts['removed'] ?? 0));

			if ($entityChanges > 0) {
				$ignoredChanges += $entityChanges;
				$ignoredWithChanges[] = $entity;
			}
		}

		$ignoredChanges = min($rawRemainingChanges, $ignoredChanges);
		sort($ignoredWithChanges, SORT_STRING);

		return [
			'raw_remaining_changes' => $rawRemainingChanges,
			'remaining_changes' => $rawRemainingChanges - $ignoredChanges,
			'ignored_changes' => $ignoredChanges,
			'ignored_entities' => $ignoredWithChanges
		];
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
