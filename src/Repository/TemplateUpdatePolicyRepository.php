<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use RuntimeException;
use Throwable;

/**
 * Persists the global per-template update policy used by ZTUM.
 *
 * The policy is intentionally stored outside the Zabbix database in the same
 * private runtime area already used by the module. Official-template UUID is
 * the authoritative key; templateid is retained only as a current local hint.
 */
final class TemplateUpdatePolicyRepository {

	public const POLICY_MANAGED = 'managed';
	public const POLICY_NEVER_UPDATE = 'never_update';

	private const SCHEMA_VERSION = 1;
	private const MAX_FILE_BYTES = 1048576;
	private const DEFAULT_PATH = '/var/lib/zabbix-template-update-manager/update-policy.json';

	private string $path;

	public function __construct(?string $path = null) {
		$this->path = $path ?? self::DEFAULT_PATH;

		if ($this->path === ''
				|| $this->path[0] !== DIRECTORY_SEPARATOR
				|| str_contains($this->path, "\0")) {
			throw new RuntimeException('Update-policy storage path must be an absolute safe path.');
		}
	}

	public static function defaultPath(): string {
		return self::DEFAULT_PATH;
	}

	public function snapshot(): array {
		return $this->readState();
	}

	public function annotate(array $templates): array {
		$state = $this->readState();
		$neverUpdate = is_array($state['never_update'] ?? null) ? $state['never_update'] : [];
		$byTemplateId = [];

		foreach ($neverUpdate as $uuid => $record) {
			if (!is_array($record)) {
				continue;
			}
			$templateId = trim((string) ($record['templateid'] ?? ''));
			if ($templateId !== '') {
				$byTemplateId[$templateId] = (string) $uuid;
			}
		}

		foreach ($templates as &$template) {
			$uuid = self::normalizeUuid((string) ($template['uuid'] ?? ''));
			$templateId = trim((string) ($template['templateid'] ?? ''));
			$blocked = ($uuid !== '' && array_key_exists($uuid, $neverUpdate))
				|| ($templateId !== '' && array_key_exists($templateId, $byTemplateId));

			$template['update_policy'] = $blocked
				? self::POLICY_NEVER_UPDATE
				: self::POLICY_MANAGED;
			$template['never_update'] = $blocked;

			if ($blocked) {
				$recordKey = $uuid !== '' && array_key_exists($uuid, $neverUpdate)
					? $uuid
					: ($byTemplateId[$templateId] ?? '');
				$record = is_array($neverUpdate[$recordKey] ?? null) ? $neverUpdate[$recordKey] : [];
				$template['update_policy_marked_at'] = (string) ($record['marked_at'] ?? '');
				$template['update_policy_marked_by'] = (string) ($record['marked_by'] ?? '');
			}
			else {
				$template['update_policy_marked_at'] = '';
				$template['update_policy_marked_by'] = '';
			}
		}
		unset($template);

		return $templates;
	}

