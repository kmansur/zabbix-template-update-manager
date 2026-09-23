<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;
use Throwable;

require_once __DIR__.'/TemplateConfigurationImportService.php';
require_once __DIR__.'/TemplateImportCompareService.php';
require_once __DIR__.'/TemplateInstallFailureInspectionService.php';
require_once __DIR__.'/TemplateInstallPreflightService.php';
require_once __DIR__.'/TemplatePostInstallValidationService.php';

/**
 * Single explicitly confirmed install flow for an official upstream-only
 * template. The existing TemplateConfigurationImportService remains the only
 * Zabbix configuration-write boundary.
 */
final class TemplateControlledInstallService {

	public function execute(string $uuid, string $expectedEvidenceSha256): array {
		$expectedEvidenceSha256 = strtolower(trim($expectedEvidenceSha256));
		if (!preg_match('/^[a-f0-9]{64}$/', $expectedEvidenceSha256)) {
			throw new RuntimeException('The submitted installation evidence fingerprint is invalid.');
		}

		$preflight = (new TemplateInstallPreflightService())->run($uuid);
		if (($preflight['status'] ?? null) !== 'passed' || empty($preflight['write_enabled'])) {
			return [
				'status' => 'blocked_preflight',
				'write_attempted' => false,
				'write_performed' => false,
				'write_outcome' => 'none',
				'failure_stage' => 'preflight',
				'reason' => (string) ($preflight['reason'] ?? 'install_preflight_not_passed'),
				'preflight_status' => (string) ($preflight['status'] ?? 'unknown'),
				'candidate' => $preflight['candidate'] ?? null,
				'validation' => null,
				'failure_inspection' => null,
				'error_detail' => null
			];
		}

		$freshEvidence = strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')));
		if ($freshEvidence === '' || !hash_equals($freshEvidence, $expectedEvidenceSha256)) {
			return [
				'status' => 'blocked_evidence_changed',
				'write_attempted' => false,
				'write_performed' => false,
				'write_outcome' => 'none',
				'failure_stage' => 'evidence',
				'reason' => 'installation_evidence_changed',
				'preflight_status' => 'passed',
				'candidate' => $preflight['candidate'] ?? null,
				'validation' => null,
				'failure_inspection' => null,
				'error_detail' => null
			];
		}

		$source = (string) ($preflight['import_source'] ?? '');
		$format = (string) ($preflight['import_format'] ?? 'json');
		$ruleProfile = (string) ($preflight['import_rule_profile'] ?? '');
		if ($source === '') {
			throw new RuntimeException('The fresh installation preflight did not provide an import source.');
		}
		if ($ruleProfile !== TemplateImportCompareService::PROFILE_INSTALL) {
			throw new RuntimeException('The fresh installation preflight did not preserve the reviewed create-only rule profile.');
		}

		$candidate = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];

		try {
			(new TemplateConfigurationImportService())->import(
				$source,
				$format,
				TemplateImportCompareService::PROFILE_INSTALL
			);
		}
		catch (Throwable $exception) {
			$inspection = (new TemplateInstallFailureInspectionService())->inspect($uuid);
			return [
				'status' => 'import_failed',
				'write_attempted' => true,
				'write_performed' => false,
				'write_outcome' => 'uncertain',
				'failure_stage' => 'import',
				'reason' => 'configuration_import_failed',
				'preflight_status' => 'passed',
				'preflight_evidence_sha256' => $freshEvidence,
				'candidate' => $candidate,
				'validation' => null,
				'failure_inspection' => $inspection,
				'error_detail' => self::sanitizeError($exception->getMessage())
			];
		}

		$validation = (new TemplatePostInstallValidationService())->validate($uuid, $candidate);

		return [
			'status' => !empty($validation['valid']) ? 'installed' : 'validation_failed',
			'write_attempted' => true,
			'write_performed' => true,
			'write_outcome' => 'confirmed',
			'failure_stage' => !empty($validation['valid']) ? null : 'validation',
			'reason' => !empty($validation['valid']) ? null : 'post_install_validation_failed',
			'preflight_status' => 'passed',
			'preflight_evidence_sha256' => $freshEvidence,
			'candidate' => $candidate,
			'validation' => $validation,
			'failure_inspection' => null,
			'error_detail' => null
		];
	}

	private static function sanitizeError(string $message): string {
		$message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? '';
		$message = trim($message);
		return strlen($message) > 600 ? substr($message, 0, 600).'…' : $message;
	}
}
