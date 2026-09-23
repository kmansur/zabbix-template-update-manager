<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use CImportReaderFactory;
use Modules\ZabbixTemplateUpdateManager\Repository\HistoricalBaselineCacheRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/Repository/HistoricalBaselineCacheRepository.php';
require_once dirname(__DIR__).'/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__).'/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamTemplateHistoryRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamTemplateSourceRepository.php';
require_once __DIR__.'/ContentComparisonClassifier.php';
require_once __DIR__.'/HistoricalTemplateBaselineService.php';
require_once __DIR__.'/ImportCompareSummary.php';
require_once __DIR__.'/TemplateBackupVerificationService.php';
require_once __DIR__.'/TemplateExportService.php';
require_once __DIR__.'/TemplateImportCompareService.php';
require_once __DIR__.'/TemplateInventoryService.php';
require_once __DIR__.'/TemplateVersionComparator.php';
require_once __DIR__.'/ThreeWayChangeAnalyzer.php';
require_once __DIR__.'/UpdatePreviewAnalyzer.php';
require_once __DIR__.'/UpdateReadinessEvaluator.php';
require_once __DIR__.'/UpdateRiskAnalyzer.php';
require_once __DIR__.'/UpstreamMatcher.php';
require_once __DIR__.'/UpstreamTemplateDocumentService.php';
require_once dirname(__DIR__).'/Support/ZabbixVersion.php';

/**
 * Rebuilds the complete read-only update analysis for one visible template.
 *
 * The service is deliberately reusable by both the comparison page and future
 * server-side preflight actions. It never imports or mutates Zabbix
 * configuration.
 */
final class TemplateUpdateAnalysisService {

	public function analyze(string $templateId): array {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for update analysis.');
		}

		$data = $this->emptyResult();
		$data['zabbix_version'] = ZabbixVersion::current();

		if (!ZabbixVersion::isSupported($data['zabbix_version'])) {
			$data['comparison_error'] = _(
				'Content comparison is disabled on unsupported or undetected Zabbix versions.'
			);
			return $data;
		}

		try {
			$record = (new TemplateRepository())->findById($templateId);
			if ($record === null) {
				throw new RuntimeException('The requested template is not visible to the current user.');
			}

			$inventory = TemplateInventoryService::fromRecords([$record]);
			$template = $inventory['templates'][0] ?? null;
			if (!is_array($template)) {
				throw new RuntimeException('Unable to normalize the requested template.');
			}

			$index = (new UpstreamIndexRepository())->load($data['zabbix_version']);
			$matched = UpstreamMatcher::attach([$template], $index);
			$versioned = TemplateVersionComparator::attach($matched['templates']);
			$template = $versioned['templates'][0];
			$data['template'] = $template;
			$data['upstream_source'] = $index['source'] ?? null;

			if (($template['upstream_status'] ?? null) !== 'official_match'
					|| !is_array($template['upstream'] ?? null)) {
				$data['comparison_error'] = _(
					'Content comparison is available only for templates with an authoritative official UUID match.'
				);
				return $data;
			}

			$sourceRepository = new UpstreamTemplateSourceRepository();
			$sourceFile = $sourceRepository->fetch($index['source'], $template['upstream']);
			$reader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
			$document = $reader->read($sourceFile['content']);
			$isolated = UpstreamTemplateDocumentService::buildImportSource(
				$document,
				$template['uuid'],
				$template['upstream'],
				true
			);
			$data['external_template_names'] = is_array($isolated['external_template_names'] ?? null)
				? array_values(array_map('strval', $isolated['external_template_names']))
				: [];

			$compareService = new TemplateImportCompareService();
			$currentDiff = $compareService->compare($isolated['source']);
			$data['comparison_summary'] = ImportCompareSummary::summarize($currentDiff);
			$data['content_status'] = ContentComparisonClassifier::classify(
				$template['version_status'],
				$data['comparison_summary']
			);
			$data['source_path'] = $sourceFile['path'];

			if (($template['version_status'] ?? null) === 'update_available') {
				try {
					$data['update_preview'] = UpdatePreviewAnalyzer::analyze($currentDiff);
				}
				catch (Throwable $exception) {
					$this->logFailure('Update preview analysis', $templateId, $exception);
					$data['update_risk_error'] = _(
						'Unable to normalize the current update preview for risk analysis. The native import comparison summary remains available.'
					).' '.$this->diagnosticMessage($exception);
				}
			}

			if (($template['version_status'] ?? null) === 'update_available'
					&& ($template['vendor_version'] ?? '') !== '') {
				$this->resolveHistoricalAnalysis(
					$data,
					$template,
					$index,
					$sourceFile,
					$sourceRepository,
					$compareService,
					$currentDiff,
					$templateId
				);
			}

			if (is_array($data['update_preview'])) {
				try {
					$data['update_risk'] = UpdateRiskAnalyzer::assess(
						$data['update_preview'],
						$data['three_way_analysis'],
						(int) ($template['host_count'] ?? 0)
					);
				}
				catch (Throwable $exception) {
					$this->logFailure('Update risk analysis', $templateId, $exception);
					$data['update_risk_error'] = _(
						'Unable to complete conservative update risk analysis. No update-safety conclusion is available.'
					).' '.$this->diagnosticMessage($exception);
				}
			}

			$data['update_readiness'] = UpdateReadinessEvaluator::evaluate(
				$template,
				$data['historical_baseline'],
				$data['three_way_analysis'],
				$data['update_preview'],
				$data['update_risk']
			);

			if (!empty($data['update_readiness']['candidate_for_backup'])) {
				$data = $this->refreshBackupVerification($data);
			}
		}
		catch (Throwable $exception) {
			$this->logFailure('Content comparison', $templateId, $exception);
			$data['comparison_error'] = _(
				'Unable to complete the read-only content comparison. Check frontend logs, network access to the official Zabbix repository and the current user role permissions.'
			).' '.$this->diagnosticMessage($exception);
		}

