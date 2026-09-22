<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;
use Throwable;

require_once __DIR__.'/TemplateControlledInstallService.php';
require_once __DIR__.'/TemplateInstallBatchPlanService.php';

/**
 * Sequentially executes an already-reviewed set of Ready install candidates.
 * Every item re-runs the authoritative controlled install flow and execution
 * stops on the first non-success.
 */
final class TemplateInstallBatchService {

	private $executor;

	public function __construct(?callable $executor = null) {
		$this->executor = $executor ?? static fn(string $uuid, string $evidence): array
			=> (new TemplateControlledInstallService())->execute($uuid, $evidence);
	}

	public function execute(array $uuids, array $evidenceByUuid): array {
		$uuids = $this->normalizeUuids($uuids);
		$result = [
			'status' => 'completed',
			'installed' => [],
			'failed' => null,
			'not_attempted' => [],
			'write_performed' => false
		];

		foreach ($uuids as $position => $uuid) {
			$evidence = strtolower(trim((string) ($evidenceByUuid[$uuid] ?? '')));
			if (preg_match('/^[a-f0-9]{64}$/', $evidence) !== 1) {
				$result['status'] = 'stopped';
				$result['failed'] = [
					'uuid' => $uuid,
					'status' => 'invalid_evidence',
					'write_performed' => false,
					'reason' => 'invalid_evidence'
				];
				$result['not_attempted'] = array_slice($uuids, $position + 1);
				break;
			}

			try {
				$install = ($this->executor)($uuid, $evidence);
				if (!is_array($install)) {
					throw new RuntimeException('Controlled install returned an invalid result.');
				}

				if (!empty($install['write_performed'])) {
					$result['write_performed'] = true;
				}

				if (($install['status'] ?? null) !== 'installed') {
					$result['status'] = 'stopped';
					$result['failed'] = [
						'uuid' => $uuid,
						'status' => (string) ($install['status'] ?? 'unknown'),
						'write_performed' => !empty($install['write_performed']),
						'reason' => (string) ($install['reason'] ?? '')
					];
					$result['not_attempted'] = array_slice($uuids, $position + 1);
					break;
				}

				$result['installed'][] = [
					'uuid' => $uuid,
					'name' => (string) ($install['candidate']['name'] ?? ''),
					'available_version' => (string) ($install['candidate']['vendor_version'] ?? ''),
					'templateid' => (string) ($install['validation']['templateid'] ?? ''),
					'validation_status' => (string) ($install['validation']['status'] ?? '')
				];
			}
			catch (Throwable $exception) {
				error_log(sprintf(
					'[Zabbix Template Update Manager] Batch installation stopped on UUID %s: %s',
					$uuid,
					$exception->getMessage()
				));

				$result['status'] = 'stopped';
				$result['failed'] = [
					'uuid' => $uuid,
					'status' => 'exception',
					'write_performed' => false,
					'reason' => 'execution_exception'
				];
				$result['not_attempted'] = array_slice($uuids, $position + 1);
				break;
			}
		}

		return $result;
	}

	private function normalizeUuids(array $uuids): array {
		$normalized = [];
		foreach ($uuids as $uuid) {
			$uuid = strtolower(str_replace('-', '', trim((string) $uuid)));
			if (preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
				throw new RuntimeException('Batch installation requires valid official template UUIDs.');
			}
			$normalized[$uuid] = true;
		}

		$result = array_keys($normalized);
		if ($result === [] || count($result) > TemplateInstallBatchPlanService::MAX_TEMPLATES) {
			throw new RuntimeException(
				'Batch installation requires between 1 and '.TemplateInstallBatchPlanService::MAX_TEMPLATES.' templates.'
			);
		}

		return $result;
	}
}
