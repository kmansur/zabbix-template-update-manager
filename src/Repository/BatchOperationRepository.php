<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use JsonException;
use RuntimeException;

final class BatchOperationRepository {

	private const SCHEMA_VERSION = 1;
	private const MAX_ENTRIES = 500;
	private const MAX_BYTES = 4194304;
	private const DEFAULT_DIR = '/var/lib/zabbix-template-update-manager/batches';

	private string $dir;

	public function __construct(?string $dir = null) {
		$configured = trim((string) getenv('ZTUM_BATCH_DIR'));
		$this->dir = $dir ?? ($configured !== '' ? rtrim($configured, DIRECTORY_SEPARATOR) : self::DEFAULT_DIR);
	}

	public static function defaultDirectory(): string {
		return self::DEFAULT_DIR;
	}

	public function create(string $type, array $entries, string $actorUserId = ''): array {
		if (!in_array($type, ['update', 'install'], true)) {
			throw new RuntimeException('Unsupported batch operation type.');
		}
		if ($entries === [] || count($entries) > self::MAX_ENTRIES) {
			throw new RuntimeException('Batch operation entry count is invalid.');
		}

		$this->ensureDirectory();
		$id = bin2hex(random_bytes(16));
		$normalized = [];
		$seen = [];

		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				throw new RuntimeException('Batch operation entry is invalid.');
			}
			$subject = trim((string) ($entry['subject'] ?? ''));
			$evidence = strtolower(trim((string) ($entry['evidence_sha256'] ?? '')));
			if ($subject === '' || strlen($subject) > 180 || isset($seen[$subject])
					|| preg_match('/^[a-f0-9]{64}$/', $evidence) !== 1) {
				throw new RuntimeException('Batch operation entry identity or evidence is invalid.');
			}
			$seen[$subject] = true;
			$normalized[] = [
				'subject' => $subject,
				'evidence_sha256' => $evidence,
				'manual_override' => !empty($entry['manual_override']),
				'state' => 'pending',
				'started_at' => null,
				'completed_at' => null,
				'result_status' => null,
				'error_code' => null
			];
		}

		$state = [
			'schema_version' => self::SCHEMA_VERSION,
			'id' => $id,
			'type' => $type,
			'created_at' => gmdate('c'),
			'updated_at' => gmdate('c'),
			'actor_userid' => ctype_digit($actorUserId) ? $actorUserId : '',
			'status' => 'pending',
			'entries' => $normalized
		];

		$this->writeAtomic($id, $state, true);
		return $state;
	}

	public function load(string $id): array {
		$id = $this->normalizeId($id);
		$path = $this->path($id);
		if (is_link($path) || !is_file($path) || !is_readable($path)) {
			throw new RuntimeException('Batch operation does not exist or is unavailable.');
		}
		$size = filesize($path);
		$content = file_get_contents($path);
		if ($size === false || $size < 2 || $size > self::MAX_BYTES || !is_string($content)
				|| strlen($content) > self::MAX_BYTES) {
			throw new RuntimeException('Batch operation state is unreadable or exceeds the safe size.');
		}
		try {
			$data = json_decode($content, true, 128, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('Batch operation state contains invalid JSON.', 0, $exception);
		}
		return $this->validate($data, $id);
	}

	public function begin(string $id, string $subject, string $evidence, string $expectedType, string $actorUserId): array {
		return $this->mutate($id, function (array $state) use ($subject, $evidence, $expectedType, $actorUserId): array {
			if (($state['type'] ?? null) !== $expectedType) {
				throw new RuntimeException('Batch operation type does not match the requested write path.');
			}
			$owner = trim((string) ($state['actor_userid'] ?? ''));
			if ($owner !== '' && (!ctype_digit($actorUserId) || !hash_equals($owner, $actorUserId))) {
				throw new RuntimeException('Batch operation belongs to a different operator.');
			}
			$index = $this->findEntry($state, $subject);
			$entry = $state['entries'][$index];

			foreach (array_slice($state['entries'], 0, $index) as $previous) {
				if (($previous['state'] ?? null) !== 'succeeded') {
					throw new RuntimeException('A previous batch entry has not completed successfully.');
				}
			}

			if (($entry['state'] ?? null) !== 'pending') {
				throw new RuntimeException('Batch entry is not pending and cannot be started again.');
			}
			if (!hash_equals((string) $entry['evidence_sha256'], strtolower(trim($evidence)))) {
				throw new RuntimeException('Batch entry evidence does not match the persisted plan.');
			}

			$state['entries'][$index]['state'] = 'running';
			$state['entries'][$index]['started_at'] = gmdate('c');
			$state['status'] = 'running';
			return $state;
		});
	}

	public function finish(string $id, string $subject, string $terminalState, ?string $resultStatus, ?string $errorCode): array {
		if (!in_array($terminalState, ['succeeded', 'failed', 'uncertain'], true)) {
			throw new RuntimeException('Batch terminal state is invalid.');
		}
		return $this->mutate($id, function (array $state) use ($subject, $terminalState, $resultStatus, $errorCode): array {
			$index = $this->findEntry($state, $subject);
			if (($state['entries'][$index]['state'] ?? null) !== 'running') {
				throw new RuntimeException('Batch entry is not running.');
			}

			$state['entries'][$index]['state'] = $terminalState;
			$state['entries'][$index]['completed_at'] = gmdate('c');
			$state['entries'][$index]['result_status'] = $resultStatus;
			$state['entries'][$index]['error_code'] = $errorCode;

			if ($terminalState === 'succeeded') {
				$pending = false;
				foreach ($state['entries'] as $entry) {
					if (($entry['state'] ?? null) === 'pending') {
						$pending = true;
						break;
					}
				}
				$state['status'] = $pending ? 'running' : 'completed';
			}
			else {
				$state['status'] = $terminalState === 'uncertain' ? 'uncertain' : 'failed';
			}
			return $state;
		});
	}

	public function markStaleRunningUncertain(string $id, int $staleSeconds = 900): array {
		if ($staleSeconds < 60 || $staleSeconds > 86400) {
			throw new RuntimeException('Batch stale threshold is invalid.');
		}

		return $this->mutate($id, function (array $state) use ($staleSeconds): array {
			$now = time();
			$changed = false;
			foreach ($state['entries'] as &$entry) {
				if (($entry['state'] ?? null) !== 'running') {
					continue;
				}
				$started = strtotime((string) ($entry['started_at'] ?? ''));
				if ($started !== false && ($now - $started) >= $staleSeconds) {
					$entry['state'] = 'uncertain';
					$entry['completed_at'] = gmdate('c');
					$entry['result_status'] = 'interrupted';
					$entry['error_code'] = 'interrupted_execution';
					$changed = true;
				}
			}
			unset($entry);
			if ($changed) {
				$state['status'] = 'uncertain';
			}
			return $state;
		});
	}

	private function mutate(string $id, callable $callback): array {
		$id = $this->normalizeId($id);
		$this->ensureDirectory();
		$lockPath = $this->path($id).'.lock';
		if (is_link($lockPath)) {
			throw new RuntimeException('Batch operation lock path is unsafe.');
		}
		$lock = fopen($lockPath, 'c+');
		if (!is_resource($lock)) {
			throw new RuntimeException('Unable to open batch operation lock.');
		}
		try {
			if (DIRECTORY_SEPARATOR === '/' && (!chmod($lockPath, 0600) || ((fileperms($lockPath) & 0777) !== 0600))) {
				throw new RuntimeException('Unable to secure batch operation lock.');
			}
			if (!flock($lock, LOCK_EX)) {
				throw new RuntimeException('Unable to lock batch operation state.');
			}
			$state = $this->load($id);
			$updated = $callback($state);
			$updated['updated_at'] = gmdate('c');
			$this->writeAtomic($id, $updated, false);
			return $updated;
		}
		finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function writeAtomic(string $id, array $state, bool $mustNotExist): void {
		$path = $this->path($id);
		if ($mustNotExist && file_exists($path)) {
			throw new RuntimeException('Batch operation ID collision.');
		}
		$encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
		if (strlen($encoded) > self::MAX_BYTES) {
			throw new RuntimeException('Batch operation state exceeds the safe size.');
		}

		$tmp = tempnam($this->dir, '.ztum-batch-');
		if (!is_string($tmp)) {
			throw new RuntimeException('Unable to create temporary batch operation state.');
		}
		try {
			if (DIRECTORY_SEPARATOR === '/' && !chmod($tmp, 0600)) {
				throw new RuntimeException('Unable to secure temporary batch operation state.');
			}
			$handle = fopen($tmp, 'wb');
			if (!is_resource($handle)) {
				throw new RuntimeException('Unable to open temporary batch operation state.');
			}
			try {
				$written = fwrite($handle, $encoded);
				if ($written === false || $written !== strlen($encoded) || !fflush($handle)) {
					throw new RuntimeException('Unable to persist complete batch operation state.');
				}
				if (function_exists('fsync') && !fsync($handle)) {
					throw new RuntimeException('Unable to synchronize batch operation state.');
				}
			}
			finally {
				fclose($handle);
			}
			if (!rename($tmp, $path)) {
				throw new RuntimeException('Unable to atomically publish batch operation state.');
			}
			if (DIRECTORY_SEPARATOR === '/' && !chmod($path, 0600)) {
				throw new RuntimeException('Unable to secure batch operation state.');
			}
		}
		finally {
			if (file_exists($tmp)) {
				unlink($tmp);
			}
		}
	}

	private function validate(mixed $state, string $id): array {
		if (!is_array($state) || ($state['schema_version'] ?? null) !== self::SCHEMA_VERSION
				|| ($state['id'] ?? null) !== $id
				|| !in_array($state['type'] ?? null, ['update', 'install'], true)
				|| !is_array($state['entries'] ?? null)
				|| $state['entries'] === [] || count($state['entries']) > self::MAX_ENTRIES) {
			throw new RuntimeException('Batch operation state schema is invalid.');
		}
		return $state;
	}

	private function findEntry(array $state, string $subject): int {
		foreach ($state['entries'] as $index => $entry) {
			if (is_array($entry) && ($entry['subject'] ?? null) === $subject) {
				return (int) $index;
			}
		}
		throw new RuntimeException('Batch subject is not present in the persisted plan.');
	}

	private function ensureDirectory(): void {
		if (is_link($this->dir)) {
			throw new RuntimeException('Batch operation directory is unsafe.');
		}
		if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
			throw new RuntimeException('Unable to create batch operation directory.');
		}
		if (DIRECTORY_SEPARATOR === '/' && (!chmod($this->dir, 0700) || ((fileperms($this->dir) & 0777) !== 0700))) {
			throw new RuntimeException('Batch operation directory permissions are not private.');
		}
		if (!is_writable($this->dir)) {
			throw new RuntimeException('Batch operation directory is not writable.');
		}
	}

	private function path(string $id): string {
		return $this->dir.DIRECTORY_SEPARATOR.$id.'.json';
	}

	private function normalizeId(string $id): string {
		$id = strtolower(trim($id));
		if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
			throw new RuntimeException('Batch operation ID is invalid.');
		}
		return $id;
	}
}
