<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/Support/ProjectVersion.php';
require_once dirname(__DIR__).'/Support/ZabbixVersion.php';\nrequire_once __DIR__.'/OfflineBundleRepository.php';

final class UpstreamIndexRepository {

	private const SCHEMA_VERSION = 1;
	private const CACHE_TTL = 900;
	private const MAX_INDEX_BYTES = 5242880;
	private const BASE_URL = 'https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/upstream-index/indexes';

	private string $cacheDir;

	public function __construct(?string $cacheDir = null) {
		$this->cacheDir = $cacheDir ?? rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
			.DIRECTORY_SEPARATOR.'zabbix-template-update-manager';
	}

	public static function endpointForVersion(string $zabbixVersion): ?string {
		$line = ZabbixVersion::line($zabbixVersion);
		return $line === null ? null : self::BASE_URL.'/'.rawurlencode($line).'.json';
	}

	public static function transportCapabilities(): array {
		return [
			'curl' => function_exists('curl_init'),
			'allow_url_fopen' => filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
			'openssl' => extension_loaded('openssl')
		];
	}

	public function load(string $zabbixVersion): array {
		$line = ZabbixVersion::line($zabbixVersion);
		if ($line === null) {
			throw new RuntimeException('Unable to determine the Zabbix major.minor line.');
		}

		$offlineContent = $this->offlineBundle->readIndex($line);
		if ($offlineContent !== null) {
			$index = self::decodeIndex($offlineContent, $line);
			$index['runtime'] = [
				'cache_status' => 'offline',
				'cache_age_seconds' => 0,
				'offline_only' => $this->offlineBundle->isOfflineOnly()
			];
			return $index;
		}
		if ($this->offlineBundle->isOfflineOnly()) {
			throw new RuntimeException(
				'Offline-only mode is enabled but the configured bundle does not contain index '.$line.'.'
			);
		}

		$cacheFile = $this->cacheFile($line);
		$cached = $this->readCache($cacheFile);

		if ($cached !== null && $cached['age'] <= self::CACHE_TTL) {
			$index = self::decodeIndex($cached['content'], $line);
			$index['runtime'] = [
				'cache_status' => 'fresh',
				'cache_age_seconds' => $cached['age']
			];
			return $index;
		}

		try {
			$endpoint = self::endpointForVersion($zabbixVersion);
			if ($endpoint === null) {
				throw new RuntimeException('Unable to build the upstream index endpoint.');
			}

			$content = $this->fetch($endpoint);
			$index = self::decodeIndex($content, $line);
			$this->writeCache($cacheFile, $content);
			$index['runtime'] = [
				'cache_status' => 'remote',
				'cache_age_seconds' => 0
			];
			return $index;
		}
		catch (Throwable $exception) {
			if ($cached !== null) {
				$index = self::decodeIndex($cached['content'], $line);
				$index['runtime'] = [
					'cache_status' => 'stale',
					'cache_age_seconds' => $cached['age'],
					'refresh_error' => true
				];
				return $index;
			}

			throw new RuntimeException(
				'Unable to retrieve the upstream template index: '.$exception->getMessage(),
				0,
				$exception
			);
		}
	}

	public static function decodeIndex(string $json, string $expectedLine): array {
		if ($json === '' || strlen($json) > self::MAX_INDEX_BYTES) {
			throw new RuntimeException('The upstream index is empty or exceeds the size limit.');
		}

		$data = json_decode($json, true);
		if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException('The upstream index is not valid JSON.');
		}

