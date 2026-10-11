<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use RuntimeException;

/**
 * Local cache for successfully resolved immutable historical baselines.
 *
 * Cache identity includes the current upstream commit, canonical template path,
 * stable template UUID and installed vendor metadata. A new upstream commit or
 * different installed vendor version therefore selects a different cache key.
 */
final class HistoricalBaselineCacheRepository {

	private const SCHEMA_VERSION = 2;
	private const MAX_CACHE_BYTES = 12582912;

	private string $cacheDir;

	public function __construct(?string $cacheDir = null) {
		$this->cacheDir = $cacheDir ?? '/var/lib/zabbix-template-update-manager/cache/historical-baselines';
	}

	public function load(
		string $path,
		string $currentCommit,
		string $uuid,
		string $targetVendorVersion,
		string $expectedVendorName = 'Zabbix'
	): ?array {
		$key = $this->validatedKey($path, $currentCommit, $uuid, $targetVendorVersion, $expectedVendorName);
		$file = $this->cacheFile($key);

		if (!$this->cacheDirectoryIsPrivate() || is_link($file) || !is_file($file)
				|| @fileowner($file) !== posix_geteuid()
				|| (@fileperms($file) & 0777) !== 0600) {
			return null;
		}

		$size = @filesize($file);
		if ($size === false || $size <= 0 || $size > self::MAX_CACHE_BYTES) {
			return null;
		}

		$content = @file_get_contents($file);
		if (!is_string($content) || $content === '' || strlen($content) > self::MAX_CACHE_BYTES) {
			return null;
		}

		$data = json_decode($content, true);
		if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
			return null;
		}

