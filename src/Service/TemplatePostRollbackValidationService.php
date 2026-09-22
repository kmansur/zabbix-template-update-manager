<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use RuntimeException;

require_once dirname(__DIR__).'/Repository/TemplateRepository.php';
require_once __DIR__.'/ImportCompareSummary.php';
require_once __DIR__.'/TemplateImportCompareService.php';
require_once __DIR__.'/TemplateInventoryService.php';

/**
 * Verifies that the installed template matches the explicitly selected rollback
 * artifact after configuration.import returns success.
 */
final class TemplatePostRollbackValidationService {

	private $templateLoader;
	private $comparer;

	public function __construct(?callable $templateLoader = null, ?callable $comparer = null) {
		$this->templateLoader = $templateLoader ?? static function (string $templateId): array {
			$record = (new TemplateRepository())->findById($templateId);
			if ($record === null) {
				throw new RuntimeException('The rolled-back template is no longer visible.');
			}

			$inventory = TemplateInventoryService::fromRecords([$record]);
			$template = $inventory['templates'][0] ?? null;
			if (!is_array($template)) {
				throw new RuntimeException('Unable to normalize the rolled-back template.');
			}
			return $template;
		};
		$this->comparer = $comparer ?? static fn(string $source, string $format): array
			=> (new TemplateImportCompareService())->compare($source, $format);
	}

	public function validate(string $templateId, array $artifact): array {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for post-rollback validation.');
		}

		$expectedUuid = self::normalizeUuid((string) ($artifact['uuid'] ?? ''));
		$expectedVersion = (string) ($artifact['vendor_version'] ?? '');
		$format = strtolower(trim((string) ($artifact['format'] ?? '')));
		$source = $artifact['source'] ?? null;
		if (!preg_match('/^[a-f0-9]{32}$/', $expectedUuid) || !is_string($source) || $source === '') {
			throw new RuntimeException('The rollback target identity/source is invalid.');
		}
		if (!in_array($format, ['json', 'yaml'], true)) {
			throw new RuntimeException('The rollback target format is unsupported.');
		}

		$template = ($this->templateLoader)($templateId);
		if (!is_array($template)) {
			throw new RuntimeException('Post-rollback template loader returned invalid data.');
		}

		$diff = ($this->comparer)($source, $format);
		if (!is_array($diff)) {
			throw new RuntimeException('Post-rollback import comparison returned invalid data.');
		}
		$summary = ImportCompareSummary::summarize($diff);

		$reasons = [];
		if ((string) ($template['templateid'] ?? '') !== $templateId) {
			$reasons[] = 'templateid_mismatch';
		}
		if (self::normalizeUuid((string) ($template['uuid'] ?? '')) !== $expectedUuid) {
			$reasons[] = 'uuid_mismatch';
		}
		if ((string) ($template['vendor_version'] ?? '') !== $expectedVersion) {
			$reasons[] = 'vendor_version_mismatch';
		}
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
			'remaining_changes' => (int) ($summary['total'] ?? -1)
		];
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
