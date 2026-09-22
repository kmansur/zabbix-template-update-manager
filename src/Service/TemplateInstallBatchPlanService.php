<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;
use Throwable;

require_once __DIR__.'/TemplateInstallPreflightService.php';

/**
 * Builds a bounded batch-install plan from the existing per-template install
 * preflight. This service is read-only and never imports configuration.
 */
final class TemplateInstallBatchPlanService {

	public const MAX_TEMPLATES = 500;

	private $preflightRunner;

	public function __construct(?callable $preflightRunner = null) {
		$this->preflightRunner = $preflightRunner ?? static fn(string $uuid): array
			=> (new TemplateInstallPreflightService())->run($uuid);
	}

	public function build(array $uuids): array {
		$uuids = $this->normalizeUuids($uuids);
		$result = [
			'items' => [],
			'summary' => [
				'selected' => count($uuids),
				'ready' => 0,
				'blocked' => 0
			]
		];

		foreach ($uuids as $uuid) {
			$item = $this->buildItem($uuid);
			$result['items'][] = $item;
			$result['summary'][$item['category'] === 'ready' ? 'ready' : 'blocked']++;
		}

		return $result;
	}

	private function buildItem(string $uuid): array {
		try {
			$preflight = ($this->preflightRunner)($uuid);
			if (!is_array($preflight)) {
				throw new RuntimeException('Template install preflight returned an invalid result.');
			}

			$candidate = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];
			$dependencies = is_array($preflight['dependencies'] ?? null) ? $preflight['dependencies'] : [];
			$status = (string) ($preflight['status'] ?? 'blocked_candidate');
			$evidence = strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')));
			$ready = $status === 'passed'
				&& !empty($preflight['write_enabled'])
				&& preg_match('/^[a-f0-9]{64}$/', $evidence) === 1;

			return [
				'uuid' => $uuid,
				'name' => (string) ($candidate['name'] ?? $candidate['technical_name'] ?? $uuid),
				'available_version' => (string) ($candidate['vendor_version'] ?? ''),
				'preflight_status' => $status,
				'category' => $ready ? 'ready' : 'blocked',
				'evidence_sha256' => $ready ? $evidence : '',
				'reason' => $ready ? '' : (string) ($preflight['reason'] ?? 'install_preflight_not_passed'),
				'required_dependencies' => is_array($dependencies['required'] ?? null)
					? array_values(array_map('strval', $dependencies['required']))
					: [],
				'missing_dependencies' => is_array($dependencies['missing'] ?? null)
					? array_values(array_map('strval', $dependencies['missing']))
					: []
			];
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Batch install plan failed for UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));

			return [
				'uuid' => $uuid,
				'name' => $uuid,
				'available_version' => '',
				'preflight_status' => 'blocked_error',
				'category' => 'blocked',
				'evidence_sha256' => '',
				'reason' => 'analysis_exception',
				'required_dependencies' => [],
				'missing_dependencies' => []
			];
		}
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
		if ($result === [] || count($result) > self::MAX_TEMPLATES) {
			throw new RuntimeException(
				'Batch installation requires between 1 and '.self::MAX_TEMPLATES.' templates.'
			);
		}

		return $result;
	}
}
