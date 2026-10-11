<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use JsonException;
use RuntimeException;

final class OfflineBundleRepository {

	private const SCHEMA_VERSION = 1;
	private const MAX_MANIFEST_BYTES = 8388608;
	private const MAX_INDEX_BYTES = 5242880;
	private const MAX_SOURCE_BYTES = 10485760;
	private const MAX_HISTORY_BYTES = 2097152;
	private const SIGNATURE_SCHEMA = 'ztum-offline-manifest-signature-v1';

	private ?string $rootDir;
	private bool $offlineOnly;
	private ?array $manifest = null;

	public function __construct(?string $rootDir = null, ?bool $offlineOnly = null) {
		$configuredRoot = trim((string) getenv('ZTUM_OFFLINE_BUNDLE_DIR'));
		$this->rootDir = $rootDir !== null
			? rtrim($rootDir, DIRECTORY_SEPARATOR)
			: ($configuredRoot !== '' ? rtrim($configuredRoot, DIRECTORY_SEPARATOR) : null);

		$this->offlineOnly = $offlineOnly ?? self::envFlag('ZTUM_OFFLINE_ONLY');
	}

	public function isConfigured(): bool {
		return $this->rootDir !== null && $this->rootDir !== '';
	}

	public function isOfflineOnly(): bool {
		return $this->offlineOnly;
	}

	public function readIndex(string $line): ?string {
		if (preg_match('/^\d+\.\d+$/', $line) !== 1) {
			throw new RuntimeException('The offline upstream index line is invalid.');
		}
		return $this->readVerified('indexes/'.$line.'.json', self::MAX_INDEX_BYTES);
	}

	public function readSource(string $commit, string $path): ?string {
		$commit = strtolower(trim($commit));
		if (preg_match('/^[a-f0-9]{40}$/', $commit) !== 1) {
			throw new RuntimeException('The offline upstream source commit is invalid.');
		}
		if (!UpstreamIndexRepository::isValidTemplatePath($path)) {
			throw new RuntimeException('The offline upstream source path is invalid.');
		}

		return $this->readVerified('sources/'.$commit.'/'.$path, self::MAX_SOURCE_BYTES);
	}

