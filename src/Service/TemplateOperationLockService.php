<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

final class TemplateOperationLockService {

	private const LOCK_FILE = 'configuration-write.lock';

	private string $lockDir;

	public function __construct(?string $lockDir = null) {
		$configured = trim((string) getenv('ZTUM_LOCK_DIR'));
		$this->lockDir = $lockDir ?? ($configured !== ''
			? $configured
			: '/var/lib/zabbix-template-update-manager/locks');
	}

	/**
	 * Serialize every ZTUM configuration-write workflow on this frontend host.
	 *
	 * The lock is acquired before the fresh controlled-operation preflight and is
	 * held through post-write validation. It is intentionally global rather than
	 * per-template so update, installation and rollback cannot race each other.
	 */
	public function run(string $operation, string $subject, callable $callback) {
		$operation = trim($operation);
		$subject = trim($subject);

		if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $operation) !== 1) {
			throw new RuntimeException('The controlled-operation lock name is invalid.');
		}
		if ($subject === '' || strlen($subject) > 160 || preg_match('/[\r\n\0]/', $subject)) {
			throw new RuntimeException('The controlled-operation lock subject is invalid.');
		}

		$this->ensurePrivateDirectory();

		$lockPath = $this->lockDir.DIRECTORY_SEPARATOR.self::LOCK_FILE;
		if (is_link($lockPath)) {
			throw new RuntimeException('The controlled-operation lock path is unsafe.');
		}

		$handle = @fopen($lockPath, 'c+');
		if (!is_resource($handle)) {
			throw new RuntimeException('Unable to open the controlled-operation lock file.');
		}

		try {
			@chmod($lockPath, 0600);
			if (!@flock($handle, LOCK_EX | LOCK_NB)) {
				throw new RuntimeException(
					'Another Template Update Manager configuration operation is already in progress.'
				);
			}

			$metadata = json_encode([
				'pid' => getmypid(),
				'operation' => $operation,
				'subject' => $subject,
				'acquired_at' => gmdate('c')
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (is_string($metadata)) {
				@ftruncate($handle, 0);
				@rewind($handle);
				@fwrite($handle, $metadata."\n");
				@fflush($handle);
			}

			return $callback();
		}
		finally {
			@flock($handle, LOCK_UN);
			@fclose($handle);
		}
	}

	private function ensurePrivateDirectory(): void {
		if (is_link($this->lockDir)) {
			throw new RuntimeException('The controlled-operation lock directory is unsafe.');
		}

		if (!is_dir($this->lockDir)
				&& !@mkdir($this->lockDir, 0700, true)
				&& !is_dir($this->lockDir)) {
			throw new RuntimeException('Unable to create the controlled-operation lock directory.');
		}

		if (DIRECTORY_SEPARATOR === '/') {
			@chmod($this->lockDir, 0700);
			$permissions = @fileperms($this->lockDir);
			if ($permissions === false || (($permissions & 0777) !== 0700)) {
				throw new RuntimeException('The controlled-operation lock directory permissions are not private.');
			}
		}

		if (!is_writable($this->lockDir)) {
			throw new RuntimeException('The controlled-operation lock directory is not writable.');
		}
	}
}
