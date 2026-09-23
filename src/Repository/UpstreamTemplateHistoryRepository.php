<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use JsonException;
use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;
use RuntimeException;

require_once dirname(__DIR__).'/Support/ProjectVersion.php';
require_once __DIR__.'/OfflineBundleRepository.php';
require_once __DIR__.'/UpstreamIndexRepository.php';

final class UpstreamTemplateHistoryRepository {

	private OfflineBundleRepository $offlineBundle;

	private const BASE_URL = 'https://git.zabbix.com/rest/api/1.0/projects/ZBX/repos/zabbix/commits';
	private const PAGE_SIZE = 25;
	private const MAX_COMMITS = 75;
	private const MAX_RESPONSE_BYTES = 2097152;
	private const CHANGES_LIMIT = 1000;

	public function __construct(?OfflineBundleRepository $offlineBundle = null) {
		$this->offlineBundle = $offlineBundle ?? new OfflineBundleRepository();
	}

	public function listCommits(string $path, string $until, int $maxCommits = self::MAX_COMMITS): array {
		if (!UpstreamIndexRepository::isValidTemplatePath($path)) {
			throw new RuntimeException('The upstream history path is invalid.');
		}

		$until = strtolower(trim($until));
		if (!preg_match('/^[a-f0-9]{40}$/', $until)) {
			throw new RuntimeException('The upstream history commit is invalid.');
		}

		if ($maxCommits < 1 || $maxCommits > self::MAX_COMMITS) {
			throw new RuntimeException('The upstream history scan limit is invalid.');
		}

		$offline = $this->offlineBundle->readHistory($path, $until, $maxCommits);
		if ($offline !== null) {
			return $offline;
		}
		if ($this->offlineBundle->isOfflineOnly()) {
			throw new RuntimeException('Offline-only mode is enabled but the required template history is missing.');
		}

		$commits = [];
		$seen = [];
		$start = 0;
		$isLastPage = false;

		while (count($commits) < $maxCommits) {
			$limit = min(self::PAGE_SIZE, $maxCommits - count($commits));
			$page = self::decodePage($this->fetchUrl(self::buildUrl($path, $until, $start, $limit)));

			foreach ($page['commits'] as $commit) {
				if (!isset($seen[$commit['id']])) {
					$seen[$commit['id']] = true;
					$commits[] = $commit;
				}
				if (count($commits) >= $maxCommits) {
					break;
				}
			}

			$isLastPage = $page['is_last_page'];
			if ($isLastPage || count($commits) >= $maxCommits) {
				break;
			}

			$next = $page['next_page_start'];
			if (!is_int($next) || $next <= $start) {
				throw new RuntimeException('The upstream history endpoint returned an invalid pagination cursor.');
			}
			$start = $next;
		}

		return [
			'commits' => $commits,
			'truncated' => !$isLastPage,
			'limit' => $maxCommits
		];
	}

	/**
	 * Resolves the path used immediately before a rename/move commit.
	 *
	 * The commit history endpoint can follow renames, but raw historical source
	 * retrieval still needs the path that existed at the requested revision.
	 * This lookup is used lazily only after the current tracked path fails.
	 */
	public function previousPathAtCommit(string $commit, string $currentPath): ?string {
		if (!UpstreamIndexRepository::isValidTemplatePath($currentPath)) {
			throw new RuntimeException('The upstream historical rename path is invalid.');
		}

		$commit = strtolower(trim($commit));
		if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
			throw new RuntimeException('The upstream historical rename commit is invalid.');
		}

		if ($this->offlineBundle->isOfflineOnly()) {
			return null;
		}

