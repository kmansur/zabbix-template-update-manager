<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use RuntimeException;

/**
 * Verifies that the newest persistent rollback artifact is intact and still
 * represents the exact currently installed template export.
 *
 * This service performs read-only Zabbix export plus local artifact reads. It
 * does not modify Zabbix configuration.
 */
final class TemplateBackupVerificationService {

	private TemplateExportService $exportService;
	private TemplateBackupRepository $backupRepository;

	public function __construct(
		?TemplateExportService $exportService = null,
		?TemplateBackupRepository $backupRepository = null
	) {
		$this->exportService = $exportService ?? new TemplateExportService();
		$this->backupRepository = $backupRepository ?? new TemplateBackupRepository();
	}

	public function verifyCurrent(array $template): array {
		$templateId = trim((string) ($template['templateid'] ?? ''));
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for backup verification.');
		}

		$inspection = $this->backupRepository->inspectForTemplate($templateId, 10);
		$base = [
			'status' => 'no_backup',
			'current_match' => false,
			'templateid' => $templateId,
			'latest' => null,
			'scanned' => (int) ($inspection['scanned'] ?? 0),
			'valid' => (int) ($inspection['valid'] ?? 0),
			'invalid' => (int) ($inspection['invalid'] ?? 0),
			'truncated' => !empty($inspection['truncated']),
			'current_export' => null,
			'reason' => null
		];

		if (($inspection['status'] ?? null) === 'repository_unavailable') {
			$base['status'] = 'repository_unavailable';
			$base['reason'] = 'backup_repository_unavailable';
			return $base;
		}

		$artifacts = is_array($inspection['artifacts'] ?? null) ? $inspection['artifacts'] : [];
		if ($artifacts === []) {
			return $base;
		}

		$latest = $artifacts[0];
		$base['latest'] = $latest;
		if (($latest['status'] ?? null) !== 'valid') {
			$base['status'] = 'latest_invalid';
			$base['reason'] = (string) ($latest['reason'] ?? 'backup_artifact_invalid');
			return $base;
		}

		if (!$this->identityMatches($template, $latest)) {
			$base['status'] = 'current_mismatch';
			$base['reason'] = 'backup_identity_mismatch';
			return $base;
		}

		$currentExport = $this->exportService->export($templateId);
		$base['current_export'] = [
			'format' => (string) ($currentExport['format'] ?? ''),
			'bytes' => (int) ($currentExport['bytes'] ?? -1),
			'sha256' => (string) ($currentExport['sha256'] ?? '')
		];

		if (($latest['format'] ?? null) !== 'yaml'
				|| ($currentExport['format'] ?? null) !== 'yaml'
				|| (int) ($latest['bytes'] ?? -1) !== (int) ($currentExport['bytes'] ?? -2)
				|| !is_string($latest['sha256'] ?? null)
				|| !is_string($currentExport['sha256'] ?? null)
				|| !hash_equals((string) $latest['sha256'], (string) $currentExport['sha256'])) {
			$base['status'] = 'current_mismatch';
			$base['reason'] = 'current_export_fingerprint_mismatch';
			return $base;
		}

		$base['status'] = 'current_match';
		$base['current_match'] = true;
		return $base;
	}

	private function identityMatches(array $template, array $artifact): bool {
		if ((string) ($artifact['templateid'] ?? '') !== (string) ($template['templateid'] ?? '')) {
			return false;
		}

		$currentUuid = strtolower(str_replace('-', '', trim((string) ($template['uuid'] ?? ''))));
		$artifactUuid = strtolower(trim((string) ($artifact['uuid'] ?? '')));
		if ($currentUuid !== $artifactUuid) {
			return false;
		}

		if (trim((string) ($template['technical_name'] ?? ''))
				!== (string) ($artifact['technical_name'] ?? '')) {
			return false;
		}

		return trim((string) ($template['vendor_version'] ?? ''))
			=== (string) ($artifact['vendor_version'] ?? '');
	}
}