		return $data;
	}

	/**
	 * Refreshes only rollback-artifact evidence for an already completed
	 * read-only analysis. Creating a backup changes local artifact state, not
	 * Zabbix configuration or upstream/template comparison state, so batch
	 * preparation can safely avoid repeating history/source/importcompare work.
	 *
	 * Controlled update execution does not rely on this shortcut: it still runs
	 * a complete fresh preflight immediately before the configuration import.
	 */
	public function refreshBackupVerification(array $analysis): array {
		$template = is_array($analysis['template'] ?? null) ? $analysis['template'] : [];
		$readiness = is_array($analysis['update_readiness'] ?? null) ? $analysis['update_readiness'] : [];
		if ($template === [] || empty($readiness['candidate_for_backup'])) {
			return $analysis;
		}

		$templateId = trim((string) ($template['templateid'] ?? ''));
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for backup verification refresh.');
		}

		try {
			$analysis['backup_verification'] = (new TemplateBackupVerificationService(
				new TemplateExportService(),
				new TemplateBackupRepository()
			))->verifyCurrent($template);

			$analysis['update_readiness'] = UpdateReadinessEvaluator::evaluate(
				$template,
				is_array($analysis['historical_baseline'] ?? null) ? $analysis['historical_baseline'] : null,
				is_array($analysis['three_way_analysis'] ?? null) ? $analysis['three_way_analysis'] : null,
				is_array($analysis['update_preview'] ?? null) ? $analysis['update_preview'] : null,
				is_array($analysis['update_risk'] ?? null) ? $analysis['update_risk'] : null,
				$analysis['backup_verification']
			);
			$analysis['backup_verification_error'] = null;
		}
		catch (Throwable $exception) {
			$this->logFailure('Backup verification', $templateId, $exception);
			$analysis['backup_verification_error'] = _(
				'Unable to inspect or verify the persistent rollback backup. The workflow remains at backup candidacy and no configuration-write step is enabled.'
			).' '.$this->diagnosticMessage($exception);
		}

		return $analysis;
	}

	private function resolveHistoricalAnalysis(
		array &$data,
		array $template,
		array $index,
		array $sourceFile,
		UpstreamTemplateSourceRepository $sourceRepository,
		TemplateImportCompareService $compareService,
		array $currentDiff,
		string $templateId
	): void {
		try {
			$currentCommit = (string) ($index['source']['commit'] ?? '');
			$vendorName = (string) ($template['upstream']['vendor_name'] ?? 'Zabbix');
			$baselineCache = new HistoricalBaselineCacheRepository();
			$baseline = $baselineCache->load(
				$sourceFile['path'],
				$currentCommit,
				$template['uuid'],
				$template['vendor_version'],
				$vendorName
			);
			$cacheStatus = 'hit';

			if ($baseline === null) {
				$cacheStatus = 'miss';
				$historyRepository = new UpstreamTemplateHistoryRepository();
				$baselineService = new HistoricalTemplateBaselineService(
					static fn(string $path, string $until, int $limit): array
						=> $historyRepository->listCommits($path, $until, $limit),
					static fn(string $commit, string $path): array
						=> $sourceRepository->fetchAtCommit($commit, $path),
					static function (string $source): array {
						$historicalReader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
						return $historicalReader->read($source);
					},
					static fn(string $commit, string $path): ?string
						=> $historyRepository->previousPathAtCommit($commit, $path)
				);

				$baseline = $baselineService->find(
					$sourceFile['path'],
					$currentCommit,
					$template['uuid'],
					$template['vendor_version'],
					$vendorName,
					75,
					null,
					true
				);

				if (($baseline['status'] ?? null) === 'found') {
					$baselineCache->store(
						$sourceFile['path'],
						$currentCommit,
						$template['uuid'],
						$template['vendor_version'],
						$vendorName,
						$baseline
					);
				}
			}

			$baselineSource = $baseline['source'] ?? null;
			unset($baseline['source']);
			$baseline['cache_status'] = $cacheStatus;
			$data['historical_baseline'] = $baseline;

			if (($baseline['status'] ?? null) === 'found' && is_string($baselineSource)) {
				$historicalDiff = $compareService->compare($baselineSource);
				$data['historical_summary'] = ImportCompareSummary::summarize($historicalDiff);

				try {
					$data['three_way_analysis'] = ThreeWayChangeAnalyzer::analyze(
						$historicalDiff,
						$currentDiff
					);
				}
				catch (Throwable $exception) {
					$this->logFailure('Three-way analysis', $templateId, $exception);
					$data['three_way_error'] = _(
						'Unable to complete the three-way change analysis. Historical and current comparison summaries remain available.'
					).' '.$this->diagnosticMessage($exception);
				}
			}

			$data['content_status'] = ContentComparisonClassifier::classify(
				$template['version_status'],
				$data['comparison_summary'],
				(string) ($baseline['status'] ?? ''),
				$data['historical_summary']
			);
		}
		catch (Throwable $exception) {
			$this->logFailure('Historical baseline lookup', $templateId, $exception);
			$data['historical_error'] = _(
				'Unable to resolve the historical official baseline. The current-upstream comparison remains valid as an update preview.'
			).' '.$this->diagnosticMessage($exception);
		}
	}

	private function emptyResult(): array {
		return [
			'zabbix_version' => '',
			'template' => null,
			'upstream_source' => null,
			'source_path' => '',
			'external_template_names' => [],
			'comparison_summary' => ImportCompareSummary::summarize([]),
			'content_status' => 'not_available',
			'comparison_error' => null,
			'historical_baseline' => null,
			'historical_summary' => ImportCompareSummary::summarize([]),
			'historical_error' => null,
			'three_way_analysis' => null,
			'three_way_error' => null,
			'update_preview' => null,
			'update_risk' => null,
			'update_risk_error' => null,
			'update_readiness' => null,
			'backup_verification' => null,
			'backup_verification_error' => null
		];
	}

	private function diagnosticMessage(Throwable $exception): string {
		$message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $exception->getMessage());
		$message = is_string($message) ? trim($message) : '';
		$message = preg_replace('/\s+/', ' ', $message);
		$message = is_string($message) ? trim($message) : '';
		if ($message === '') {
			$message = get_class($exception);
		}

		if (strlen($message) > 400) {
			$message = substr($message, 0, 399).'…';
		}

		return 'Diagnostic: '.$message;
	}

	private function logFailure(string $stage, string $templateId, Throwable $exception): void {
		error_log(sprintf(
			'[Zabbix Template Update Manager] %s failed for template %s: %s',
			$stage,
			$templateId,
			$exception->getMessage()
		));
	}
}
