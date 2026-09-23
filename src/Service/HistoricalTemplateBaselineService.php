<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use RuntimeException;

final class HistoricalTemplateBaselineService {

	private $historyLoader;
	private $sourceLoader;
	private $documentReader;
	private $previousPathResolver;

	public function __construct(
		?callable $historyLoader = null,
		?callable $sourceLoader = null,
		?callable $documentReader = null,
		?callable $previousPathResolver = null
	) {
		$historyRepository = null;
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

		if ($previousPathResolver === null) {
			$previousPathResolver = $historyRepository !== null
				? static fn(string $commit, string $path): ?string
					=> $historyRepository->previousPathAtCommit($commit, $path)
				: static fn(string $commit, string $path): ?string => null;
		}

		$this->historyLoader = $historyLoader;
		$this->sourceLoader = $sourceLoader;
		$this->documentReader = $documentReader;
		$this->previousPathResolver = $previousPathResolver;
	}

	/**
	 * Resolve an official historical baseline for the installed vendor version.
	 *
	 * vendor.version is not a unique source revision: several consecutive file
	 * commits may legitimately carry the same value. When more than one distinct
	 * official template body exists for that vendor version, each candidate is
	 * compared against LOCAL using Zabbix importcompare semantics when available.
	 * Only an exact semantic LOCAL match is then authoritative. Otherwise the
	 * baseline is reported as ambiguous and update readiness remains fail-closed.
	 */
	public function find(
		string $path,
		string $currentCommit,
		string $uuid,
		string $targetVendorVersion,
		string $expectedVendorName = 'Zabbix',
		int $maxCommits = 75,
		?callable $candidateEvaluator = null,
		bool $allowExternalTemplateReferences = false
	): array {
		$targetVendorVersion = trim($targetVendorVersion);
		if ($targetVendorVersion === '') {
			throw new RuntimeException('A target vendor version is required for historical baseline lookup.');
		}

		$history = ($this->historyLoader)($path, $currentCommit, $maxCommits);
		if (!is_array($history) || !is_array($history['commits'] ?? null)) {
			throw new RuntimeException('The historical commit loader returned an invalid result.');
		}

		// Runtime analysis already loads these services. Using them here keeps
		// provenance selection tied to the same native Zabbix importcompare semantics
		// used everywhere else, while dependency-injected unit tests remain standalone.
		if ($candidateEvaluator === null
				&& class_exists(TemplateImportCompareService::class)
				&& class_exists(ImportCompareSummary::class)) {
			$compareService = new TemplateImportCompareService();
			$candidateEvaluator = static function (string $source, string $commit) use ($compareService): int {
				$summary = ImportCompareSummary::summarize($compareService->compare($source));
				return max(0, (int) ($summary['total'] ?? 0));
			};
		}

		$examined = 0;
		$matchingCommitCount = 0;
		$seenTargetVersion = false;
		$versionBoundaryReached = false;
		$candidatesByHash = [];
		$historicalPath = $path;
		$previousCommit = null;

		// The canonical Bitbucket commit endpoint returns newest -> oldest for the path.
		// Once the requested version block has been entered and an older version is
		// reached, all revisions for this vendor version have been enumerated.
		foreach ($history['commits'] as $commit) {
			$id = strtolower(trim((string) ($commit['id'] ?? '')));
			if (!preg_match('/^[a-f0-9]{40}$/', $id)) {
				throw new RuntimeException('The historical commit loader returned an invalid commit ID.');
			}

			$examined++;
			$loaded = $this->loadRevision($id, $historicalPath, $previousCommit, $uuid);
			$source = $loaded['source'];
			$document = $loaded['document'];
			$metadata = $loaded['metadata'];
			$historicalPath = $loaded['path'];
			$previousCommit = $id;
			if ($metadata['vendor_version'] !== $targetVendorVersion) {
				if ($seenTargetVersion) {
					$versionBoundaryReached = true;
					break;
				}
				continue;
			}

			$seenTargetVersion = true;
			$matchingCommitCount++;

			if ($expectedVendorName !== '' && $metadata['vendor_name'] !== $expectedVendorName) {
				throw new RuntimeException('The historical template vendor does not match the expected official vendor.');
			}

			$isolated = UpstreamTemplateDocumentService::buildHistoricalImportSource(
				$document,
				$uuid,
				$targetVendorVersion,
				$expectedVendorName,
				$allowExternalTemplateReferences
			);
			$sourceHash = hash('sha256', $isolated['source']);

			if (!array_key_exists($sourceHash, $candidatesByHash)) {
				$candidatesByHash[$sourceHash] = [
					'commit' => $id,
					'source' => $isolated['source'],
					'source_sha256' => $sourceHash,
					'commit_count' => 1,
					'semantic_distance' => null
				];
			}
			else {
				$candidatesByHash[$sourceHash]['commit_count']++;
			}
		}

		$historyTruncated = (bool) ($history['truncated'] ?? false) && !$versionBoundaryReached;
		$candidates = array_values($candidatesByHash);
		$distinctCandidateCount = count($candidates);

		if ($distinctCandidateCount === 0) {
			return [
				'status' => $historyTruncated ? 'history_limit_reached' : 'not_found',
				'commit' => '',
				'path' => $path,
				'vendor_version' => $targetVendorVersion,
				'commits_examined' => $examined,
				'history_truncated' => $historyTruncated,
				'candidate_count' => 0,
				'distinct_candidate_count' => 0,
				'exact_match_count' => 0,
				'selection' => 'none',
				'source' => null
			];
		}

		$exactMatches = [];
		$closest = null;
		if ($candidateEvaluator !== null) {
			foreach ($candidates as $index => $candidate) {
				$distance = ($candidateEvaluator)($candidate['source'], $candidate['commit']);
				if (!is_int($distance) || $distance < 0) {
					throw new RuntimeException('The historical baseline candidate evaluator returned an invalid distance.');
				}
				$candidates[$index]['semantic_distance'] = $distance;
				if ($distance === 0) {
					$exactMatches[] = $candidates[$index];
				}
				if ($closest === null || $distance < $closest['semantic_distance']) {
					$closest = $candidates[$index];
				}
			}
		}

		if ($distinctCandidateCount === 1) {
			$selected = $candidates[0];
			return $this->foundResult(
				$selected,
				$path,
				$targetVendorVersion,
				$examined,
				$historyTruncated,
				$matchingCommitCount,
				$distinctCandidateCount,
				count($exactMatches),
				($selected['semantic_distance'] ?? null) === 0 ? 'exact_local_match' : 'single_official_content'
			);
		}

		if ($exactMatches !== []) {
			// Candidates retain newest -> oldest order, so choose the newest exact
			// semantic match if several source revisions normalize identically to LOCAL.
			return $this->foundResult(
				$exactMatches[0],
				$path,
				$targetVendorVersion,
				$examined,
				$historyTruncated,
				$matchingCommitCount,
				$distinctCandidateCount,
				count($exactMatches),
				'exact_local_match'
			);
		}

		return [
			'status' => 'ambiguous',
			'commit' => '',
			'path' => $path,
			'vendor_version' => $targetVendorVersion,
			'commits_examined' => $examined,
			'history_truncated' => $historyTruncated,
			'candidate_count' => $matchingCommitCount,
			'distinct_candidate_count' => $distinctCandidateCount,
			'exact_match_count' => 0,
			'selection' => 'no_exact_local_match',
			'closest_commit' => $closest['commit'] ?? '',
			'closest_changes' => $closest['semantic_distance'] ?? null,
			'source' => null
		];
	}