		return self::decodePreviousPath(
			$this->fetchUrl(self::buildChangesUrl($commit)),
			$currentPath
		);
	}

	public static function buildChangesUrl(string $commit): string {
		$commit = strtolower(trim($commit));
		if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
			throw new RuntimeException('The upstream historical rename commit is invalid.');
		}

		return self::BASE_URL.'/'.rawurlencode($commit).'/changes?'.http_build_query([
			'limit' => self::CHANGES_LIMIT
		], '', '&', PHP_QUERY_RFC3986);
	}

	public static function decodePreviousPath(string $json, string $currentPath): ?string {
		if (!UpstreamIndexRepository::isValidTemplatePath($currentPath)) {
			throw new RuntimeException('The upstream historical rename path is invalid.');
		}
		if ($json === '' || strlen($json) > self::MAX_RESPONSE_BYTES) {
			throw new RuntimeException('The upstream historical changes response is empty or exceeds the size limit.');
		}

		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The upstream historical changes response is not valid JSON.', 0, $exception);
		}

		if (!is_array($data) || !is_array($data['values'] ?? null)) {
			throw new RuntimeException('The upstream historical changes response has an invalid structure.');
		}

		foreach ($data['values'] as $record) {
			if (!is_array($record)) {
				continue;
			}

			$path = self::changePath($record['path'] ?? null);
			$srcPath = self::changePath($record['srcPath'] ?? null);
			if ($path === $currentPath
					&& $srcPath !== null
					&& $srcPath !== $currentPath
					&& UpstreamIndexRepository::isValidTemplatePath($srcPath)) {
				return $srcPath;
			}
		}

		return null;
	}

	private static function changePath($path): ?string {
		if (!is_array($path)) {
			return null;
		}

		$components = $path['components'] ?? null;
		if (is_array($components) && $components !== []) {
			$segments = [];
			foreach ($components as $component) {
				if (!is_string($component) || $component === '' || $component === '.' || $component === '..') {
					return null;
				}
				$segments[] = $component;
			}
			return implode('/', $segments);
		}

		$parent = trim((string) ($path['parent'] ?? ''), '/');
		$name = trim((string) ($path['name'] ?? ''), '/');
		if ($name === '') {
			return null;
		}

		return $parent !== '' ? $parent.'/'.$name : $name;
	}

	public static function buildUrl(string $path, string $until, int $start = 0, int $limit = self::PAGE_SIZE): string {
		if (!UpstreamIndexRepository::isValidTemplatePath($path)) {
			throw new RuntimeException('The upstream history path is invalid.');
		}
		$until = strtolower(trim($until));
		if (!preg_match('/^[a-f0-9]{40}$/', $until)) {
			throw new RuntimeException('The upstream history commit is invalid.');
		}
		if ($start < 0 || $limit < 1 || $limit > self::PAGE_SIZE) {
			throw new RuntimeException('The upstream history pagination parameters are invalid.');
		}

		$query = http_build_query([
			'path' => $path,
			'until' => $until,
			'followRenames' => 'true',
			'limit' => $limit,
			'start' => $start
		], '', '&', PHP_QUERY_RFC3986);

		return self::BASE_URL.'?'.$query;
	}

	public static function decodePage(string $json): array {
		if ($json === '' || strlen($json) > self::MAX_RESPONSE_BYTES) {
			throw new RuntimeException('The upstream history response is empty or exceeds the size limit.');
		}

		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The upstream history response is not valid JSON.', 0, $exception);
		}

		if (!is_array($data) || !is_array($data['values'] ?? null) || !is_bool($data['isLastPage'] ?? null)) {
			throw new RuntimeException('The upstream history response has an invalid structure.');
		}

		$commits = [];
		foreach ($data['values'] as $record) {
			if (!is_array($record)) {
				throw new RuntimeException('The upstream history response contains an invalid commit record.');
			}

			$id = strtolower(trim((string) ($record['id'] ?? '')));
			if (!preg_match('/^[a-f0-9]{40}$/', $id)) {
				throw new RuntimeException('The upstream history response contains an invalid commit ID.');
			}

			$commits[] = [
				'id' => $id,
				'message' => is_string($record['message'] ?? null) ? $record['message'] : ''
			];
		}

		$nextPageStart = null;
		if (!$data['isLastPage']) {
			if (!is_int($data['nextPageStart'] ?? null)) {
				throw new RuntimeException('The upstream history response is missing its pagination cursor.');
			}
			$nextPageStart = $data['nextPageStart'];
		}

		return [
			'commits' => $commits,
			'is_last_page' => $data['isLastPage'],
			'next_page_start' => $nextPageStart
		];
	}

	private function fetchUrl(string $url): string {
		if (function_exists('curl_init')) {
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
					if (strlen($content) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
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
				throw new RuntimeException('The upstream history response exceeds the size limit.');
			}
			if ($result === false || $status !== 200) {
				throw new RuntimeException(sprintf('Upstream history request failed with HTTP %d: %s', $status, $error));
			}
			if (strtolower((string) parse_url($effectiveUrl, PHP_URL_HOST)) !== 'git.zabbix.com') {
				throw new RuntimeException('The upstream history request redirected outside the canonical Zabbix host.');
			}
			return $content;
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

		$content = @file_get_contents($url, false, $context, 0, self::MAX_RESPONSE_BYTES + 1);
		if (!is_string($content)) {
			throw new RuntimeException('Unable to retrieve upstream template history with the PHP stream client.');
		}
		if (strlen($content) > self::MAX_RESPONSE_BYTES) {
			throw new RuntimeException('The upstream history response exceeds the size limit.');
		}
		return $content;
	}
}
