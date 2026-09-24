<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use RuntimeException;
use Throwable;

/**
 * Private, bounded operation-history storage for ZTUM operator visibility.
 *
 * This log is supplemental operational history, not an authorization or
 * write-safety source. Controlled-operation evidence remains authoritative.
 */
final class TemplateOperationHistoryRepository {

	private const SCHEMA_VERSION = 1;
	private const MAX_ENTRIES = 1000;
	private const MAX_FILE_BYTES = 2097152;
	private const DEFAULT_PATH = '/var/lib/zabbix-template-update-manager/operation-history.json';

	private string $path;

	public function __construct(?string $path = null) {
		$this->path = $path ?? self::DEFAULT_PATH;

		if ($this->path === ''
				|| $this->path[0] !== DIRECTORY_SEPARATOR
				|| str_contains($this->path, "\0")) {
			throw new RuntimeException('Operation-history storage path must be an absolute safe path.');
		}
	}

	public static function defaultPath(): string {
		return self::DEFAULT_PATH;
	}

	public function append(array $entry): array {
		$entry = $this->normalizeEntry($entry);
		$directory = dirname($this->path);
		$this->assertWritableDirectory($directory);

		$lockPath = $this->path.'.lock';
		if (is_link($lockPath)) {
			throw new RuntimeException('Operation-history lock path is unsafe.');
		}

		$lock = @fopen($lockPath, 'c+');
		if (!is_resource($lock)) {
			throw new RuntimeException('Unable to open operation-history lock file.');
		}

		try {
			@chmod($lockPath, 0600);
			if (!@flock($lock, LOCK_EX)) {
				throw new RuntimeException('Unable to lock operation-history storage.');
			}

			$state = $this->readStateUnlocked();
			$entries = is_array($state['entries'] ?? null) ? $state['entries'] : [];
			$entries[] = $entry;
			if (count($entries) > self::MAX_ENTRIES) {
				$entries = array_slice($entries, -self::MAX_ENTRIES);
			}

			$this->writeStateUnlocked([
				'schema_version' => self::SCHEMA_VERSION,
				'entries' => $entries
			]);
		}
		finally {
			@flock($lock, LOCK_UN);
			@fclose($lock);
		}

		return $entry;
	}

	public function recent(int $limit = 100): array {
		if ($limit < 1 || $limit > self::MAX_ENTRIES) {
			throw new RuntimeException('Operation-history limit is invalid.');
		}

		$state = $this->readState();
		$entries = is_array($state['entries'] ?? null) ? $state['entries'] : [];
		$entries = array_slice($entries, -$limit);
		$entries = array_reverse($entries);

		return $entries;
	}

