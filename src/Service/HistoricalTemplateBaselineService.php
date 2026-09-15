<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use RuntimeException;

final class HistoricalTemplateBaselineService {

	private $historyLoader;
	private $sourceLoader;
	private $documentReader;

	public function __construct(?callable $historyLoader = null, ?callable $sourceLoader = null, ?callable $documentReader = null) {
		if ($historyLoader === null) {
			$historyRepository = new UpstreamTemplateHistoryRepository();
			$historyLoader = static fn(string $path, string $until, int $limit): array
				=> $historyRepository->listCommits($path, $until, $limit);
		}

		if ($sourceLoader === null) {
			$sourceRepository = new UpstreamTemplateSourceRepository();
			$sourceLoader = static fn(string $commit, string $path): array
				=> $sourceRepository->fetchAtCommit($commit, $path);
		}

		if ($documentReader === null) {
			$documentReader = static function (string $source): array {
				$reader = \CImportReaderFactory::getReader(\CImportReaderFactory::YAML);
				return $reader->read($source);
			};
		}

		$this->historyLoader = $historyLoader;
		$this->sourceLoader = $sourceLoader;
		$this->documentReader = $documentReader;
	}

	public function find(
		string $path,
		string $currentCommit,
		string $uuid,
		string $targetVendorVersion,
		string $expectedVendorName = 'Zabbix',
		int $maxCommits = 75
	): array {
		$targetVendorVersion = trim($targetVendorVersion);
		if ($targetVendorVersion === '') {
			throw new RuntimeException('A target vendor version is required for historical baseline lookup.');
		}

		$history = ($this->historyLoader)($path, $currentCommit, $maxCommits);
		if (!is_array($history) || !is_array($history['commits'] ?? null)) {
			throw new RuntimeException('The historical commit loader returned an invalid result.');
		}

		$examined = 0;
		foreach ($history['commits'] as $commit) {
			$id = strtolower(trim((string) ($commit['id'] ?? '')));
			if (!preg_match('/^[a-f0-9]{40}$/', $id)) {
				throw new RuntimeException('The historical commit loader returned an invalid commit ID.');
			}

			$examined++;
			$source = ($this->sourceLoader)($id, $path);
			if (!is_array($source) || !is_string($source['content'] ?? null)) {
				throw new RuntimeException('The historical source loader returned an invalid result.');
			}

			$document = ($this->documentReader)($source['content']);
			if (!is_array($document)) {
				throw new RuntimeException('The historical template parser returned an invalid document.');
			}

			$metadata = UpstreamTemplateDocumentService::templateMetadata($document, $uuid);
			if ($metadata['vendor_version'] !== $targetVendorVersion) {
				continue;
			}

			if ($expectedVendorName !== '' && $metadata['vendor_name'] !== $expectedVendorName) {
				throw new RuntimeException('The historical template vendor does not match the expected official vendor.');
			}

			$isolated = UpstreamTemplateDocumentService::buildHistoricalImportSource(
				$document,
				$uuid,
				$targetVendorVersion,
				$expectedVendorName
			);

			return [
				'status' => 'found',
				'commit' => $id,
				'path' => $path,
				'vendor_version' => $targetVendorVersion,
				'commits_examined' => $examined,
				'history_truncated' => (bool) ($history['truncated'] ?? false),
				'source' => $isolated['source']
			];
		}

		return [
			'status' => (bool) ($history['truncated'] ?? false) ? 'history_limit_reached' : 'not_found',
			'commit' => '',
			'path' => $path,
			'vendor_version' => $targetVendorVersion,
			'commits_examined' => $examined,
			'history_truncated' => (bool) ($history['truncated'] ?? false),
			'source' => null
		];
	}
}
