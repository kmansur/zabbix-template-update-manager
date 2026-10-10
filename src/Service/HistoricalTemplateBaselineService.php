<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use RuntimeException;

require_once __DIR__.'/HistoricalDashboardNormalizationDiagnostic.php';
require_once __DIR__.'/HistoricalDashboardCandidateCorrelation.php';

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
		bool $allowExternalTemplateReferences = false,
		float $maxRuntimeSeconds = 0.0,
		?callable $dashboardCandidateEvaluator = null
	): array {
		$startedAt = microtime(true);
		$targetVendorVersion = trim($targetVendorVersion);
		if ($targetVendorVersion === '') {
			throw new RuntimeException('A target vendor version is required for historical baseline lookup.');
		}

		$history = ($this->historyLoader)($path, $currentCommit, $maxCommits);
		if (!is_array($history) || !is_array($history['commits'] ?? null)) {
			throw new RuntimeException('The historical commit loader returned an invalid result.');
		}

		if ($this->runtimeBudgetReached($startedAt, $maxRuntimeSeconds)) {
			return $this->timeBudgetResult(
				$path,
				$targetVendorVersion,
				0,
				(bool) ($history['truncated'] ?? false),
				0,
				0
			);
		}

		// Runtime analysis already loads these services. Using them here keeps
		// provenance selection tied to the same native Zabbix importcompare semantics
		// used everywhere else, while dependency-injected unit tests remain standalone.
		if ($candidateEvaluator === null
				&& class_exists(TemplateImportCompareService::class)
				&& class_exists(ImportCompareSummary::class)) {
			$compareService = new TemplateImportCompareService();
			$candidateEvaluator = static function (string $source, string $commit) use ($compareService): array {
				$diff = $compareService->compare($source);
				$summary = ImportCompareSummary::summarize($diff);
				$preview = UpdatePreviewAnalyzer::analyze($diff, 100);
				return [
					'total' => max(0, (int) ($summary['total'] ?? 0)),
					'by_entity' => $summary['by_entity'] ?? [],
					'field_names' => self::safeFieldNames($preview),
					'change_structure' => self::safeChangeStructure($preview),
					'auto_start_diagnostic' => HistoricalDashboardNormalizationDiagnostic::summarizePreview($preview),
					'auto_start_unknown_shapes' => HistoricalDashboardNormalizationDiagnostic::describeUnknownPreview($preview),
					'auto_start_missing_transitions' => HistoricalDashboardNormalizationDiagnostic::missingTransitionSummary($preview)
				];
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
			if ($this->runtimeBudgetReached($startedAt, $maxRuntimeSeconds)) {
				return $this->timeBudgetResult(
					$path,
					$targetVendorVersion,
					$examined,
					(bool) ($history['truncated'] ?? false),
					$matchingCommitCount,
					count($candidatesByHash)
				);
			}

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
					'semantic_distance' => null,
					'change_categories' => [],
					'field_names' => [],
					'change_structure' => [],
					'auto_start_diagnostic' => [],
					'auto_start_unknown_shapes' => [],
					'auto_start_missing_transitions' => [],
					'dashboard_correlation' => []
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
				$evaluation = ($candidateEvaluator)($candidate['source'], $candidate['commit']);
				$distance = is_array($evaluation) ? ($evaluation['total'] ?? null) : $evaluation;
				if (!is_int($distance) || $distance < 0) {
					throw new RuntimeException('The historical baseline candidate evaluator returned an invalid distance.');
				}
				$candidates[$index]['semantic_distance'] = $distance;
				if ($dashboardCandidateEvaluator !== null) {
					$correlation = $dashboardCandidateEvaluator($candidate['source'], $candidate['commit']);
					$candidates[$index]['dashboard_correlation'] = is_array($correlation) ? $correlation : [];
				}
				$categories = is_array($evaluation) ? ($evaluation['by_entity'] ?? []) : [];
				if (!is_array($categories)) {
					throw new RuntimeException('Historical candidate change categories must be an array.');
				}
				$candidates[$index]['change_categories'] = $categories;
				$candidates[$index]['field_names'] = is_array($evaluation) && is_array($evaluation['field_names'] ?? null)
					? $evaluation['field_names'] : [];
				$candidates[$index]['change_structure'] = is_array($evaluation) && is_array($evaluation['change_structure'] ?? null)
					? $evaluation['change_structure'] : [];
				$candidates[$index]['auto_start_diagnostic'] = is_array($evaluation) && is_array($evaluation['auto_start_diagnostic'] ?? null)
					? $evaluation['auto_start_diagnostic'] : [];
				$candidates[$index]['auto_start_unknown_shapes'] = is_array($evaluation) && is_array($evaluation['auto_start_unknown_shapes'] ?? null)
					? $evaluation['auto_start_unknown_shapes'] : [];
				$candidates[$index]['auto_start_missing_transitions'] = is_array($evaluation) && is_array($evaluation['auto_start_missing_transitions'] ?? null)
					? $evaluation['auto_start_missing_transitions'] : [];
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
			// Audit-only candidate identities; never feed a guessed source to import.
			'candidate_audit' => array_map(static fn(array $candidate): array => [
				'commit' => $candidate['commit'],
				'source_sha256' => $candidate['source_sha256'],
				'commit_count' => $candidate['commit_count'],
				'semantic_distance' => $candidate['semantic_distance'],
				'change_categories' => $candidate['change_categories'],
				'field_names' => $candidate['field_names'],
				'change_structure' => $candidate['change_structure'],
				'auto_start_diagnostic' => $candidate['auto_start_diagnostic'],
				'auto_start_unknown_shapes' => $candidate['auto_start_unknown_shapes'],
				'auto_start_missing_transitions' => $candidate['auto_start_missing_transitions'],
				'dashboard_correlation' => $candidate['dashboard_correlation']
			], $candidates),
			'source' => null
		];
	}


	/**
	 * Diagnostic metadata only: fixed allow-list prevents logging arbitrary field
	 * names, entity identifiers, macros, URLs or before/after configuration values.
	 */
	/**
	 * Aggregate direct field operations separately from structural wrappers.
	 * Do not emit entity identities, paths, field values or untrusted labels.
	 */
	private static function safeChangeStructure(array $preview): array {
		$totals = ['direct_fields' => 0, 'entity_additions' => 0,
			'entity_removals' => 0, 'unresolved_identity' => 0];
		foreach ((array) ($preview['details'] ?? []) as $detail) {
			if (!is_array($detail)) {
				continue;
			}
			switch ($detail['change_type'] ?? '') {
				case 'updated': $totals['direct_fields']++; break;
				case 'added': $totals['entity_additions']++; break;
				case 'removed': $totals['entity_removals']++; break;
				default: $totals['unresolved_identity']++; break;
			}
		}
		$totals['details_truncated'] = !empty($preview['details_truncated']) ? 1 : 0;
		return $totals;
	}

	private static function safeFieldNames(array $preview): array {
		$allowed = ['name', 'description', 'template', 'vendor', 'version', 'status', 'type', 'delay', 'history', 'trends', 'units', 'value_type', 'priority', 'width', 'height', 'display_period', 'auto_start'];
		$counts = [];
		foreach ((array) ($preview['details'] ?? []) as $detail) {
			if (!is_array($detail)) {
				continue;
			}
			$field = (string) ($detail['field'] ?? '');
			if (!in_array($field, $allowed, true)) {
				$counts['other_or_sensitive'] = ($counts['other_or_sensitive'] ?? 0) + 1;
				continue;
			}
			$counts[$field] = ($counts[$field] ?? 0) + 1;
		}
		if (!empty($preview['details_truncated'])) {
			$counts['details_truncated'] = 1;
		}
		ksort($counts, SORT_STRING);
		return $counts;
	}

	private function runtimeBudgetReached(float $startedAt, float $maxRuntimeSeconds): bool {
		return $maxRuntimeSeconds > 0.0
			&& (microtime(true) - $startedAt) >= $maxRuntimeSeconds;
	}

	private function timeBudgetResult(
		string $path,
		string $vendorVersion,
		int $examined,
		bool $historyTruncated,
		int $candidateCount,
		int $distinctCandidateCount
	): array {
		return [
			'status' => 'time_budget_reached',
			'commit' => '',
			'path' => $path,
			'vendor_version' => $vendorVersion,
			'commits_examined' => $examined,
			'history_truncated' => $historyTruncated,
			'candidate_count' => $candidateCount,
			'distinct_candidate_count' => $distinctCandidateCount,
			'exact_match_count' => 0,
			'selection' => 'continue_request_bounded_scan',
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
