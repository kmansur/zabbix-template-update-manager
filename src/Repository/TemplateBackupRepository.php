<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use RuntimeException;

/**
 * Stores and validates current-template exports as private local rollback artifacts.
 *
 * This repository writes only local backup files. It does not modify Zabbix
 * configuration or the Zabbix database.
 */
final class TemplateBackupRepository {

	private const MAX_EXPORT_BYTES = 20971520;
	private const MAX_MANIFEST_BYTES = 1048576;
	private const MAX_INSPECTION_LIMIT = 50;
	private const DEFAULT_BACKUP_DIR = '/var/lib/zabbix-template-update-manager/backups';

	private string $backupDir;
	private $clock;

	public function __construct(?string $backupDir = null, ?callable $clock = null) {
		$this->backupDir = $backupDir ?? self::DEFAULT_BACKUP_DIR;
		$this->clock = $clock ?? static fn(): int => time();
	}

	public static function defaultBackupDirectory(): string {
		return self::DEFAULT_BACKUP_DIR;
	}

	public function store(array $template, array $export): array {
		$templateId = $this->normalizeTemplateId((string) ($template['templateid'] ?? ''));
		$uuid = strtolower(str_replace('-', '', trim((string) ($template['uuid'] ?? ''))));
		$technicalName = trim((string) ($template['technical_name'] ?? ''));
		$visibleName = trim((string) ($template['name'] ?? ''));
		$vendorVersion = trim((string) ($template['vendor_version'] ?? ''));

		if ($uuid !== '' && !preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			throw new RuntimeException('The backup template UUID is invalid.');
		}
		if ($technicalName === '' || strlen($technicalName) > 255 || strlen($visibleName) > 255) {
			throw new RuntimeException('The backup template identity is invalid.');
		}
		if (strlen($vendorVersion) > 128) {
			throw new RuntimeException('The backup template vendor version is invalid.');
		}

		$format = (string) ($export['format'] ?? '');
		$source = $export['source'] ?? null;
		$sha256 = strtolower(trim((string) ($export['sha256'] ?? '')));
		$bytes = (int) ($export['bytes'] ?? -1);

		if ($format !== 'yaml'
				|| !is_string($source)
				|| $source === ''
				|| strlen($source) > self::MAX_EXPORT_BYTES
				|| $bytes !== strlen($source)
				|| !preg_match('/^[a-f0-9]{64}$/', $sha256)
				|| !hash_equals($sha256, hash('sha256', $source))) {
			throw new RuntimeException('The template export is invalid and cannot be stored as a backup.');
		}

		$timestamp = (int) ($this->clock)();
		if ($timestamp <= 0) {
			throw new RuntimeException('The backup clock returned an invalid timestamp.');
		}

		$templateDir = $this->templateDirectory($templateId);
		if (!$this->ensurePrivateDirectory($templateDir)) {
			throw new RuntimeException(sprintf(
				'Unable to create or secure the persistent template backup directory below %s.',
				$this->backupDir
			));
		}

		$stamp = gmdate('Ymd\THis\Z', $timestamp);
		$basename = 'backup-'.$stamp.'-'.substr($sha256, 0, 12);
		$sourcePath = $templateDir.DIRECTORY_SEPARATOR.$basename.'.yaml';
		$manifestPath = $templateDir.DIRECTORY_SEPARATOR.$basename.'.json';

		$manifest = [
			'schema_version' => 1,
			'created_at' => gmdate('c', $timestamp),
			'template' => [
				'templateid' => $templateId,
				'uuid' => $uuid,
				'technical_name' => $technicalName,
				'name' => $visibleName,
				'vendor_version' => $vendorVersion
			],
			'export' => [
				'format' => 'yaml',
				'bytes' => $bytes,
				'sha256' => $sha256,
				'file' => basename($sourcePath)
			]
		];

		$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($manifestJson)) {
			throw new RuntimeException('Unable to encode the template backup manifest.');
		}

		if (!$this->writePrivateFile($sourcePath, $source)) {
			throw new RuntimeException('Unable to write the template backup source.');
		}
		if (!$this->writePrivateFile($manifestPath, $manifestJson."\n")) {
			@unlink($sourcePath);
			throw new RuntimeException('Unable to write the template backup manifest.');
		}

