<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Exception\LockContendedException;
use Modules\ZabbixTemplateUpdateManager\Exception\RuntimeStorageException;
use RuntimeException;

require_once dirname(__DIR__).'/Exception/LockContendedException.php';
require_once dirname(__DIR__).'/Exception/RuntimeStorageException.php';

final class TemplateOperationLockService {

	private const LOCK_FILE = 'configuration-write.lock';
	private const DEFAULT_LOCK_DIR = '/var/lib/zabbix-template-update-manager/locks';

	private string $lockDir;

	public function __construct(?string $lockDir = null) {
		$configured = trim((string) getenv('ZTUM_LOCK_DIR'));
		$this->lockDir = $lockDir ?? ($configured !== ''
			? $configured
			: self::DEFAULT_LOCK_DIR);
		if ($this->lockDir === '' || $this->lockDir[0] !== '/'
				|| strpos($this->lockDir, "\0") !== false
				|| preg_match('#(?:^|/)\\.\\.?(/|$)#', $this->lockDir)) {
			throw new RuntimeStorageException('The controlled-operation lock directory must be a safe absolute path.');
		}
	}

	public static function defaultDirectory(): string {
		return self::DEFAULT_LOCK_DIR;
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
			throw new RuntimeStorageException('The controlled-operation lock path is unsafe.');
		}

		// Suppress the PHP warning because the exception below is the bounded UI/log diagnostic.
		$handle = @fopen($lockPath, 'c+');
		if (!is_resource($handle)) {
			throw new RuntimeStorageException('Unable to open the controlled-operation lock file.');
		}

		try {
			if (DIRECTORY_SEPARATOR === '/') {
				if (!chmod($lockPath, 0600)) {
					throw new RuntimeStorageException('Unable to secure the controlled-operation lock file permissions.');
				}

				$permissions = fileperms($lockPath);
				if ($permissions === false || (($permissions & 0777) !== 0600)) {
					throw new RuntimeStorageException('The controlled-operation lock file permissions are not private.');
				}
			}

			if (!flock($handle, LOCK_EX | LOCK_NB)) {
				throw new LockContendedException();
			}

			$metadata = json_encode([
				'pid' => getmypid(),
				'operation' => $operation,
				'subject' => $subject,
				'acquired_at' => gmdate('c')
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			if (!is_string($metadata)) {
				throw new RuntimeStorageException('Unable to encode controlled-operation lock metadata.');
			}

			if (!ftruncate($handle, 0) || !rewind($handle)) {
				throw new RuntimeStorageException('Unable to prepare the controlled-operation lock metadata file.');
			}

			$payload = $metadata."\n";
			$written = fwrite($handle, $payload);
			if ($written === false || $written !== strlen($payload) || !fflush($handle)) {
				throw new RuntimeStorageException('Unable to persist controlled-operation lock metadata.');
			}

			return $callback();
		}
		finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	private function ensurePrivateDirectory(): void {
		if (is_link($this->lockDir)) {
			throw new RuntimeStorageException('The controlled-operation lock directory is unsafe.');
		}

		// mkdir races are expected; only the final state is authoritative.
		if (!is_dir($this->lockDir)
				&& !@mkdir($this->lockDir, 0700, true)
				&& !is_dir($this->lockDir)) {
			throw new RuntimeStorageException('Unable to create the controlled-operation lock directory.');
		}

		if (DIRECTORY_SEPARATOR === '/') {
			if (!chmod($this->lockDir, 0700)) {
				throw new RuntimeStorageException('Unable to secure the controlled-operation lock directory permissions.');
			}

			$permissions = fileperms($this->lockDir);
			if ($permissions === false || (($permissions & 0777) !== 0700)) {
				throw new RuntimeStorageException('The controlled-operation lock directory permissions are not private.');
			}
		}

		if (!is_writable($this->lockDir)) {
			throw new RuntimeStorageException('The controlled-operation lock directory is not writable.');
		}
	}
}