		if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
			throw new RuntimeException('Unsupported upstream index schema version.');
		}

		$source = $data['source'] ?? null;
		if (!is_array($source) || ($source['line'] ?? null) !== $expectedLine) {
			throw new RuntimeException('The upstream index source line does not match the requested Zabbix line.');
		}

		if (!preg_match('/^[a-f0-9]{40}$/', (string) ($source['commit'] ?? ''))) {
			throw new RuntimeException('The upstream index contains an invalid source commit.');
		}

		$templates = $data['templates'] ?? null;
		if (!is_array($templates)) {
			throw new RuntimeException('The upstream index template map is missing.');
		}

		foreach ($templates as $uuid => $template) {
			if (!preg_match('/^[a-f0-9]{32}$/', (string) $uuid)) {
				throw new RuntimeException('The upstream index contains an invalid UUID key.');
			}
			if (!is_array($template) || ($template['uuid'] ?? null) !== $uuid) {
				throw new RuntimeException('The upstream index contains an invalid template record.');
			}

			$paths = $template['paths'] ?? null;
			if (!is_array($paths) || $paths === []) {
				throw new RuntimeException('The upstream index contains a template without source paths.');
			}
			foreach ($paths as $path) {
				if (!self::isValidTemplatePath($path)) {
					throw new RuntimeException(
						'The upstream index contains an invalid source path: '.self::summarizePath($path)
					);
				}
			}

			$hashes = $template['content_sha256s'] ?? null;
			if (!is_array($hashes) || $hashes === []) {
				throw new RuntimeException('The upstream index contains a template without content hashes.');
			}
			foreach ($hashes as $hash) {
				if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
					throw new RuntimeException('The upstream index contains an invalid content hash.');
				}
			}

			$sources = $template['sources'] ?? null;
			if ($sources !== null) {
				if (!is_array($sources) || $sources === []) {
					throw new RuntimeException('The upstream index contains an invalid source fingerprint map.');
				}

				$sourcePaths = [];
				foreach ($sources as $source) {
					if (!is_array($source)) {
						throw new RuntimeException('The upstream index contains an invalid source fingerprint record.');
					}
					$sourcePath = $source['path'] ?? null;
					$sourceSha256 = $source['sha256'] ?? null;
					if (!self::isValidTemplatePath($sourcePath)
							|| !is_string($sourceSha256)
							|| !preg_match('/^[a-f0-9]{64}$/', $sourceSha256)) {
						throw new RuntimeException('The upstream index contains an invalid raw source fingerprint.');
					}
					if (isset($sourcePaths[$sourcePath])) {
						throw new RuntimeException('The upstream index contains a duplicate raw source path.');
					}
					$sourcePaths[$sourcePath] = true;
				}

				$declaredPaths = array_fill_keys($paths, true);
				ksort($sourcePaths, SORT_STRING);
				ksort($declaredPaths, SORT_STRING);
				if ($sourcePaths !== $declaredPaths) {
					throw new RuntimeException('The upstream index source fingerprints do not match the declared source paths.');
				}
			}
		}

		return $data;
	}

	public static function isValidTemplatePath($path): bool {
		if (!is_string($path)
				|| preg_match('#^templates/(?:[A-Za-z0-9._+-]+/)*[A-Za-z0-9._+-]+\.yaml$#D', $path) !== 1) {
			return false;
		}

		foreach (explode('/', $path) as $segment) {
			if ($segment === '.' || $segment === '..') {
				return false;
			}
		}

		return true;
	}

	private static function summarizePath($path): string {
		if (!is_string($path)) {
			return '<non-string>';
		}

		$path = preg_replace('/[\x00-\x1F\x7F]+/', '?', $path) ?? '';
		return strlen($path) > 220 ? substr($path, 0, 220).'…' : $path;
	}

	private function cacheFile(string $line): string {
		return $this->cacheDir.DIRECTORY_SEPARATOR.'upstream-'.str_replace('.', '-', $line).'.json';
	}

	private function readCache(string $cacheFile): ?array {
		if (!is_file($cacheFile)) {
			return null;
		}

		$content = @file_get_contents($cacheFile);
		$mtime = @filemtime($cacheFile);
		if ($content === false || $mtime === false) {
			return null;
		}

		return [
			'content' => $content,
			'age' => max(0, time() - $mtime)
		];
	}

	private function writeCache(string $cacheFile, string $content): void {
		if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0700, true) && !is_dir($this->cacheDir)) {
			return;
		}

		$tmpFile = $cacheFile.'.tmp-'.getmypid();
		if (@file_put_contents($tmpFile, $content, LOCK_EX) === false) {
			return;
		}

		@chmod($tmpFile, 0600);
		if (!@rename($tmpFile, $cacheFile)) {
			@unlink($tmpFile);
		}
	}

	private function fetch(string $url): string {
		$errors = [];

		if (function_exists('curl_init')) {
			try {
				return $this->fetchWithCurl($url);
			}
			catch (Throwable $exception) {
				$errors[] = 'cURL: '.$this->sanitizeTransportError($exception->getMessage());
			}
		}
		else {
			$errors[] = 'cURL: extension unavailable';
		}

		if (filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
			try {
				return $this->fetchWithStream($url);
			}
			catch (Throwable $exception) {
				$errors[] = 'stream: '.$this->sanitizeTransportError($exception->getMessage());
			}
		}
		else {
			$errors[] = 'stream: allow_url_fopen disabled';
		}

		throw new RuntimeException('All HTTP transports failed. '.implode(' | ', $errors));
	}

	private function fetchWithCurl(string $url): string {
		$handle = curl_init($url);
		if ($handle === false) {
			throw new RuntimeException('Unable to initialize cURL.');
		}

		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_USERAGENT => ProjectVersion::userAgent(),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2
		]);

		$content = curl_exec($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		$error = curl_error($handle);
		curl_close($handle);

		if (!is_string($content) || $status !== 200) {
			throw new RuntimeException(sprintf(
				'HTTP %d%s',
				$status,
				$error !== '' ? ' - '.$error : ''
			));
		}

		if (strlen($content) > self::MAX_INDEX_BYTES) {
			throw new RuntimeException('The upstream index response exceeds the size limit.');
		}

		return $content;
	}

	private function fetchWithStream(string $url): string {
		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 10,
				'ignore_errors' => true,
				'header' => "User-Agent: ".ProjectVersion::userAgent()."\r\n"
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true
			]
		]);

		$content = @file_get_contents($url, false, $context, 0, self::MAX_INDEX_BYTES + 1);
		$status = 0;
		if (isset($http_response_header[0])
				&& preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $matches) === 1) {
			$status = (int) $matches[1];
		}

		if (!is_string($content) || $status !== 200) {
			$error = error_get_last();
			$detail = is_array($error) ? (string) ($error['message'] ?? '') : '';
			throw new RuntimeException(sprintf(
				'HTTP %d%s',
				$status,
				$detail !== '' ? ' - '.$detail : ''
			));
		}

		if (strlen($content) > self::MAX_INDEX_BYTES) {
			throw new RuntimeException('The upstream index response exceeds the size limit.');
		}

		return $content;
	}

	private function sanitizeTransportError(string $message): string {
		$message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? '';
		$message = trim($message);
		return strlen($message) > 300 ? substr($message, 0, 300).'…' : $message;
	}
}