	public function readHistory(string $path, string $until, int $maxCommits): ?array {
		$until = strtolower(trim($until));
		if (!UpstreamIndexRepository::isValidTemplatePath($path)
				|| preg_match('/^[a-f0-9]{40}$/', $until) !== 1
				|| $maxCommits < 1 || $maxCommits > 75) {
			throw new RuntimeException('The offline upstream history request is invalid.');
		}

		$relative = 'history/'.$until.'/'.hash('sha256', $path).'.json';
		$content = $this->readVerified($relative, self::MAX_HISTORY_BYTES);
		if ($content === null) {
			return null;
		}

		try {
			$data = json_decode($content, true, 128, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The offline upstream history record is not valid JSON.', 0, $exception);
		}

		if (!is_array($data)
				|| ($data['schema_version'] ?? null) !== self::SCHEMA_VERSION
				|| ($data['path'] ?? null) !== $path
				|| strtolower((string) ($data['until'] ?? '')) !== $until
				|| !is_bool($data['truncated'] ?? null)
				|| !is_array($data['commits'] ?? null)) {
			throw new RuntimeException('The offline upstream history record has an invalid structure.');
		}

		$commits = [];
		foreach ($data['commits'] as $record) {
			if (!is_array($record)) {
				throw new RuntimeException('The offline upstream history contains an invalid commit record.');
			}
			$id = strtolower(trim((string) ($record['id'] ?? '')));
			if (preg_match('/^[a-f0-9]{40}$/', $id) !== 1) {
				throw new RuntimeException('The offline upstream history contains an invalid commit ID.');
			}
			$commits[] = [
				'id' => $id,
				'message' => is_string($record['message'] ?? null) ? $record['message'] : ''
			];
		}

		$truncated = (bool) $data['truncated'] || count($commits) > $maxCommits;
		$commits = array_slice($commits, 0, $maxCommits);

		return [
			'commits' => $commits,
			'truncated' => $truncated,
			'limit' => $maxCommits
		];
	}

	public function readPreviousPath(string $commit, string $path): ?string {
		$commit = strtolower(trim($commit));
		if (preg_match('/^[a-f0-9]{40}$/', $commit) !== 1 || !UpstreamIndexRepository::isValidTemplatePath($path)) {
			throw new RuntimeException('The offline upstream rename request is invalid.');
		}
		$relative = 'renames/'.$commit.'/'.hash('sha256', $path).'.json';
		$content = $this->readVerified($relative, self::MAX_HISTORY_BYTES);
		if ($content === null) {
			return null;
		}
		try {
			$data = json_decode($content, true, 128, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The offline upstream rename record is not valid JSON.', 0, $exception);
		}
		$previous = is_array($data) ? (string) ($data['previous_path'] ?? '') : '';
		if (!is_array($data) || ($data['schema_version'] ?? null) !== self::SCHEMA_VERSION
				|| strtolower((string) ($data['commit'] ?? '')) !== $commit
				|| ($data['path'] ?? null) !== $path
				|| !UpstreamIndexRepository::isValidTemplatePath($previous)) {
			throw new RuntimeException('The offline upstream rename record has an invalid structure.');
		}
		return $previous;
	}

	public function describe(): array {
		return [
			'configured' => $this->isConfigured(),
			'offline_only' => $this->offlineOnly,
			'root' => $this->isConfigured() ? $this->rootDir : null
		];
	}

	private function readVerified(string $relativePath, int $maxBytes): ?string {
		if (!$this->isConfigured()) {
			return null;
		}

		$manifest = $this->manifest();
		$files = $manifest['files'];
		if (!array_key_exists($relativePath, $files)) {
			return null;
		}

		$path = $this->rootDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
		if (is_link($path) || !is_file($path) || !is_readable($path)) {
			throw new RuntimeException('An offline bundle file is missing, unreadable or unsafe: '.$relativePath);
		}

		$size = @filesize($path);
		if ($size === false || $size < 1 || $size > $maxBytes) {
			throw new RuntimeException('An offline bundle file exceeds its allowed size: '.$relativePath);
		}

		$content = @file_get_contents($path);
		if (!is_string($content) || $content === '' || strlen($content) > $maxBytes) {
			throw new RuntimeException('Unable to read an offline bundle file safely: '.$relativePath);
		}

		$actual = hash('sha256', $content);
		if (!hash_equals($files[$relativePath], $actual)) {
			throw new RuntimeException('Offline bundle file integrity verification failed: '.$relativePath);
		}

		return $content;
	}

	private function manifest(): array {
		if ($this->manifest !== null) {
			return $this->manifest;
		}
		if (!$this->isConfigured()) {
			throw new RuntimeException('Offline bundle is not configured.');
		}
		if (is_link($this->rootDir) || !is_dir($this->rootDir) || !is_readable($this->rootDir)) {
			throw new RuntimeException('The configured offline bundle directory is missing, unreadable or unsafe.');
		}

		$manifestPath = $this->rootDir.DIRECTORY_SEPARATOR.'manifest.json';
		if (is_link($manifestPath) || !is_file($manifestPath) || !is_readable($manifestPath)) {
			throw new RuntimeException('The configured offline bundle manifest is missing or unsafe.');
		}

		$size = @filesize($manifestPath);
		$content = @file_get_contents($manifestPath);
		if ($size === false || $size < 2 || $size > self::MAX_MANIFEST_BYTES
				|| !is_string($content) || strlen($content) > self::MAX_MANIFEST_BYTES) {
			throw new RuntimeException('The offline bundle manifest is unreadable or exceeds the size limit.');
		}

		try {
			$data = json_decode($content, true, 128, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The offline bundle manifest is not valid JSON.', 0, $exception);
		}

		if (!is_array($data)
				|| ($data['schema_version'] ?? null) !== self::SCHEMA_VERSION
				|| !is_array($data['files'] ?? null)
				|| $data['files'] === []) {
			throw new RuntimeException('The offline bundle manifest has an invalid structure.');
		}

		$this->verifyManifestSignature($data);

		$files = [];
		foreach ($data['files'] as $relative => $sha256) {
			if (!is_string($relative) || !self::isSafeRelativePath($relative)
					|| !is_string($sha256) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
				throw new RuntimeException('The offline bundle manifest contains an invalid file record.');
			}
			$files[$relative] = $sha256;
		}

		$data['files'] = $files;
		$this->manifest = $data;
		return $data;
	}

	private function verifyManifestSignature(array $manifest): void {
		$signature = $manifest['signature'] ?? null;
		$keyId = trim((string) getenv('ZTUM_OFFLINE_BUNDLE_KEY_ID'));
		$publicKey = trim((string) getenv('ZTUM_OFFLINE_BUNDLE_PUBLIC_KEY'));
		if (!is_array($signature)
				|| ($signature['schema'] ?? null) !== self::SIGNATURE_SCHEMA
				|| ($signature['key_id'] ?? null) !== $keyId
				|| preg_match('/^[A-Za-z0-9._-]{1,128}$/', $keyId) !== 1
				|| !is_string($signature['signature'] ?? null)
				|| $publicKey === ''
				|| !function_exists('sodium_crypto_sign_verify_detached')) {
			throw new RuntimeException('The offline bundle manifest has no usable trusted signature.');
		}

		$decodedSignature = base64_decode($signature['signature'], true);
		$decodedKey = base64_decode($publicKey, true);
		if (!is_string($decodedSignature) || strlen($decodedSignature) !== SODIUM_CRYPTO_SIGN_BYTES
				|| !is_string($decodedKey) || strlen($decodedKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
			throw new RuntimeException('The offline bundle manifest signature or trusted key is invalid.');
		}

		$unsigned = $manifest;
		unset($unsigned['signature']);
		$this->sortManifestKeys($unsigned);
		try {
			$canonical = json_encode($unsigned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					| JSON_THROW_ON_ERROR)."\n";
			if (!sodium_crypto_sign_verify_detached($decodedSignature, $canonical, $decodedKey)) {
				throw new RuntimeException('The offline bundle manifest signature is invalid.');
			}
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The offline bundle manifest cannot be canonicalized.', 0, $exception);
		}
	}

	private function sortManifestKeys(array &$value): void {
		ksort($value, SORT_STRING);
		foreach ($value as &$child) {
			if (is_array($child) && array_keys($child) !== range(0, count($child) - 1)) {
				$this->sortManifestKeys($child);
			}
		}
		unset($child);
	}

	private static function isSafeRelativePath(string $path): bool {
		if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
			return false;
		}
		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				return false;
			}
		}
		return true;
	}

	private static function envFlag(string $name): bool {
		$value = strtolower(trim((string) getenv($name)));
		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}
}