	private function loadRevision(
		string $commit,
		string $path,
		?string $previousCommit,
		string $uuid
	): array {
		try {
			return $this->loadRevisionAtPath($commit, $path, $uuid);
		}
		catch (\Throwable $firstException) {
			if ($previousCommit !== null) {
				try {
					$previousPath = ($this->previousPathResolver)($previousCommit, $path);
				}
				catch (\Throwable $resolverException) {
					throw new RuntimeException(sprintf(
						'Unable to resolve historical path before commit %s while reading %s: %s',
						$previousCommit,
						$path,
						$resolverException->getMessage()
					), 0, $resolverException);
				}

				if (is_string($previousPath)
						&& $previousPath !== ''
						&& $previousPath !== $path) {
					try {
						return $this->loadRevisionAtPath($commit, $previousPath, $uuid);
					}
					catch (\Throwable $retryException) {
						throw new RuntimeException(sprintf(
							'Unable to read historical template revision %s using current path %s or rename-aware path %s: %s',
							$commit,
							$path,
							$previousPath,
							$retryException->getMessage()
						), 0, $retryException);
					}
				}
			}

			throw new RuntimeException(sprintf(
				'Unable to read historical template revision %s at path %s: %s',
				$commit,
				$path,
				$firstException->getMessage()
			), 0, $firstException);
		}
	}

	private function loadRevisionAtPath(string $commit, string $path, string $uuid): array {
		$source = ($this->sourceLoader)($commit, $path);
		if (!is_array($source) || !is_string($source['content'] ?? null)) {
			throw new RuntimeException('The historical source loader returned an invalid result.');
		}

		$document = ($this->documentReader)($source['content']);
		if (!is_array($document)) {
			throw new RuntimeException('The historical template parser returned an invalid document.');
		}

		$metadata = UpstreamTemplateDocumentService::templateMetadata($document, $uuid);

		return [
			'source' => $source,
			'document' => $document,
			'metadata' => $metadata,
			'path' => $path
		];
	}

	private function foundResult(
		array $candidate,
		string $path,
		string $vendorVersion,
		int $examined,
		bool $historyTruncated,
		int $candidateCount,
		int $distinctCandidateCount,
		int $exactMatchCount,
		string $selection
	): array {
		return [
			'status' => 'found',
			'commit' => $candidate['commit'],
			'path' => $path,
			'vendor_version' => $vendorVersion,
			'commits_examined' => $examined,
			'history_truncated' => $historyTruncated,
			'candidate_count' => $candidateCount,
			'distinct_candidate_count' => $distinctCandidateCount,
			'exact_match_count' => $exactMatchCount,
			'selection' => $selection,
			'semantic_distance' => $candidate['semantic_distance'],
			'source' => $candidate['source']
		];
	}
}