	public function isNeverUpdate(string $templateId, string $uuid = ''): bool {
		$templateId = trim($templateId);
		$uuid = self::normalizeUuid($uuid);
		$state = $this->readState();
		$neverUpdate = is_array($state['never_update'] ?? null) ? $state['never_update'] : [];

		if ($uuid !== '' && array_key_exists($uuid, $neverUpdate)) {
			return true;
		}

		if ($templateId !== '') {
			foreach ($neverUpdate as $record) {
				if (is_array($record) && (string) ($record['templateid'] ?? '') === $templateId) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return array{changed:int,total:int}
	 */
	public function setNeverUpdate(array $templates, string $markedBy = ''): array {
		return $this->mutate($templates, true, $markedBy);
	}

	/**
	 * @return array{changed:int,total:int}
	 */
	public function allowUpdates(array $templates, string $markedBy = ''): array {
		return $this->mutate($templates, false, $markedBy);
	}

	private function mutate(array $templates, bool $neverUpdate, string $markedBy): array {
		$normalized = $this->normalizeTemplates($templates);
		if ($normalized === []) {
			throw new RuntimeException('At least one valid official template is required for update-policy changes.');
		}

		$directory = dirname($this->path);
		$this->assertWritableDirectory($directory);

		$lockPath = $this->path.'.lock';
		if (is_link($lockPath)) {
			throw new RuntimeException('Update-policy lock path is unsafe.');
		}

		$lock = @fopen($lockPath, 'c+');
		if (!is_resource($lock)) {
			throw new RuntimeException('Unable to open update-policy lock file.');
		}

		try {
			@chmod($lockPath, 0600);
			if (!@flock($lock, LOCK_EX)) {
				throw new RuntimeException('Unable to lock update-policy storage.');
			}

			$state = $this->readStateUnlocked();
			$records = is_array($state['never_update'] ?? null) ? $state['never_update'] : [];
			$before = $records;

			foreach ($normalized as $template) {
				$uuid = $template['uuid'];
				$templateId = $template['templateid'];

				if ($neverUpdate) {
					$records[$uuid] = [
						'uuid' => $uuid,
						'templateid' => $templateId,
						'name' => $template['name'],
						'technical_name' => $template['technical_name'],
						'marked_at' => gmdate('c'),
						'marked_by' => self::sanitizeMetadata($markedBy, 128)
					];
					continue;
				}

				unset($records[$uuid]);
				foreach ($records as $recordUuid => $record) {
					if (is_array($record)
							&& $templateId !== ''
							&& (string) ($record['templateid'] ?? '') === $templateId) {
						unset($records[$recordUuid]);
					}
				}
			}

			ksort($records, SORT_STRING);
			$state = [
				'schema_version' => self::SCHEMA_VERSION,
				'never_update' => $records
			];

			if ($records !== $before) {
				$this->writeStateUnlocked($state);
			}

			return [
				'changed' => self::changedCount($before, $records),
				'total' => count($records)
			];
		}
		finally {
			@flock($lock, LOCK_UN);
			@fclose($lock);
		}
	}

	private function readState(): array {
		if (is_link($this->path)) {
			throw new RuntimeException('Update-policy file path is unsafe.');
		}
		if (!file_exists($this->path)) {
			return self::emptyState();
		}
		$this->assertReadableDirectory(dirname($this->path));

		$handle = @fopen($this->path, 'rb');
		if (!is_resource($handle)) {
			throw new RuntimeException('Unable to open update-policy file.');
		}

		try {
			if (!@flock($handle, LOCK_SH)) {
				throw new RuntimeException('Unable to lock update-policy file for reading.');
			}
			return $this->readFromHandle($handle);
		}
		finally {
			@flock($handle, LOCK_UN);
			@fclose($handle);
		}
	}

	private function readStateUnlocked(): array {
		if (is_link($this->path)) {
			throw new RuntimeException('Update-policy file path is unsafe.');
		}
		if (!file_exists($this->path)) {
			return self::emptyState();
		}
		$this->assertReadableDirectory(dirname($this->path));

		$handle = @fopen($this->path, 'rb');
		if (!is_resource($handle)) {
			throw new RuntimeException('Unable to open update-policy file.');
		}

		try {
			return $this->readFromHandle($handle);
		}
		finally {
			@fclose($handle);
		}
	}

	private function readFromHandle($handle): array {
		$stat = @fstat($handle);
		$size = is_array($stat) ? (int) ($stat['size'] ?? -1) : -1;
		if ($size < 0 || $size > self::MAX_FILE_BYTES) {
			throw new RuntimeException('Update-policy file size is invalid.');
		}

		$content = stream_get_contents($handle, self::MAX_FILE_BYTES + 1);
		if (!is_string($content) || strlen($content) > self::MAX_FILE_BYTES) {
			throw new RuntimeException('Unable to read update-policy file safely.');
		}
		if (trim($content) === '') {
			throw new RuntimeException('Update-policy file is empty or invalid.');
		}

		try {
			$decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
		}
		catch (Throwable $exception) {
			throw new RuntimeException('Update-policy file contains invalid JSON.', 0, $exception);
		}

		return $this->validateState($decoded);
	}

	private function writeStateUnlocked(array $state): void {
		$directory = dirname($this->path);
		$encoded = json_encode(
			$state,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
		if (!is_string($encoded) || strlen($encoded) > self::MAX_FILE_BYTES) {
			throw new RuntimeException('Update-policy state exceeds the safe storage limit.');
		}
		$encoded .= "\n";

		$tmp = @tempnam($directory, '.ztum-policy-');
		if (!is_string($tmp) || $tmp === '') {
			throw new RuntimeException('Unable to create temporary update-policy file.');
		}

		try {
			if (is_link($tmp)) {
				throw new RuntimeException('Temporary update-policy path is unsafe.');
			}
			@chmod($tmp, 0600);
			$handle = @fopen($tmp, 'wb');
			if (!is_resource($handle)) {
				throw new RuntimeException('Unable to open temporary update-policy file.');
			}

			try {
				$written = @fwrite($handle, $encoded);
				if ($written !== strlen($encoded)) {
					throw new RuntimeException('Unable to write complete update-policy state.');
				}
				if (!@fflush($handle)) {
					throw new RuntimeException('Unable to flush update-policy state.');
				}
				if (function_exists('fsync')) {
					@fsync($handle);
				}
			}
			finally {
				@fclose($handle);
			}

			if (!@rename($tmp, $this->path)) {
				throw new RuntimeException('Unable to atomically publish update-policy state.');
			}
			@chmod($this->path, 0600);
		}
		finally {
			if (file_exists($tmp)) {
				@unlink($tmp);
			}
		}
	}

	private function validateState($decoded): array {
		if (!is_array($decoded)
				|| (int) ($decoded['schema_version'] ?? 0) !== self::SCHEMA_VERSION
				|| !is_array($decoded['never_update'] ?? null)) {
			throw new RuntimeException('Update-policy file schema is invalid.');
		}

		$records = [];
		foreach ($decoded['never_update'] as $key => $record) {
			if (!is_array($record)) {
				throw new RuntimeException('Update-policy record is invalid.');
			}

			$uuid = self::normalizeUuid((string) ($record['uuid'] ?? $key));
			$templateId = trim((string) ($record['templateid'] ?? ''));
			if (!preg_match('/^[a-f0-9]{32}$/', $uuid)
					|| ($templateId !== '' && (!ctype_digit($templateId) || (int) $templateId <= 0))) {
				throw new RuntimeException('Update-policy template identity is invalid.');
			}

			$records[$uuid] = [
				'uuid' => $uuid,
				'templateid' => $templateId,
				'name' => self::sanitizeMetadata((string) ($record['name'] ?? ''), 256),
				'technical_name' => self::sanitizeMetadata((string) ($record['technical_name'] ?? ''), 256),
				'marked_at' => self::sanitizeMetadata((string) ($record['marked_at'] ?? ''), 64),
				'marked_by' => self::sanitizeMetadata((string) ($record['marked_by'] ?? ''), 128)
			];
		}

		ksort($records, SORT_STRING);
		return [
			'schema_version' => self::SCHEMA_VERSION,
			'never_update' => $records
		];
	}

	private function normalizeTemplates(array $templates): array {
		$result = [];
		foreach ($templates as $template) {
			if (!is_array($template)) {
				throw new RuntimeException('Update-policy template record is invalid.');
			}

			$uuid = self::normalizeUuid((string) ($template['uuid'] ?? ''));
			$templateId = trim((string) ($template['templateid'] ?? ''));
			if (!preg_match('/^[a-f0-9]{32}$/', $uuid)
					|| $templateId === ''
					|| !ctype_digit($templateId)
					|| (int) $templateId <= 0) {
				throw new RuntimeException('Update-policy changes require a valid official UUID and numeric template ID.');
			}

			$result[$uuid] = [
				'uuid' => $uuid,
				'templateid' => $templateId,
				'name' => self::sanitizeMetadata((string) ($template['name'] ?? ''), 256),
				'technical_name' => self::sanitizeMetadata((string) ($template['technical_name'] ?? ''), 256)
			];
		}

		return array_values($result);
	}

	private function assertReadableDirectory(string $directory): void {
		if (is_link($directory)
				|| !is_dir($directory)
				|| !is_readable($directory)) {
			throw new RuntimeException(
				'Update-policy runtime directory is unavailable or not readable: '.$directory
			);
		}
	}

	private function assertWritableDirectory(string $directory): void {
		if (is_link($directory)
				|| !is_dir($directory)
				|| !is_writable($directory)) {
			throw new RuntimeException(
				'Update-policy runtime directory is unavailable or not writable: '.$directory
			);
		}
	}

	private static function emptyState(): array {
		return [
			'schema_version' => self::SCHEMA_VERSION,
			'never_update' => []
		];
	}

	private static function normalizeUuid(string $uuid): string {
		$uuid = strtolower(str_replace('-', '', trim($uuid)));
		return preg_match('/^[a-f0-9]{32}$/', $uuid) === 1 ? $uuid : '';
	}

	private static function sanitizeMetadata(string $value, int $maxLength): string {
		$value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
		$value = is_string($value) ? trim($value) : '';
		return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
	}

	private static function changedCount(array $before, array $after): int {
		$keys = array_unique(array_merge(array_keys($before), array_keys($after)));
		$changed = 0;
		foreach ($keys as $key) {
			if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
				$changed++;
			}
		}
		return $changed;
	}
}
