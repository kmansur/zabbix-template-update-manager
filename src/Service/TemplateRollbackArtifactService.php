<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use RuntimeException;

require_once dirname(__DIR__).'/Repository/TemplateBackupRepository.php';

/**
 * Resolves one explicitly selected rollback artifact from the bounded backup
 * inventory and revalidates its exact YAML bytes immediately before use.
 */
final class TemplateRollbackArtifactService {

	private const MAX_EXPORT_BYTES = 20971520;
	private TemplateBackupRepository $repository;

	public function __construct(?TemplateBackupRepository $repository = null) {
		$this->repository = $repository ?? new TemplateBackupRepository();
	}

	public function load(string $templateId, string $manifestFile): array {
		$templateId = $this->normalizeTemplateId($templateId);
		$manifestFile = trim($manifestFile);

		if (basename($manifestFile) !== $manifestFile
				|| preg_match('/^backup-\d{8}T\d{6}Z-[a-f0-9]{12}\.json$/', $manifestFile) !== 1) {
			throw new RuntimeException('The selected rollback manifest filename is invalid.');
		}

		$inspection = $this->repository->inspectForTemplate($templateId, 50);
		if (($inspection['status'] ?? null) === 'repository_unavailable') {
			throw new RuntimeException('The rollback repository is unavailable.');
		}

		$selected = null;
		foreach (($inspection['artifacts'] ?? []) as $artifact) {
			if (is_array($artifact) && ($artifact['manifest_file'] ?? null) === $manifestFile) {
				$selected = $artifact;
				break;
			}
		}

		if ($selected === null) {
			throw new RuntimeException('The selected rollback artifact was not found in the bounded repository view.');
		}
		if (($selected['status'] ?? null) !== 'valid') {
			throw new RuntimeException('The selected rollback artifact failed integrity validation.');
		}

		$sourcePath = (string) ($selected['source_path'] ?? '');
		$sourceFile = (string) ($selected['source_file'] ?? '');
		$expectedSourceFile = pathinfo($manifestFile, PATHINFO_FILENAME).'.yaml';
		$bytes = (int) ($selected['bytes'] ?? -1);
		$sha256 = strtolower(trim((string) ($selected['sha256'] ?? '')));
		$uuid = strtolower(trim((string) ($selected['uuid'] ?? '')));

		if ($sourcePath === ''
				|| $sourceFile !== $expectedSourceFile
				|| basename($sourceFile) !== $sourceFile
				|| $bytes < 1
				|| $bytes > self::MAX_EXPORT_BYTES
				|| preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
				|| preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
			throw new RuntimeException('The selected rollback artifact metadata is incomplete or unsafe.');
		}

		if (is_link($sourcePath) || !is_file($sourcePath) || !is_readable($sourcePath)) {
			throw new RuntimeException('The selected rollback YAML source is not safely readable.');
		}

		$actualBytes = @filesize($sourcePath);
		if ($actualBytes === false || $actualBytes !== $bytes) {
			throw new RuntimeException('The selected rollback YAML byte count changed after repository inspection.');
		}

		$source = @file_get_contents($sourcePath);
		if (!is_string($source) || $source === '' || strlen($source) !== $bytes) {
			throw new RuntimeException('The selected rollback YAML could not be read completely.');
		}

		$actualSha256 = hash('sha256', $source);
		if (!hash_equals($sha256, $actualSha256)) {
			throw new RuntimeException('The selected rollback YAML SHA-256 changed after repository inspection.');
		}

		return [
			'templateid' => $templateId,
			'uuid' => $uuid,
			'name' => (string) ($selected['name'] ?? ''),
			'technical_name' => (string) ($selected['technical_name'] ?? ''),
			'vendor_version' => (string) ($selected['vendor_version'] ?? ''),
			'created_at' => (string) ($selected['created_at'] ?? ''),
			'bytes' => $bytes,
			'sha256' => $sha256,
			'manifest_file' => $manifestFile,
			'source_file' => $sourceFile,
			'format' => 'yaml',
			'source' => $source
		];
	}

	private function normalizeTemplateId(string $templateId): string {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for rollback.');
		}

		return $templateId;
	}
}
