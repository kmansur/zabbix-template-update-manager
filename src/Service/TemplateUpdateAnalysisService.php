<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use CImportReaderFactory;
use Modules\ZabbixTemplateUpdateManager\Repository\HistoricalBaselineCacheRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateUpdatePolicyRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/Repository/HistoricalBaselineCacheRepository.php';
require_once dirname(__DIR__).'/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__).'/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/Repository/TemplateUpdatePolicyRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamTemplateHistoryRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamTemplateSourceRepository.php';
require_once __DIR__.'/ContentComparisonClassifier.php';
require_once __DIR__.'/HistoricalTemplateBaselineService.php';
require_once __DIR__.'/HistoricalDashboardCandidateCorrelation.php';
require_once __DIR__.'/HistoricalDashboardResidualDiagnostic.php';
require_once __DIR__.'/HistoricalDashboardSemanticReconciliation.php';
require_once __DIR__.'/ImportCompareSummary.php';
require_once __DIR__.'/TemplateBackupVerificationService.php';
require_once __DIR__.'/TemplateExportService.php';
require_once __DIR__.'/TemplateImportCompareService.php';
require_once __DIR__.'/TemplateDashboardEffectiveStateDiagnostic.php';
require_once __DIR__.'/TemplateInventoryService.php';
require_once __DIR__.'/TemplateHostImpactService.php';
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

			try {
				$policyTemplates = (new TemplateUpdatePolicyRepository())->annotate([$template]);
				$template = $policyTemplates[0] ?? $template;
				$data['update_policy'] = (string) ($template['update_policy'] ?? 'managed');
			}
			catch (Throwable $exception) {
				$this->logFailure('Update policy lookup', $templateId, $exception);
				$template['update_policy'] = 'unavailable';
				$template['never_update'] = false;
				$data['update_policy'] = 'unavailable';
				$data['policy_error'] = _(
					'Unable to read the template update policy. Update execution remains blocked until the policy store is available.'
				);
			}

			$data['template'] = $template;
			$data['upstream_source'] = $index['source'] ?? null;

			try {
				$data['host_impact'] = (new TemplateHostImpactService())->analyze($templateId);
			}
			catch (Throwable $exception) {
				$this->logFailure('Host impact analysis', $templateId, $exception);
				$data['host_impact_error'] = _(
					'Unable to resolve inherited template-to-host impact. Direct host count remains available.'
				).' '.$this->diagnosticMessage($exception);
			}

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
						(int) ($template['host_count'] ?? 0),
						$data['host_impact']
					);
				}
				catch (Throwable $exception) {
					$this->logFailure('Update risk analysis', $templateId, $exception);
					$data['update_risk_error'] = _(
						'Unable to complete conservative update risk analysis. No update-safety conclusion is available.'
					).' '.$this->diagnosticMessage($exception);
				}
			}

			$data['update_readiness'] = $this->applyUpdatePolicyGate(
				UpdateReadinessEvaluator::evaluate(
					$template,
					$data['historical_baseline'],
					$data['three_way_analysis'],
					$data['update_preview'],
					$data['update_risk']
				),
				(string) ($data['update_policy'] ?? 'managed'),
				$data['policy_error']
			);

			if (is_array($data['update_readiness']) && is_array($data['host_impact'])) {
				$data['update_readiness']['direct_host_count'] = (int) ($data['host_impact']['direct_host_count'] ?? 0);
				$data['update_readiness']['indirect_host_count'] = (int) ($data['host_impact']['indirect_host_count'] ?? 0);
				$data['update_readiness']['total_host_count'] = (int) ($data['host_impact']['total_host_count'] ?? 0);
				$data['update_readiness']['dependent_template_count'] = (int) ($data['host_impact']['dependent_template_count'] ?? 0);
			}

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

			$analysis['update_readiness'] = $this->applyUpdatePolicyGate(
				UpdateReadinessEvaluator::evaluate(
					$template,
					is_array($analysis['historical_baseline'] ?? null) ? $analysis['historical_baseline'] : null,
					is_array($analysis['three_way_analysis'] ?? null) ? $analysis['three_way_analysis'] : null,
					is_array($analysis['update_preview'] ?? null) ? $analysis['update_preview'] : null,
					is_array($analysis['update_risk'] ?? null) ? $analysis['update_risk'] : null,
					$analysis['backup_verification']
				),
				(string) ($analysis['update_policy'] ?? 'managed'),
				$analysis['policy_error'] ?? null
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

	private function applyUpdatePolicyGate(array $readiness, string $policy, ?string $policyError): array {
		if ($policy === TemplateUpdatePolicyRepository::POLICY_MANAGED && $policyError === null) {
			return $readiness;
		}

		$readiness['status'] = 'blocked_update_policy';
		$readiness['next_step'] = $policy === TemplateUpdatePolicyRepository::POLICY_NEVER_UPDATE
			? 'allow_updates'
			: 'resolve_update_policy';
		$readiness['candidate_for_backup'] = false;
		$readiness['backup_verified'] = false;
		$readiness['manual_confirmation_required'] = false;
		$readiness['manual_reasons'] = [];
		$readiness['write_enabled'] = false;
		$readiness['review_flags'] = [];
		$readiness['blockers'] = [
			$policy === TemplateUpdatePolicyRepository::POLICY_NEVER_UPDATE
				? 'update_policy_never'
				: 'update_policy_unavailable'
		];

		return $readiness;
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
				try {
					$baseline = $this->resolveInitialReleaseBaseline(
						$template,
						$data['zabbix_version'],
						$sourceFile['path'],
						$vendorName
					);
				}
				catch (Throwable $exception) {
					$this->logFailure('Initial-release baseline lookup', $templateId, $exception);
					$baseline = null;
				}

				$historyRepository = null;
				if ($baseline === null) {
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
						true,
						12.0,
						static function (string $source, string $commit) use ($templateId): array {
							$rows = \API::TemplateDashboard()->get([
								'templateids' => [$templateId],
								'output' => ['dashboardid', 'templateid', 'uuid', 'auto_start', 'display_period']
							]);
							if (!is_array($rows)) {
								return ['unverified' => 1, 'complete' => false];
							}
							foreach ($rows as $row) {
								if (!is_array($row) || (string) ($row['templateid'] ?? '') !== $templateId) {
									return ['unverified' => 1, 'complete' => false];
								}
							}
							$correlation = HistoricalDashboardCandidateCorrelation::compare($source, $rows);
							$nativeDiff = (new TemplateImportCompareService())->compare($source);
							$preview = UpdatePreviewAnalyzer::analyze($nativeDiff, 100);
							$correlation['residual'] = HistoricalDashboardResidualDiagnostic::assess($preview, $correlation);
							$correlation['semantic_reconciliation'] = HistoricalDashboardSemanticReconciliation::assess(
								ImportCompareSummary::summarize($nativeDiff), $preview, $correlation
							);
							return $correlation;
						}
					);
				}

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
			// Keep fail-closed behavior, but record enough provenance to distinguish
			// unavailable, ambiguous, truncated and time-budget-limited scans.
			if (($baseline['status'] ?? null) === 'ambiguous') {
				// Independently inspect effective values via the native read-only API.
				// This evidence never affects baseline selection or write readiness.
				try {
					$effective = TemplateDashboardEffectiveStateDiagnostic::inspect($templateId);
					error_log(sprintf(
						'[Zabbix Template Update Manager] Historical effective dashboard state for template %s: count=%d auto_start_no=%d auto_start_yes=%d auto_start_unknown=%d display_period_known=%d identity_complete=%s (read-only; baseline remains blocked)',
						$templateId,
						(int) $effective['count'],
						(int) $effective['auto_start_no'],
						(int) $effective['auto_start_yes'],
						(int) $effective['auto_start_unknown'],
						(int) $effective['display_period_known'],
						!empty($effective['identity_complete']) ? 'yes' : 'no'
					));
				}
				catch (Throwable $exception) {
					error_log(sprintf(
						'[Zabbix Template Update Manager] Historical effective dashboard state unavailable for template %s: %s (baseline remains blocked)',
						$templateId,
						get_class($exception)
					));
				}
				foreach ((array) ($baseline['candidate_audit'] ?? []) as $candidate) {
					$correlation = $candidate['dashboard_correlation'] ?? [];
					$residual = is_array($correlation) ? ($correlation['residual'] ?? []) : [];
					$semantic = is_array($correlation) ? ($correlation['semantic_reconciliation'] ?? []) : [];
					if (is_array($semantic) && $semantic !== []) {
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical semantic reconciliation for template %s: commit=%s status=%s native_changes=%s preview_operations=%d reconciled_operations=%d remaining_operations=%s truncated=%s (diagnostic only; no baseline selection)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							in_array($semantic['status'] ?? '', ['unverified', 'representation_only_candidate'], true) ? $semantic['status'] : 'unverified',
							isset($semantic['native_changes']) ? (string) $semantic['native_changes'] : 'unknown',
							(int) ($semantic['preview_operations'] ?? 0),
							(int) ($semantic['reconciled_operations'] ?? 0),
							isset($semantic['remaining_operations']) ? (string) $semantic['remaining_operations'] : 'unknown',
							!empty($semantic['truncated']) ? 'yes' : 'no'
						));
					}

					if (is_array($residual) && $residual !== []) {
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical dashboard residual for template %s: commit=%s status=%s direct_changes=%d target_missing_to_no=%d other_changes=%d truncated=%s (diagnostic only; baseline remains blocked)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							in_array($residual['status'] ?? '', ['unverified', 'dashboard_snapshot_omission_correlated'], true) ? $residual['status'] : 'unverified',
							(int) ($residual['direct_changes'] ?? 0),
							(int) ($residual['target_missing_to_no'] ?? 0),
							(int) ($residual['other_changes'] ?? 0),
							!empty($residual['details_truncated']) ? 'yes' : 'no'
						));
					}

					if (is_array($correlation) && $correlation !== []) {
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical dashboard candidate correlation for template %s: commit=%s candidate=%d local=%d matched=%d unverified=%d complete=%s (diagnostic only; baseline remains blocked)',
							$templateId, (string) ($candidate['commit'] ?? ''),
							(int) ($correlation['candidate'] ?? 0),
							(int) ($correlation['local'] ?? 0),
							(int) ($correlation['matched'] ?? 0),
							(int) ($correlation['unverified'] ?? 0),
							!empty($correlation['complete']) ? 'yes' : 'no'
						));
					}

					if (!is_array($candidate)) {
						continue;
					}
					error_log(sprintf(
						'[Zabbix Template Update Manager] Historical candidate for template %s: commit=%s sha256=%s revisions=%d semantic_changes=%s (diagnostic only)',
						$templateId,
						(string) ($candidate['commit'] ?? ''),
						(string) ($candidate['source_sha256'] ?? ''),
						(int) ($candidate['commit_count'] ?? 0),
						isset($candidate['semantic_distance']) ? (string) $candidate['semantic_distance'] : 'unknown'
					));
					foreach ((array) ($candidate['auto_start_unknown_shapes'] ?? []) as $shape) {
						if (!is_array($shape)) {
							continue;
						}
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical auto_start shape for template %s: commit=%s before_present=%s before_type=%s before_known=%s before_shape=%s before_count=%d after_present=%s after_type=%s after_known=%s after_shape=%s after_count=%d (values withheld; diagnostic only)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							!empty($shape['before_present']) ? 'yes' : 'no',
							(string) ($shape['before_type'] ?? 'other'),
							!empty($shape['before_known']) ? 'yes' : 'no',
							(string) ($shape['before_shape'] ?? 'not_array'),
							(int) ($shape['before_count'] ?? 0),
							!empty($shape['after_present']) ? 'yes' : 'no',
							(string) ($shape['after_type'] ?? 'other'),
							!empty($shape['after_known']) ? 'yes' : 'no',
							(string) ($shape['after_shape'] ?? 'not_array'),
							(int) ($shape['after_count'] ?? 0)
						));
					}
					$missing = $candidate['auto_start_missing_transitions'] ?? [];
					if (is_array($missing) && $missing !== []) {
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical auto_start missing transition for template %s: commit=%s missing_before_to_no=%d missing_before_to_yes=%d missing_before_to_unknown=%d missing_after=%d truncated=%s (snapshot absence is NOT a verified API default; baseline remains blocked)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							(int) ($missing['missing_before_to_no'] ?? 0),
							(int) ($missing['missing_before_to_yes'] ?? 0),
							(int) ($missing['missing_before_to_unknown'] ?? 0),
							(int) ($missing['missing_after'] ?? 0),
							!empty($missing['truncated']) ? 'yes' : 'no'
						));
					}
					$representation = $candidate['auto_start_diagnostic'] ?? [];
					if (is_array($representation) && $representation !== []) {
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical dashboard auto_start diagnostic for template %s: commit=%s equivalent=%d different=%d unknown=%d truncated=%s (read-only; baseline remains blocked)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							(int) ($representation['equivalent'] ?? 0),
							(int) ($representation['different'] ?? 0),
							(int) ($representation['unknown'] ?? 0),
							!empty($representation['truncated']) ? 'yes' : 'no'
						));
					}
					$structure = $candidate['change_structure'] ?? [];
					if (is_array($structure) && $structure !== []) {
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical candidate structure for template %s: commit=%s direct_fields=%d entity_additions=%d entity_removals=%d unresolved_identity=%d truncated=%s (diagnostic only)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							(int) ($structure['direct_fields'] ?? 0),
							(int) ($structure['entity_additions'] ?? 0),
							(int) ($structure['entity_removals'] ?? 0),
							(int) ($structure['unresolved_identity'] ?? 0),
							!empty($structure['details_truncated']) ? 'yes' : 'no'
						));
					}
					foreach ((array) ($candidate['field_names'] ?? []) as $field => $count) {
						if (!is_string($field) || !is_int($count) || $count < 0) {
							continue;
						}
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical candidate safe field for template %s: commit=%s field=%s changes=%d (diagnostic only; values withheld)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							$field,
							$count
						));
					}
					foreach ((array) ($candidate['change_categories'] ?? []) as $entity => $counts) {
						if (!is_string($entity) || !is_array($counts)) {
							continue;
						}
						error_log(sprintf(
							'[Zabbix Template Update Manager] Historical candidate category for template %s: commit=%s entity=%s added=%d updated=%d removed=%d (diagnostic only)',
							$templateId,
							(string) ($candidate['commit'] ?? ''),
							$entity,
							(int) ($counts['added'] ?? 0),
							(int) ($counts['updated'] ?? 0),
							(int) ($counts['removed'] ?? 0)
						));
					}
				}
			}
			if (($baseline['status'] ?? null) !== 'found') {
				error_log(sprintf(
					'[Zabbix Template Update Manager] Historical baseline unresolved for template %s: status=%s selection=%s examined=%d candidates=%d distinct=%d truncated=%s cache=%s',
					$templateId,
					(string) ($baseline['status'] ?? 'unknown'),
					(string) ($baseline['selection'] ?? 'unknown'),
					(int) ($baseline['commits_examined'] ?? 0),
					(int) ($baseline['candidate_count'] ?? 0),
					(int) ($baseline['distinct_candidate_count'] ?? 0),
					!empty($baseline['history_truncated']) ? 'yes' : 'no',
					$cacheStatus
				));
			}

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

	private function resolveInitialReleaseBaseline(
		array $template,
		string $zabbixVersion,
		string $currentPath,
		string $expectedVendorName
	): ?array {
		$line = ZabbixVersion::line($zabbixVersion);
		$targetVersion = trim((string) ($template['vendor_version'] ?? ''));
		$uuid = strtolower(str_replace('-', '', trim((string) ($template['uuid'] ?? ''))));

		if ($line === null
				|| $targetVersion !== $line.'-0'
				|| preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
			return null;
		}

		$index = (new UpstreamIndexRepository())->loadInitialRelease($zabbixVersion);
		$record = $index['templates'][$uuid] ?? null;
		if (!is_array($record)) {
			return null;
		}

		if ((string) ($record['vendor_version'] ?? '') !== $targetVersion
				|| ($expectedVendorName !== ''
					&& (string) ($record['vendor_name'] ?? '') !== $expectedVendorName)) {
			return null;
		}

		$sourceFile = (new UpstreamTemplateSourceRepository())->fetch($index['source'], $record);
		$reader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
		$document = $reader->read($sourceFile['content']);
		$isolated = UpstreamTemplateDocumentService::buildHistoricalImportSource(
			$document,
			$uuid,
			$targetVersion,
			$expectedVendorName,
			true
		);

		return [
			'status' => 'found',
			'commit' => (string) ($index['source']['commit'] ?? ''),
			'path' => $currentPath,
			'vendor_version' => $targetVersion,
			'commits_examined' => 0,
			'history_truncated' => false,
			'candidate_count' => 1,
			'distinct_candidate_count' => 1,
			'exact_match_count' => 0,
			'selection' => 'initial_release_index',
			'semantic_distance' => null,
			'source' => $isolated['source']
		];
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
			'host_impact' => null,
			'host_impact_error' => null,
			'update_policy' => 'managed',
			'policy_error' => null,
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
