<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;
use Throwable;

final class UpstreamIndexRepository {

	private const SCHEMA_VERSION = 1;
	private const CACHE_TTL = 900;
	private const MAX_INDEX_BYTES = 5242880;
	private const BASE_URL = 'https://raw.githubusercontent.com/kmansur/zabbix-template-update-manager/upstream-index/indexes';
	private const USER_AGENT = 'Zabbix-Template-Update-Manager/0.1.0-dev';

	private string $cacheDir;

	public function __construct(?string $cacheDir = null) {
		$this->cacheDir = $cacheDir ?? rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
			.DIRECTORY_SEPARATOR.'zabbix-template-update-manager';
	}

	public function load(string $zabbixVersion): array {
		$line = ZabbixVersion::line($zabbixVersion);
		if ($line === null) {
			throw new RuntimeException('Unable to determine the Zabbix major.minor line.');
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
			$content = $this->fetch(self::BASE_URL.'/'.rawurlencode($line).'.json');
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
				'Unable to retrieve the upstream template index.',
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
					throw new RuntimeException('The upstream index contains an invalid source path.');
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
		}

		return $data;
	}

	public static function isValidTemplatePath($path): bool {
		return is_string($path)
			&& preg_match('#^templates/(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+\.yaml$#D', $path) === 1;
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
		if (function_exists('curl_init')) {
			$handle = curl_init($url);
			if ($handle === false) {
				throw new RuntimeException('Unable to initialize cURL.');
			}

			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT => 10,
				CURLOPT_USERAGENT => self::USER_AGENT,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2
			]);

			$content = curl_exec($handle);
			$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
			$error = curl_error($handle);
			curl_close($handle);

			if (!is_string($content) || $status !== 200) {
				throw new RuntimeException(sprintf('Upstream index request failed with HTTP %d: %s', $status, $error));
			}

			if (strlen($content) > self::MAX_INDEX_BYTES) {
				throw new RuntimeException('The upstream index response exceeds the size limit.');
			}

			return $content;
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 10,
				'header' => "User-Agent: ".self::USER_AGENT."\r\n"
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true
			]
		]);

		$content = @file_get_contents($url, false, $context, 0, self::MAX_INDEX_BYTES + 1);
		if (!is_string($content)) {
			throw new RuntimeException('Unable to retrieve the upstream index with the PHP stream client.');
		}

		if (strlen($content) > self::MAX_INDEX_BYTES) {
			throw new RuntimeException('The upstream index response exceeds the size limit.');
		}

		return $content;
	}
}
