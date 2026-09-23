<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;
use Throwable;

require_once __DIR__.'/TemplateBackupService.php';
require_once __DIR__.'/TemplateUpdateAnalysisService.php';
require_once __DIR__.'/TemplateUpdatePreflightService.php';

/**
 * Builds an authoritative request-bounded batch plan from per-template safety gates.
 *
 * Optional preparation may create local rollback artifacts, but this service
 * never imports or changes Zabbix configuration.
 */
final class TemplateBatchPlanService {

	public const MAX_TEMPLATES = 500;

	private $analysisRunner;
	private $backupCreator;
	private $preflightRunner;

	public function __construct(
		?callable $analysisRunner = null,
		?callable $backupCreator = null,
		?callable $preflightRunner = null
	) {
		$this->analysisRunner = $analysisRunner ?? static fn(string $templateId): array
			=> (new TemplateUpdateAnalysisService())->analyze($templateId);
		$this->backupCreator = $backupCreator ?? static fn(array $template): array
			=> (new TemplateBackupService())->create($template);
		$this->preflightRunner = $preflightRunner;
	}

	public function build(array $templateIds, bool $prepareBackups = false): array {
		$templateIds = $this->normalizeIds($templateIds);
		$result = [
			'items' => [],
			'summary' => [
				'selected' => count($templateIds),
				'ready' => 0,
				'review' => 0,
				'conflict' => 0,
				'blocked' => 0
			],
			'write_enabled' => false
		];

		foreach ($templateIds as $templateId) {
			$item = $this->buildItem($templateId, $prepareBackups);
			$result['items'][] = $item;
			$category = (string) ($item['category'] ?? 'blocked');
			if (array_key_exists($category, $result['summary'])) {
				$result['summary'][$category]++;
			}
		}

		return $result;
	}

	private function buildItem(string $templateId, bool $prepareBackups): array {
		try {
			$analysis = ($this->analysisRunner)($templateId);
			if (!is_array($analysis)) {
				throw new RuntimeException('Template analysis returned an invalid result.');
			}

			$template = is_array($analysis['template'] ?? null) ? $analysis['template'] : [];
			$readiness = is_array($analysis['update_readiness'] ?? null) ? $analysis['update_readiness'] : [];
			$status = (string) ($readiness['status'] ?? 'blocked_unresolved');

			if ($prepareBackups && !empty($readiness['candidate_for_backup']) && $template !== []) {
				($this->backupCreator)($template);
				$analysis = ($this->analysisRunner)($templateId);
				$template = is_array($analysis['template'] ?? null) ? $analysis['template'] : [];
				$readiness = is_array($analysis['update_readiness'] ?? null) ? $analysis['update_readiness'] : [];
				$status = (string) ($readiness['status'] ?? 'blocked_unresolved');
			}

			$manualReasons = is_array($readiness['manual_reasons'] ?? null)
				? array_values(array_map('strval', $readiness['manual_reasons']))
				: [];

			$item = [
				'templateid' => $templateId,
				'name' => (string) ($template['name'] ?? $templateId),
				'installed_version' => (string) ($template['vendor_version'] ?? ''),
				'available_version' => (string) ($template['upstream_vendor_version'] ?? ''),
				'host_count' => max(0, (int) ($template['host_count'] ?? 0)),
				'readiness_status' => $status,
				'next_step' => (string) ($readiness['next_step'] ?? 'none'),
				'category' => $this->classify($status),
				'evidence_sha256' => '',
				'manual_evidence_sha256' => '',
				'manual_reasons' => $manualReasons,
				'batch_manual_eligible' => false,
				'batch_manual_requires_local_overwrite_ack' => false,
				'reason' => $this->reason($analysis, $readiness),
				'backup_prepared' => $prepareBackups && !empty($analysis['backup_verification'])
			];

			if ($status === 'backup_verified') {
				$preflight = $this->runPreparedPreflight($templateId, false, $analysis);
				if (is_array($preflight) && ($preflight['status'] ?? null) === 'passed') {
					$evidence = strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')));
					if (preg_match('/^[a-f0-9]{64}$/', $evidence)) {
						$item['category'] = 'ready';
						$item['evidence_sha256'] = $evidence;
						$item['reason'] = '';
					}
					else {
						$item['category'] = 'blocked';
						$item['reason'] = 'invalid_preflight_evidence';
					}
				}
				else {
					$item['category'] = 'blocked';
					$item['reason'] = is_array($preflight)
						? (string) ($preflight['reason'] ?? 'preflight_not_passed')
						: 'invalid_preflight_result';
				}
			}
			elseif ($status === 'review_backup_verified'
					&& $this->isBatchManualEligible($manualReasons)) {
				$preflight = $this->runPreparedPreflight($templateId, true, $analysis);
				$evidence = is_array($preflight)
					? strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')))
					: '';

				if (is_array($preflight)
						&& ($preflight['status'] ?? null) === 'passed'
						&& !empty($preflight['manual_override'])
						&& preg_match('/^[a-f0-9]{64}$/', $evidence)) {
					$item['batch_manual_eligible'] = true;
					$item['batch_manual_requires_local_overwrite_ack'] = in_array(
						'local_customization_overwrite',
						$manualReasons,
						true
					);
					$item['manual_evidence_sha256'] = $evidence;
				}
			}

			return $item;
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Batch plan failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));

