<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use RuntimeException;

require_once dirname(__DIR__).'/Repository/TemplateRepository.php';
require_once __DIR__.'/TemplatePostUpdateValidationService.php';

/**
 * Resolves the newly created template by UUID and then reuses the authoritative
 * post-update validator to prove current-upstream equality.
 */
final class TemplatePostInstallValidationService {

	public function validate(string $uuid, array $candidate): array {
		$uuid = self::normalizeUuid($uuid);
		if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			throw new RuntimeException('The post-install UUID is invalid.');
		}

		$matches = [];
		foreach ((new TemplateRepository())->findAll() as $record) {
			if (!is_array($record)) {
				continue;
			}
			if (self::normalizeUuid((string) ($record['uuid'] ?? '')) === $uuid) {
				$matches[] = $record;
			}
		}

		if (count($matches) !== 1) {
			return [
				'status' => 'validation_failed',
				'valid' => false,
				'reasons' => [count($matches) === 0 ? 'installed_template_not_found' : 'duplicate_uuid_after_install'],
				'templateid' => '',
				'uuid' => $uuid,
				'expected_version' => (string) ($candidate['vendor_version'] ?? ''),
				'installed_version' => '',
				'content_status' => 'not_available',
				'remaining_changes' => -1
			];
		}

		$record = $matches[0];
		$templateId = trim((string) ($record['templateid'] ?? ''));
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('The installed template has no valid template ID.');
		}

		$reasons = [];
		$expectedTechnicalName = trim((string) ($candidate['technical_name'] ?? ''));
		if ($expectedTechnicalName !== '' && trim((string) ($record['host'] ?? '')) !== $expectedTechnicalName) {
			$reasons[] = 'technical_name_mismatch';
		}

		$validation = (new TemplatePostUpdateValidationService())->validateCreateOnlyInstall($templateId, $candidate);
		if ($reasons !== []) {
			$validation['valid'] = false;
			$validation['status'] = 'validation_failed';
			$validation['reasons'] = array_values(array_unique(array_merge(
				(array) ($validation['reasons'] ?? []),
				$reasons
			)));
		}

		return $validation;
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