		if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION
				|| ($data['cache_key'] ?? null) !== $key['hash']) {
			return null;
		}

		foreach (['path', 'current_commit', 'uuid', 'target_vendor_version', 'expected_vendor_name'] as $field) {
			if (($data[$field] ?? null) !== $key[$field]) {
				return null;
			}
		}

		$baseline = $data['baseline'] ?? null;
		if (!is_array($baseline) || ($baseline['status'] ?? null) !== 'found') {
			return null;
		}

		$commit = strtolower(trim((string) ($baseline['commit'] ?? '')));
		$source = $baseline['source'] ?? null;
		$sourceHash = strtolower(trim((string) ($data['source_sha256'] ?? '')));

		if (!preg_match('/^[a-f0-9]{40}$/', $commit)
				|| ($baseline['path'] ?? null) !== $key['path']
				|| ($baseline['vendor_version'] ?? null) !== $key['target_vendor_version']
				|| !is_string($source)
				|| $source === ''
				|| strlen($source) > self::MAX_CACHE_BYTES
				|| !preg_match('/^[a-f0-9]{64}$/', $sourceHash)
				|| !hash_equals($sourceHash, hash('sha256', $source))) {
			return null;
		}

		return [
			'status' => 'found',
			'commit' => $commit,
			'path' => $key['path'],
			'vendor_version' => $key['target_vendor_version'],
			'commits_examined' => max(0, (int) ($baseline['commits_examined'] ?? 0)),
			'history_truncated' => (bool) ($baseline['history_truncated'] ?? false),
			'candidate_count' => max(0, (int) ($baseline['candidate_count'] ?? 0)),
			'distinct_candidate_count' => max(0, (int) ($baseline['distinct_candidate_count'] ?? 0)),
			'exact_match_count' => max(0, (int) ($baseline['exact_match_count'] ?? 0)),
			'selection' => (string) ($baseline['selection'] ?? 'cached_authoritative'),
			'semantic_distance' => isset($baseline['semantic_distance']) ? max(0, (int) $baseline['semantic_distance']) : null,
			'source' => $source
		];
	}

	public function store(
		string $path,
		string $currentCommit,
		string $uuid,
		string $targetVendorVersion,
		string $expectedVendorName,
		array $baseline
	): bool {
		$key = $this->validatedKey($path, $currentCommit, $uuid, $targetVendorVersion, $expectedVendorName);

		if (($baseline['status'] ?? null) !== 'found') {
			return false;
		}

		$commit = strtolower(trim((string) ($baseline['commit'] ?? '')));
		$source = $baseline['source'] ?? null;
		if (!preg_match('/^[a-f0-9]{40}$/', $commit)
				|| ($baseline['path'] ?? null) !== $key['path']
				|| ($baseline['vendor_version'] ?? null) !== $key['target_vendor_version']
				|| !is_string($source)
				|| $source === ''
				|| strlen($source) > self::MAX_CACHE_BYTES) {
			throw new RuntimeException('The historical baseline is invalid and cannot be cached.');
		}

		$record = [
			'schema_version' => self::SCHEMA_VERSION,
			'cache_key' => $key['hash'],
			'path' => $key['path'],
			'current_commit' => $key['current_commit'],
			'uuid' => $key['uuid'],
			'target_vendor_version' => $key['target_vendor_version'],
			'expected_vendor_name' => $key['expected_vendor_name'],
			'source_sha256' => hash('sha256', $source),
			'cached_at' => time(),
			'baseline' => [
				'status' => 'found',
				'commit' => $commit,
				'path' => $key['path'],
				'vendor_version' => $key['target_vendor_version'],
				'commits_examined' => max(0, (int) ($baseline['commits_examined'] ?? 0)),
				'history_truncated' => (bool) ($baseline['history_truncated'] ?? false),
				'candidate_count' => max(0, (int) ($baseline['candidate_count'] ?? 0)),
				'distinct_candidate_count' => max(0, (int) ($baseline['distinct_candidate_count'] ?? 0)),
				'exact_match_count' => max(0, (int) ($baseline['exact_match_count'] ?? 0)),
				'selection' => (string) ($baseline['selection'] ?? ''),
				'semantic_distance' => isset($baseline['semantic_distance']) ? max(0, (int) $baseline['semantic_distance']) : null,
				'source' => $source
			]
		];

		$json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json) || strlen($json) > self::MAX_CACHE_BYTES) {
			throw new RuntimeException('The historical baseline cache record exceeds the size limit.');
		}

		$parent = dirname($this->cacheDir);
		if (!$this->privateDirectory($parent)) {
			return false;
		}
		if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0700)) {
			return false;
		}
		if (!$this->cacheDirectoryIsPrivate()) {
			return false;
		}

		$file = $this->cacheFile($key);
		if (is_link($file)) {
			return false;
		}
		$tmp = $file.'.tmp-'.bin2hex(random_bytes(12));
		$oldUmask = umask(0077);
		try {
			$written = @file_put_contents($tmp, $json, LOCK_EX);
		}
		finally {
			umask($oldUmask);
		}
		if ($written === false) {
			return false;
		}

		@chmod($tmp, 0600);
		if (!@rename($tmp, $file)) {
			@unlink($tmp);
			return false;
		}

		return true;
	}

	private function privateDirectory(string $directory): bool {
		if (!function_exists('posix_geteuid') || is_link($directory) || !is_dir($directory)) {
			return false;
		}
		$mode = @fileperms($directory);
		return $mode !== false && ($mode & 0777) === 0700
			&& @fileowner($directory) === posix_geteuid();
	}

	private function cacheDirectoryIsPrivate(): bool {
		return $this->privateDirectory($this->cacheDir);
	}

	private function cacheFile(array $key): string {
		return $this->cacheDir.DIRECTORY_SEPARATOR.'baseline-'.$key['hash'].'.json';
	}

	private function validatedKey(
		string $path,
		string $currentCommit,
		string $uuid,
		string $targetVendorVersion,
		string $expectedVendorName
	): array {
		$currentCommit = strtolower(trim($currentCommit));
		$uuid = strtolower(str_replace('-', '', trim($uuid)));
		$targetVendorVersion = trim($targetVendorVersion);
		$expectedVendorName = trim($expectedVendorName);

		if (!UpstreamIndexRepository::isValidTemplatePath($path)) {
			throw new RuntimeException('The historical baseline cache path is invalid.');
		}
		if (!preg_match('/^[a-f0-9]{40}$/', $currentCommit)) {
			throw new RuntimeException('The historical baseline cache current commit is invalid.');
		}
		if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			throw new RuntimeException('The historical baseline cache template UUID is invalid.');
		}
		if ($targetVendorVersion === '' || strlen($targetVendorVersion) > 128) {
			throw new RuntimeException('The historical baseline cache vendor version is invalid.');
		}
		if (strlen($expectedVendorName) > 128) {
			throw new RuntimeException('The historical baseline cache vendor name is invalid.');
		}

		$identity = [
			'path' => $path,
			'current_commit' => $currentCommit,
			'uuid' => $uuid,
			'target_vendor_version' => $targetVendorVersion,
			'expected_vendor_name' => $expectedVendorName
		];
		$encoded = json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($encoded)) {
			throw new RuntimeException('Unable to encode the historical baseline cache identity.');
		}

		$identity['hash'] = hash('sha256', $encoded);
		return $identity;
	}
}