			return [
				'templateid' => $templateId,
				'name' => $templateId,
				'installed_version' => '',
				'available_version' => '',
				'host_count' => 0,
				'readiness_status' => 'blocked_error',
				'next_step' => 'inspect_error',
				'category' => 'blocked',
				'evidence_sha256' => '',
				'manual_evidence_sha256' => '',
				'manual_reasons' => [],
				'batch_manual_eligible' => false,
				'batch_manual_requires_local_overwrite_ack' => false,
				'reason' => 'analysis_exception',
				'backup_prepared' => false
			];
		}
	}

	/**
	 * Batch preparation already owns a fresh analysis snapshot for this request.
	 * Reusing that exact snapshot to derive preparation evidence avoids a second
	 * complete read-only analysis (network/history/importcompare) in the same
	 * HTTP request. The controlled write path does not use this helper and still
	 * reruns an authoritative fresh preflight immediately before import.
	 */
	private function runPreparedPreflight(string $templateId, bool $manualOverride, array $analysis): array {
		if ($this->preflightRunner !== null) {
			$result = ($this->preflightRunner)($templateId, $manualOverride);
			if (!is_array($result)) {
				throw new RuntimeException('Batch preparation preflight returned an invalid result.');
			}
			return $result;
		}

		$service = new TemplateUpdatePreflightService(
			static fn(string $requestedTemplateId): array => $analysis
		);
		return $service->run($templateId, $manualOverride);
	}

	private function normalizeIds(array $templateIds): array {
		$normalized = [];
		foreach ($templateIds as $templateId) {
			$templateId = trim((string) $templateId);
			if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
				throw new RuntimeException('Batch planning requires valid numeric template IDs.');
			}
			$normalized[$templateId] = true;
		}

		$ids = array_map('strval', array_keys($normalized));
		if ($ids === [] || count($ids) > self::MAX_TEMPLATES) {
			throw new RuntimeException('Batch planning requires between 1 and '.self::MAX_TEMPLATES.' templates.');
		}

		return $ids;
	}

	private function isBatchManualEligible(array $manualReasons): bool {
		if ($manualReasons === []) {
			return false;
		}

		$allowed = ['medium_technical_risk', 'high_technical_risk', 'local_customization_overwrite'];
		foreach ($manualReasons as $reason) {
			if (!in_array($reason, $allowed, true)) {
				return false;
			}
		}

		return true;
	}

	private function classify(string $status): string {
		if ($status === 'backup_verified') {
			return 'blocked';
		}
		if (in_array($status, ['review_medium', 'review_high', 'review_required', 'review_backup_verified'], true)) {
			return 'review';
		}
		if (in_array($status, ['blocked_conflict', 'blocked_local_overwrite'], true)) {
			return 'conflict';
		}
		return 'blocked';
	}

	private function reason(array $analysis, array $readiness): string {
		$blockers = is_array($readiness['blockers'] ?? null) ? $readiness['blockers'] : [];
		if ($blockers !== []) {
			return implode(', ', array_map('strval', $blockers));
		}

		$flags = is_array($readiness['review_flags'] ?? null) ? $readiness['review_flags'] : [];
		if ($flags !== []) {
			return implode(', ', array_map('strval', $flags));
		}

		if (($analysis['comparison_error'] ?? null) !== null) {
			$message = preg_replace('/\s+/', ' ', trim((string) $analysis['comparison_error']));
			$message = is_string($message) ? $message : '';
			if (strlen($message) > 500) {
				$message = substr($message, 0, 499).'…';
			}
			return $message !== '' ? 'comparison_error: '.$message : 'comparison_error';
		}

		return (string) ($readiness['next_step'] ?? 'not_ready');
	}
}
