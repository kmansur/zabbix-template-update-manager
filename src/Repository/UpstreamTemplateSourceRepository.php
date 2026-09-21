<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/Support/ProjectVersion.php';

final class UpstreamTemplateSourceRepository {

	private const BASE_URL = 'https://git.zabbix.com/projects/ZBX/repos/zabbix/raw/';
	private const MAX_SOURCE_BYTES = 10485760;

	public function fetch(array $indexSource, array $upstreamTemplate): array {
		$commit = strtolower(trim((string) ($indexSource['commit'] ?? '')));
		if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
			throw new RuntimeException('The upstream source commit is invalid.');
		}

		$paths = $upstreamTemplate['paths'] ?? null;
		if (!is_array($paths) || $paths === []) {
			throw new RuntimeException('The upstream template has no source paths.');
		}

		$hashes = $upstreamTemplate['content_sha256s'] ?? null;
		if (!is_array($hashes) || $hashes === []) {
			throw new RuntimeException('The upstream template has no content fingerprint.');
		}
		if (count(array_unique($hashes)) !== 1) {
			throw new RuntimeException('The upstream template has multiple official content variants.');
		}
		$templateContentSha256 = strtolower(trim((string) $hashes[0]));

		$sourceHashes = [];
		$sources = $upstreamTemplate['sources'] ?? null;
		if ($sources !== null) {
			if (!is_array($sources) || $sources === []) {
				throw new RuntimeException('The upstream template has an invalid raw source fingerprint map.');
			}
			foreach ($sources as $source) {
				if (!is_array($source)) {
					throw new RuntimeException('The upstream template has an invalid raw source fingerprint record.');
				}
				$sourcePath = (string) ($source['path'] ?? '');
				$sourceSha256 = strtolower(trim((string) ($source['sha256'] ?? '')));
				if (!UpstreamIndexRepository::isValidTemplatePath($sourcePath)
						|| !preg_match('/^[a-f0-9]{64}$/', $sourceSha256)) {
					throw new RuntimeException('The upstream template has an invalid raw source fingerprint.');
				}
				if (array_key_exists($sourcePath, $sourceHashes)) {
					throw new RuntimeException('The upstream template has a duplicate raw source path.');
				}
				$sourceHashes[$sourcePath] = $sourceSha256;
			}
		}

		$lastException = null;
		foreach ($paths as $path) {
			if (!UpstreamIndexRepository::isValidTemplatePath($path)) {
				throw new RuntimeException('The upstream template contains an invalid source path.');
			}

			try {
				$fetched = $this->fetchAtCommit($commit, $path);
			}
			catch (Throwable $exception) {
				$lastException = $exception;
				continue;
			}

			$fetched['content_sha256'] = $templateContentSha256;
			$fetched['source_sha256'] = '';
			if ($sourceHashes !== []) {
				if (!isset($sourceHashes[$path])) {
					throw new RuntimeException('The selected upstream path has no raw source fingerprint.');
				}
				$actualSourceSha256 = hash('sha256', $fetched['content']);
				if (!hash_equals($sourceHashes[$path], $actualSourceSha256)) {
					throw new RuntimeException('The immutable upstream raw source fingerprint does not match the validated upstream index.');
				}
				$fetched['source_sha256'] = $actualSourceSha256;
			}

			return $fetched;
		}

		throw new RuntimeException(
			'Unable to retrieve any official source path for the upstream template.',
			0,
			$lastException
		);
	}

	public function fetchAtCommit(string $commit, string $path): array {
		$url = self::buildUrl($commit, $path);
		return [
			'content' => $this->fetchUrl($url),
			'path' => $path,
			'commit' => strtolower(trim($commit)),
			'url' => $url
		];
	}

	public static function buildUrl(string $commit, string $path): string {
		$commit = strtolower(trim($commit));
		if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
			throw new RuntimeException('The upstream source commit is invalid.');
		}
		if (!UpstreamIndexRepository::isValidTemplatePath($path)) {
			throw new RuntimeException('The upstream source path is invalid.');
		}

		$encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
		return self::BASE_URL.$encodedPath.'?at='.rawurlencode($commit);
	}

	private function fetchUrl(string $url): string {
		if (function_exists('curl_init')) {
			return $this->fetchWithCurl($url);
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => 15,
				'follow_location' => 0,
				'header' => 'User-Agent: '.ProjectVersion::userAgent()."\r\n"
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true
			]
		]);

		$content = @file_get_contents($url, false, $context, 0, self::MAX_SOURCE_BYTES + 1);
		if (!is_string($content)) {
			throw new RuntimeException('Unable to retrieve the upstream template source with the PHP stream client.');
		}
		if (strlen($content) > self::MAX_SOURCE_BYTES) {
			throw new RuntimeException('The upstream template source exceeds the size limit.');
		}

		return $content;
	}

	private function fetchWithCurl(string $url): string {
		$handle = curl_init($url);
		if ($handle === false) {
			throw new RuntimeException('Unable to initialize cURL.');
		}

		$content = '';
		$tooLarge = false;
		$options = [
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_USERAGENT => ProjectVersion::userAgent(),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$content, &$tooLarge): int {
				if (strlen($content) + strlen($chunk) > self::MAX_SOURCE_BYTES) {
					$tooLarge = true;
					return 0;
				}

				$content .= $chunk;
				return strlen($chunk);
			}
		];

		if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
			$options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
		}

		curl_setopt_array($handle, $options);
		$result = curl_exec($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		$effectiveUrl = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
		$error = curl_error($handle);
		curl_close($handle);

		if ($tooLarge) {
			throw new RuntimeException('The upstream template source exceeds the size limit.');
		}
		if ($result === false || $status !== 200) {
			throw new RuntimeException(sprintf('Upstream source request failed with HTTP %d: %s', $status, $error));
		}
		if (strtolower((string) parse_url($effectiveUrl, PHP_URL_HOST)) !== 'git.zabbix.com') {
			throw new RuntimeException('The upstream source request redirected outside the canonical Zabbix host.');
		}
		if ($content === '') {
			throw new RuntimeException('The upstream template source is empty.');
		}

		return $content;
	}
}
