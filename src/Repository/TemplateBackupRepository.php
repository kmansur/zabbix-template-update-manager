<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use RuntimeException;

/**
 * Stores current-template exports as private local rollback artifacts.
 *
 * This repository writes only local backup files. It does not modify Zabbix
 * configuration or the Zabbix database.
 */
final class TemplateBackupRepository {

	private const MAX_EXPORT_BYTES = 20971520;
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
		$templateId = trim((string) ($template['templateid'] ?? ''));
		$uuid = strtolower(str_replace('-', '', trim((string) ($template['uuid'] ?? ''))));
		$technicalName = trim((string) ($template['technical_name'] ?? ''));
		$visibleName = trim((string) ($template['name'] ?? ''));
		$vendorVersion = trim((string) ($template['vendor_version'] ?? ''));

		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('The backup template ID is invalid.');
		}
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

		$templateDir = $this->backupDir.DIRECTORY_SEPARATOR.'template-'.$templateId;
		if (!$this->ensureDirectory($templateDir)) {
			throw new RuntimeException(sprintf(
				'Unable to create the persistent template backup directory below %s.',
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

	private function ensureDirectory(string $directory): bool {
		if (is_dir($directory)) {
			return is_writable($directory);
		}
		if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
			return false;
		}
		@chmod($directory, 0700);
		return is_writable($directory);
	}

	private function writePrivateFile(string $path, string $content): bool {
		$tmp = $path.'.tmp-'.getmypid();
		if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
			return false;
		}
		@chmod($tmp, 0600);
		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			return false;
		}
		@chmod($path, 0600);
		return true;
	}
}
