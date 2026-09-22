<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

require_once __DIR__.'/TemplateConfigurationImportService.php';
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
				'write_performed' => false,
				'reason' => (string) ($preflight['reason'] ?? 'install_preflight_not_passed'),
				'preflight_status' => (string) ($preflight['status'] ?? 'unknown'),
				'candidate' => $preflight['candidate'] ?? null,
				'validation' => null
			];
		}

		$freshEvidence = strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')));
		if ($freshEvidence === '' || !hash_equals($freshEvidence, $expectedEvidenceSha256)) {
			return [
				'status' => 'blocked_evidence_changed',
				'write_performed' => false,
				'reason' => 'installation_evidence_changed',
				'preflight_status' => 'passed',
				'candidate' => $preflight['candidate'] ?? null,
				'validation' => null
			];
		}

		$source = (string) ($preflight['import_source'] ?? '');
		$format = (string) ($preflight['import_format'] ?? 'json');
		if ($source === '') {
			throw new RuntimeException('The fresh installation preflight did not provide an import source.');
		}

		(new TemplateConfigurationImportService())->import($source, $format);

		$candidate = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];
		$validation = (new TemplatePostInstallValidationService())->validate($uuid, $candidate);

		return [
			'status' => !empty($validation['valid']) ? 'installed' : 'validation_failed',
			'write_performed' => true,
			'reason' => !empty($validation['valid']) ? null : 'post_install_validation_failed',
			'preflight_status' => 'passed',
			'preflight_evidence_sha256' => $freshEvidence,
			'candidate' => $candidate,
			'validation' => $validation
		];
	}
}