	private function readState(): array {
		if (is_link($this->path)) {
			throw new RuntimeException('Operation-history file path is unsafe.');
		}
		if (!file_exists($this->path)) {
			return self::emptyState();
		}

		$this->assertReadableDirectory(dirname($this->path));
		$handle = @fopen($this->path, 'rb');
		if (!is_resource($handle)) {
			throw new RuntimeException('Unable to open operation-history file.');
		}

		try {
			if (!@flock($handle, LOCK_SH)) {
				throw new RuntimeException('Unable to lock operation-history file for reading.');
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
			throw new RuntimeException('Operation-history file path is unsafe.');
		}
		if (!file_exists($this->path)) {
			return self::emptyState();
		}

		$this->assertReadableDirectory(dirname($this->path));
		$handle = @fopen($this->path, 'rb');
		if (!is_resource($handle)) {
			throw new RuntimeException('Unable to open operation-history file.');
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
			throw new RuntimeException('Operation-history file size is invalid.');
		}

		$content = stream_get_contents($handle, self::MAX_FILE_BYTES + 1);
		if (!is_string($content) || strlen($content) > self::MAX_FILE_BYTES || trim($content) === '') {
			throw new RuntimeException('Operation-history file is empty or unreadable.');
		}

		try {
			$decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
		}
		catch (Throwable $exception) {
			throw new RuntimeException('Operation-history file contains invalid JSON.', 0, $exception);
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
			throw new RuntimeException('Operation-history state exceeds the safe storage limit.');
		}
		$encoded .= "\n";

		$tmp = @tempnam($directory, '.ztum-history-');
		if (!is_string($tmp) || $tmp === '') {
			throw new RuntimeException('Unable to create temporary operation-history file.');
		}

		try {
			if (is_link($tmp)) {
				throw new RuntimeException('Temporary operation-history path is unsafe.');
			}
			@chmod($tmp, 0600);
			$handle = @fopen($tmp, 'wb');
			if (!is_resource($handle)) {
				throw new RuntimeException('Unable to open temporary operation-history file.');
			}

			try {
				$written = @fwrite($handle, $encoded);
				if ($written !== strlen($encoded)) {
					throw new RuntimeException('Unable to write complete operation-history state.');
				}
				if (!@fflush($handle)) {
					throw new RuntimeException('Unable to flush operation-history state.');
				}
				if (function_exists('fsync')) {
					@fsync($handle);
				}
			}
			finally {
				@fclose($handle);
			}

			if (!@rename($tmp, $this->path)) {
				throw new RuntimeException('Unable to atomically publish operation-history state.');
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
				|| !is_array($decoded['entries'] ?? null)
				|| count($decoded['entries']) > self::MAX_ENTRIES) {
			throw new RuntimeException('Operation-history file schema is invalid.');
		}

		$entries = [];
		foreach ($decoded['entries'] as $entry) {
			$entries[] = $this->normalizeEntry($entry);
		}

		return [
			'schema_version' => self::SCHEMA_VERSION,
			'entries' => $entries
		];
	}

	private function normalizeEntry($entry): array {
		if (!is_array($entry)) {
			throw new RuntimeException('Operation-history entry is invalid.');
		}

		$id = strtolower(trim((string) ($entry['id'] ?? '')));
		$createdAt = trim((string) ($entry['created_at'] ?? ''));
		$operation = trim((string) ($entry['operation'] ?? ''));
		$subject = self::sanitize((string) ($entry['subject'] ?? ''), 160);
		$status = self::sanitize((string) ($entry['status'] ?? ''), 64);
		$actorUserId = trim((string) ($entry['actor_userid'] ?? ''));
		$detail = self::sanitize((string) ($entry['detail'] ?? ''), 512);
		$writePerformed = $entry['write_performed'] ?? null;

		if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1
				|| $createdAt === ''
				|| strtotime($createdAt) === false
				|| preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $operation) !== 1
				|| $subject === ''
				|| $status === ''
				|| ($actorUserId !== '' && !ctype_digit($actorUserId))
				|| !($writePerformed === null || is_bool($writePerformed))) {
			throw new RuntimeException('Operation-history entry fields are invalid.');
		}

		return [
			'id' => $id,
			'created_at' => $createdAt,
			'operation' => $operation,
			'subject' => $subject,
			'status' => $status,
			'write_performed' => $writePerformed,
			'actor_userid' => $actorUserId,
			'detail' => $detail
		];
	}

	private function assertReadableDirectory(string $directory): void {
		if (is_link($directory) || !is_dir($directory) || !is_readable($directory)) {
			throw new RuntimeException('Operation-history runtime directory is unavailable or unreadable.');
		}
	}

	private function assertWritableDirectory(string $directory): void {
		if (is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
			throw new RuntimeException('Operation-history runtime directory is unavailable or not writable.');
		}
		if (DIRECTORY_SEPARATOR === '/') {
			$permissions = @fileperms($directory);
			if ($permissions === false || (($permissions & 0777) !== 0700)) {
				throw new RuntimeException('Operation-history runtime directory permissions are not private.');
			}
		}
	}

	private static function emptyState(): array {
		return ['schema_version' => self::SCHEMA_VERSION, 'entries' => []];
	}

	private static function sanitize(string $value, int $maxLength): string {
		$value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
		$value = is_string($value) ? trim(preg_replace('/\s+/', ' ', $value) ?? '') : '';
		return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
	}
}
