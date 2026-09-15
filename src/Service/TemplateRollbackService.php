<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

require_once __DIR__.'/TemplateBackupService.php';
require_once __DIR__.'/TemplateConfigurationImportService.php';
require_once __DIR__.'/TemplatePostRollbackValidationService.php';
require_once __DIR__.'/TemplateRollbackArtifactService.php';
require_once __DIR__.'/TemplateRollbackPreflightService.php';

/**
 * Executes one explicitly confirmed rollback to a previously validated local
 * artifact. The existing controlled configuration-import service remains the
 * repository's single Zabbix configuration write boundary.
 */
final class TemplateRollbackService {

	private $preflightRunner;
	private $recoveryBackupCreator;
	private $artifactLoader;
	private $importer;
	private $validator;

	public function __construct(
		?callable $preflightRunner = null,
		?callable $recoveryBackupCreator = null,
		?callable $artifactLoader = null,
		?callable $importer = null,
		?callable $validator = null
	) {
		$this->preflightRunner = $preflightRunner ?? static fn(string $templateId, string $manifestFile): array
			=> (new TemplateRollbackPreflightService())->run($templateId, $manifestFile);
		$this->recoveryBackupCreator = $recoveryBackupCreator ?? static fn(array $template): array
			=> (new TemplateBackupService())->create($template);
		$this->artifactLoader = $artifactLoader ?? static fn(string $templateId, string $manifestFile): array
			=> (new TemplateRollbackArtifactService())->load($templateId, $manifestFile);
		$this->importer = $importer ?? static function (array $artifact): void {
			(new TemplateConfigurationImportService())->import(
				(string) ($artifact['source'] ?? ''),
				(string) ($artifact['format'] ?? 'yaml')
			);
		};
		$this->validator = $validator ?? static fn(string $templateId, array $artifact): array
			=> (new TemplatePostRollbackValidationService())->validate($templateId, $artifact);
	}

	public function execute(string $templateId, string $manifestFile, string $expectedEvidenceSha256): array {
		$templateId = trim($templateId);
		$manifestFile = trim($manifestFile);
		$expectedEvidenceSha256 = strtolower(trim($expectedEvidenceSha256));

		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for rollback.');
		}
		if (basename($manifestFile) !== $manifestFile
				|| preg_match('/^backup-\d{8}T\d{6}Z-[a-f0-9]{12}\.json$/', $manifestFile) !== 1) {
			throw new RuntimeException('A valid rollback manifest filename is required.');
		}
		if (preg_match('/^[a-f0-9]{64}$/', $expectedEvidenceSha256) !== 1) {
			throw new RuntimeException('A valid rollback preflight evidence fingerprint is required.');
		}

		$preflight = ($this->preflightRunner)($templateId, $manifestFile);
		if (!is_array($preflight)) {
			throw new RuntimeException('Fresh rollback preflight returned invalid data.');
		}
		if (($preflight['status'] ?? null) !== 'ready') {
			return $this->blockedResult('blocked_preflight', $preflight, null, null);
		}

		$freshEvidence = strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')));
		if (preg_match('/^[a-f0-9]{64}$/', $freshEvidence) !== 1
				|| !hash_equals($expectedEvidenceSha256, $freshEvidence)) {
			return $this->blockedResult('blocked_evidence_changed', $preflight, null, null);
		}

		// Re-run the complete rollback preflight immediately before preparing the
		// target/recovery artifacts. This second pass is still configuration-read-only.
		$secondPreflight = ($this->preflightRunner)($templateId, $manifestFile);
		if (!is_array($secondPreflight)
				|| ($secondPreflight['status'] ?? null) !== 'ready'
				|| !hash_equals($freshEvidence, strtolower((string) ($secondPreflight['evidence_sha256'] ?? '')))) {
			return $this->blockedResult(
				'blocked_evidence_changed',
				is_array($secondPreflight) ? $secondPreflight : $preflight,
				null,
				null
			);
		}

		$template = is_array($secondPreflight['template'] ?? null) ? $secondPreflight['template'] : null;
		$currentExport = is_array($secondPreflight['current_export'] ?? null)
			? $secondPreflight['current_export']
			: null;
		if ($template === null || $currentExport === null) {
			throw new RuntimeException('Rollback preflight omitted current template/export evidence.');
		}

		// Load and revalidate the selected target while it is still guaranteed to
		// be inside the same bounded history window used by the confirmation page.
		// The import later consumes these validated in-memory bytes. Creating the
		// recovery backup cannot mutate this already loaded target content.
		$artifact = ($this->artifactLoader)($templateId, $manifestFile);
		if (!is_array($artifact)) {
			throw new RuntimeException('Rollback target loader returned invalid data.');
		}
		$this->assertTargetMatchesPreflight($artifact, $secondPreflight);