		return [
			'created_at' => $manifest['created_at'],
			'templateid' => $templateId,
			'uuid' => $uuid,
			'format' => 'yaml',
			'bytes' => $bytes,
			'sha256' => $sha256,
			'source_path' => $sourcePath,
			'manifest_path' => $manifestPath
		];
	}

	/**
	 * Inspect recent rollback artifacts for one template.
	 *
	 * Every returned artifact is integrity checked against its manifest. The
	 * newest invalid artifact is intentionally not skipped by higher layers.
	 */
	public function inspectForTemplate(string $templateId, int $limit = 10): array {
		$templateId = $this->normalizeTemplateId($templateId);
		if ($limit < 1 || $limit > self::MAX_INSPECTION_LIMIT) {
			throw new RuntimeException('The backup inspection limit is invalid.');
		}

		$templateDir = $this->templateDirectory($templateId);
		if (!file_exists($templateDir)) {
			return [
				'status' => 'no_backup',
				'templateid' => $templateId,
				'artifacts' => [],
				'scanned' => 0,
				'valid' => 0,
				'invalid' => 0,
				'truncated' => false
			];
		}

		if (!is_dir($templateDir) || is_link($templateDir) || !is_readable($templateDir)) {
			return [
				'status' => 'repository_unavailable',
				'templateid' => $templateId,
				'artifacts' => [],
				'scanned' => 0,
				'valid' => 0,
				'invalid' => 0,
				'truncated' => false
			];
		}

		$manifestPaths = glob($templateDir.DIRECTORY_SEPARATOR.'backup-*.json', GLOB_NOSORT);
		if ($manifestPaths === false) {
			return [
				'status' => 'repository_unavailable',
				'templateid' => $templateId,
				'artifacts' => [],
				'scanned' => 0,
				'valid' => 0,
				'invalid' => 0,
				'truncated' => false
			];
		}

		$manifestPaths = array_values(array_filter(
			$manifestPaths,
			static fn(string $path): bool => preg_match(
				'/^backup-\d{8}T\d{6}Z-[a-f0-9]{12}\.json$/',
				basename($path)
			) === 1
		));
		rsort($manifestPaths, SORT_STRING);

		$total = count($manifestPaths);
		$manifestPaths = array_slice($manifestPaths, 0, $limit);
		$artifacts = [];
		$valid = 0;
		$invalid = 0;

		foreach ($manifestPaths as $manifestPath) {
			$artifact = $this->inspectManifest($templateId, $templateDir, $manifestPath);
			$artifacts[] = $artifact;
			if (($artifact['status'] ?? null) === 'valid') {
				$valid++;
			}
			else {
				$invalid++;
			}
		}

		return [
			'status' => $artifacts === [] ? 'no_backup' : 'ok',
			'templateid' => $templateId,
			'artifacts' => $artifacts,
			'scanned' => count($artifacts),
			'valid' => $valid,
			'invalid' => $invalid,
			'truncated' => $total > $limit
		];
	}

	private function inspectManifest(string $templateId, string $templateDir, string $manifestPath): array {
		$invalid = static fn(string $reason): array => [
			'status' => 'invalid',
			'reason' => $reason,
			'manifest_file' => basename($manifestPath)
		];

		if (is_link($manifestPath) || !is_file($manifestPath) || !is_readable($manifestPath)) {
			return $invalid('manifest_unreadable');
		}

		$manifestSize = @filesize($manifestPath);
		if ($manifestSize === false || $manifestSize < 2 || $manifestSize > self::MAX_MANIFEST_BYTES) {
			return $invalid('manifest_size_invalid');
		}

		$manifestJson = @file_get_contents($manifestPath);
		if (!is_string($manifestJson) || $manifestJson === '') {
			return $invalid('manifest_unreadable');
		}

		try {
			$manifest = json_decode($manifestJson, true, 64, JSON_THROW_ON_ERROR);
		}
		catch (\Throwable $exception) {
			return $invalid('manifest_json_invalid');
		}

		if (!is_array($manifest) || ($manifest['schema_version'] ?? null) !== 1) {
			return $invalid('manifest_schema_invalid');
		}

		$createdAt = (string) ($manifest['created_at'] ?? '');
		if ($createdAt === '' || strtotime($createdAt) === false) {
			return $invalid('manifest_created_at_invalid');
		}

		$template = is_array($manifest['template'] ?? null) ? $manifest['template'] : [];
		if ((string) ($template['templateid'] ?? '') !== $templateId) {
			return $invalid('manifest_template_mismatch');
		}

		$uuid = strtolower(trim((string) ($template['uuid'] ?? '')));
		if ($uuid !== '' && !preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			return $invalid('manifest_uuid_invalid');
		}

		$technicalName = (string) ($template['technical_name'] ?? '');
		$visibleName = (string) ($template['name'] ?? '');
		$vendorVersion = (string) ($template['vendor_version'] ?? '');
		if ($technicalName === '' || strlen($technicalName) > 255
				|| strlen($visibleName) > 255 || strlen($vendorVersion) > 128) {
			return $invalid('manifest_identity_invalid');
		}

		$export = is_array($manifest['export'] ?? null) ? $manifest['export'] : [];
		$bytes = (int) ($export['bytes'] ?? -1);
		$sha256 = strtolower(trim((string) ($export['sha256'] ?? '')));
		$sourceFile = (string) ($export['file'] ?? '');
		$expectedSourceFile = pathinfo($manifestPath, PATHINFO_FILENAME).'.yaml';

		if (($export['format'] ?? null) !== 'yaml'
				|| $bytes < 1
				|| $bytes > self::MAX_EXPORT_BYTES
				|| !preg_match('/^[a-f0-9]{64}$/', $sha256)
				|| $sourceFile === ''
				|| basename($sourceFile) !== $sourceFile
				|| $sourceFile !== $expectedSourceFile) {
			return $invalid('manifest_export_invalid');
		}

		$sourcePath = $templateDir.DIRECTORY_SEPARATOR.$sourceFile;
		if (is_link($sourcePath) || !is_file($sourcePath) || !is_readable($sourcePath)) {
			return $invalid('source_unreadable');
		}

		$sourceSize = @filesize($sourcePath);
		if ($sourceSize === false || $sourceSize !== $bytes) {
			return $invalid('source_size_mismatch');
		}

		$actualSha256 = @hash_file('sha256', $sourcePath);
		if (!is_string($actualSha256) || !hash_equals($sha256, strtolower($actualSha256))) {
			return $invalid('source_hash_mismatch');
		}

		if (DIRECTORY_SEPARATOR === '/') {
			$manifestPermissions = @fileperms($manifestPath);
			$sourcePermissions = @fileperms($sourcePath);
			if ($manifestPermissions === false || (($manifestPermissions & 0777) !== 0600)
					|| $sourcePermissions === false || (($sourcePermissions & 0777) !== 0600)) {
				return $invalid('artifact_permissions_insecure');
			}
		}

		return [
			'status' => 'valid',
			'reason' => null,
			'created_at' => $createdAt,
			'templateid' => $templateId,
			'uuid' => $uuid,
			'technical_name' => $technicalName,
			'name' => $visibleName,
			'vendor_version' => $vendorVersion,
			'format' => 'yaml',
			'bytes' => $bytes,
			'sha256' => $sha256,
			'source_file' => $sourceFile,
			'manifest_file' => basename($manifestPath),
			'source_path' => $sourcePath,
			'manifest_path' => $manifestPath
		];
	}

	private function normalizeTemplateId(string $templateId): string {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('The backup template ID is invalid.');
		}
		return $templateId;
	}

	private function templateDirectory(string $templateId): string {
		return $this->backupDir.DIRECTORY_SEPARATOR.'template-'.$templateId;
	}

	private function ensurePrivateDirectory(string $directory): bool {
		if (!is_dir($directory)) {
			if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
				return false;
			}
		}

		if (DIRECTORY_SEPARATOR === '/') {
			if (!@chmod($directory, 0700)) {
				return false;
			}
			$permissions = @fileperms($directory);
			if ($permissions === false || (($permissions & 0777) !== 0700)) {
				return false;
			}
		}

		return is_writable($directory);
	}

	private function writePrivateFile(string $path, string $content): bool {
		try {
			$tmp = $path.'.tmp-'.bin2hex(random_bytes(8));
		}
		catch (\Throwable $exception) {
			return false;
		}

		$previousUmask = umask(0077);
		try {
			$written = @file_put_contents($tmp, $content, LOCK_EX);
		}
		finally {
			umask($previousUmask);
		}

		if ($written === false) {
			@unlink($tmp);
			return false;
		}

		if (DIRECTORY_SEPARATOR === '/') {
			if (!@chmod($tmp, 0600)) {
				@unlink($tmp);
				return false;
			}
			$permissions = @fileperms($tmp);
			if ($permissions === false || (($permissions & 0777) !== 0600)) {
				@unlink($tmp);
				return false;
			}
		}

		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			return false;
		}

		return true;
	}
}
