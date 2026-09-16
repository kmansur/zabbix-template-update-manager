<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;
use Throwable;

require_once __DIR__.'/TemplateBatchPlanService.php';
require_once __DIR__.'/TemplateControlledUpdateService.php';

/**
 * Executes a bounded set of already-reviewed updates sequentially.
 *
 * Every template reuses TemplateControlledUpdateService, which reruns fresh
 * preflight and enforces the single approved configuration.import boundary.
 * Execution stops immediately on the first non-success or exception.
 */
final class TemplateBatchUpdateService {

	private $executor;

	public function __construct(?callable $executor = null) {
		$this->executor = $executor ?? static fn(string $templateId, string $evidence): array
			=> (new TemplateControlledUpdateService())->execute($templateId, $evidence);
	}

	public function execute(array $templateIds, array $evidenceByTemplate): array {
		$templateIds = $this->normalizeIds($templateIds);
		$result = [
			'status' => 'completed',
			'updated' => [],
			'failed' => null,
			'not_attempted' => [],
			'write_performed' => false
		];

		foreach ($templateIds as $position => $templateId) {
			$evidence = strtolower(trim((string) ($evidenceByTemplate[$templateId] ?? '')));
			if (!preg_match('/^[a-f0-9]{64}$/', $evidence)) {
				$result['status'] = 'stopped';
				$result['failed'] = [
					'templateid' => $templateId,
					'status' => 'invalid_evidence',
					'write_performed' => false
				];
				$result['not_attempted'] = array_slice($templateIds, $position + 1);
				break;
			}

			try {
				$update = ($this->executor)($templateId, $evidence);
				if (!is_array($update)) {
					throw new RuntimeException('Controlled update returned an invalid result.');
				}

				if (!empty($update['write_performed'])) {
					$result['write_performed'] = true;
				}

				if (($update['status'] ?? null) !== 'updated') {
					$result['status'] = 'stopped';
					$result['failed'] = [
						'templateid' => $templateId,
						'status' => (string) ($update['status'] ?? 'unknown'),
						'write_performed' => !empty($update['write_performed']),
						'reason' => (string) ($update['reason'] ?? '')
					];
					$result['not_attempted'] = array_slice($templateIds, $position + 1);
					break;
				}

				$result['updated'][] = [
					'templateid' => $templateId,
					'status' => 'updated',
					'available_version' => (string) ($update['candidate']['vendor_version'] ?? ''),
					'validation_status' => (string) ($update['validation']['status'] ?? '')
				];
			}
			catch (Throwable $exception) {
				error_log(sprintf(
					'[Zabbix Template Update Manager] Batch execution stopped on template %s: %s',
					$templateId,
					$exception->getMessage()
				));
				$result['status'] = 'stopped';
				$result['failed'] = [
					'templateid' => $templateId,
					'status' => 'exception',
					'write_performed' => false,
					'reason' => 'execution_exception'
				];
				$result['not_attempted'] = array_slice($templateIds, $position + 1);
				break;
			}
		}

		return $result;
	}

	private function normalizeIds(array $templateIds): array {
		$normalized = [];
		foreach ($templateIds as $templateId) {
			$templateId = trim((string) $templateId);
			if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
				throw new RuntimeException('Batch update requires valid numeric template IDs.');
			}
			$normalized[$templateId] = true;
		}

		$ids = array_keys($normalized);
		if ($ids === [] || count($ids) > TemplateBatchPlanService::MAX_TEMPLATES) {
			throw new RuntimeException('Batch update requires between 1 and '.TemplateBatchPlanService::MAX_TEMPLATES.' templates.');
		}

		return $ids;
	}
}