		// Persist the current state before restoring the older target. The recovery
		// artifact must prove that no current-template drift occurred after preflight.
		$recoveryBackup = ($this->recoveryBackupCreator)($template);
		if (!is_array($recoveryBackup)) {
			throw new RuntimeException('Recovery backup creation returned invalid data.');
		}

		$currentSha = strtolower(trim((string) ($currentExport['sha256'] ?? '')));
		$recoverySha = strtolower(trim((string) ($recoveryBackup['sha256'] ?? '')));
		$currentBytes = (int) ($currentExport['bytes'] ?? -1);
		$recoveryBytes = (int) ($recoveryBackup['bytes'] ?? -2);
		if (!preg_match('/^[a-f0-9]{64}$/', $currentSha)
				|| !preg_match('/^[a-f0-9]{64}$/', $recoverySha)
				|| !hash_equals($currentSha, $recoverySha)
				|| $currentBytes < 1
				|| $currentBytes !== $recoveryBytes) {
			return $this->blockedResult(
				'blocked_current_changed',
				$secondPreflight,
				$recoveryBackup,
				$artifact
			);
		}

		($this->importer)($artifact);

		try {
			$validation = ($this->validator)($templateId, $artifact);
		}
		catch (\Throwable $exception) {
			return [
				'status' => 'validation_error',
				'write_performed' => true,
				'reason' => 'post_rollback_validation_error',
				'preflight_evidence_sha256' => $freshEvidence,
				'recovery_backup' => $this->backupDescriptor($recoveryBackup),
				'target' => $this->targetDescriptor($artifact),
				'validation' => null
			];
		}

		if (!is_array($validation)) {
			throw new RuntimeException('Post-rollback validation returned invalid data.');
		}

		return [
			'status' => !empty($validation['valid']) ? 'rolled_back' : 'validation_failed',
			'write_performed' => true,
			'reason' => !empty($validation['valid']) ? null : 'post_rollback_validation_failed',
			'preflight_evidence_sha256' => $freshEvidence,
			'recovery_backup' => $this->backupDescriptor($recoveryBackup),
			'target' => $this->targetDescriptor($artifact),
			'validation' => $validation
		];
	}

	private function blockedResult(string $status, array $preflight, ?array $recoveryBackup, ?array $target): array {
		return [
			'status' => $status,
			'write_performed' => false,
			'reason' => $status,
			'preflight_status' => (string) ($preflight['status'] ?? 'unknown'),
			'preflight' => $preflight,
			'recovery_backup' => $recoveryBackup !== null ? $this->backupDescriptor($recoveryBackup) : null,
			'target' => $target !== null ? $this->targetDescriptor($target) : null,
			'validation' => null
		];
	}

	private function assertTargetMatchesPreflight(array $artifact, array $preflight): void {
		$target = is_array($preflight['target'] ?? null) ? $preflight['target'] : [];
		foreach (['templateid', 'uuid', 'vendor_version', 'manifest_file', 'source_file', 'sha256'] as $field) {
			if ((string) ($artifact[$field] ?? '') !== (string) ($target[$field] ?? '')) {
				throw new RuntimeException('The rollback target changed after the final preflight.');
			}
		}
		if ((int) ($artifact['bytes'] ?? -1) !== (int) ($target['bytes'] ?? -2)
				|| !is_string($artifact['source'] ?? null)
				|| $artifact['source'] === '') {
			throw new RuntimeException('The rollback target content evidence is invalid.');
		}
	}

	private function backupDescriptor(array $backup): array {
		return [
			'created_at' => (string) ($backup['created_at'] ?? ''),
			'bytes' => (int) ($backup['bytes'] ?? 0),
			'sha256' => (string) ($backup['sha256'] ?? ''),
			'manifest_file' => basename((string) ($backup['manifest_path'] ?? '')),
			'source_file' => basename((string) ($backup['source_path'] ?? ''))
		];
	}

	private function targetDescriptor(array $artifact): array {
		return [
			'created_at' => (string) ($artifact['created_at'] ?? ''),
			'vendor_version' => (string) ($artifact['vendor_version'] ?? ''),
			'bytes' => (int) ($artifact['bytes'] ?? 0),
			'sha256' => (string) ($artifact['sha256'] ?? ''),
			'manifest_file' => (string) ($artifact['manifest_file'] ?? ''),
			'source_file' => (string) ($artifact['source_file'] ?? '')
		];
	}
}
