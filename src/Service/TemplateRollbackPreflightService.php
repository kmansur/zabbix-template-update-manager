<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use RuntimeException;

require_once dirname(__DIR__).'/Repository/TemplateRepository.php';
require_once __DIR__.'/ImportCompareSummary.php';
require_once __DIR__.'/TemplateExportService.php';
require_once __DIR__.'/TemplateImportCompareService.php';
require_once __DIR__.'/TemplateInventoryService.php';
require_once __DIR__.'/TemplateRollbackArtifactService.php';

/**
 * Revalidates one selected rollback artifact against the current installed
 * template and produces deterministic evidence for explicit rollback review.
 *
 * This service never writes Zabbix configuration.
 */
final class TemplateRollbackPreflightService {

	private $artifactLoader;
	private $templateLoader;
	private $exporter;
	private $comparer;

	public function __construct(
		?callable $artifactLoader = null,
		?callable $templateLoader = null,
		?callable $exporter = null,
		?callable $comparer = null
	) {
		$this->artifactLoader = $artifactLoader ?? static fn(string $templateId, string $manifestFile): array
			=> (new TemplateRollbackArtifactService())->load($templateId, $manifestFile);
		$this->templateLoader = $templateLoader ?? static function (string $templateId): array {
			$record = (new TemplateRepository())->findById($templateId);
			if ($record === null) {
				throw new RuntimeException('The requested template is not visible to the current user.');
			}

			$inventory = TemplateInventoryService::fromRecords([$record]);
			$template = $inventory['templates'][0] ?? null;
			if (!is_array($template)) {
				throw new RuntimeException('Unable to normalize the current template for rollback preflight.');
			}

			return $template;
		};
		$this->exporter = $exporter ?? static fn(string $templateId): array
			=> (new TemplateExportService())->export($templateId);
		$this->comparer = $comparer ?? static fn(string $source): array
			=> (new TemplateImportCompareService())->compare($source);
	}

	public function run(string $templateId, string $manifestFile): array {
		$templateId = $this->normalizeTemplateId($templateId);
		$manifestFile = trim($manifestFile);

		$artifact = ($this->artifactLoader)($templateId, $manifestFile);
		$template = ($this->templateLoader)($templateId);
		$currentExport = ($this->exporter)($templateId);

		if (!is_array($artifact) || !is_array($template) || !is_array($currentExport)) {
			throw new RuntimeException('Rollback preflight dependencies returned invalid data.');
		}

		$currentUuid = self::normalizeUuid((string) ($template['uuid'] ?? ''));
		$artifactUuid = self::normalizeUuid((string) ($artifact['uuid'] ?? ''));
		if (!preg_match('/^[a-f0-9]{32}$/', $currentUuid)
				|| !hash_equals($currentUuid, $artifactUuid)
				|| (string) ($template['templateid'] ?? '') !== $templateId
				|| (string) ($artifact['templateid'] ?? '') !== $templateId) {
			return [
				'status' => 'blocked_identity',
				'reason' => 'template_identity_mismatch',
				'write_enabled' => false,
				'template' => self::templateDescriptor($template),
				'target' => self::artifactDescriptor($artifact),
				'current_export' => self::exportDescriptor($currentExport),
				'comparison_summary' => null,
				'evidence_sha256' => null
			];
		}

		$source = $artifact['source'] ?? null;
		if (!is_string($source) || $source === '') {
			throw new RuntimeException('The rollback artifact source is unavailable.');
		}

		$diff = ($this->comparer)($source);
		if (!is_array($diff)) {
			throw new RuntimeException('Rollback import comparison returned an invalid result.');
		}
		$summary = ImportCompareSummary::summarize($diff);

		$currentSha = strtolower(trim((string) ($currentExport['sha256'] ?? '')));
		$currentBytes = (int) ($currentExport['bytes'] ?? -1);
		$targetSha = strtolower(trim((string) ($artifact['sha256'] ?? '')));
		$targetBytes = (int) ($artifact['bytes'] ?? -1);

		if (!preg_match('/^[a-f0-9]{64}$/', $currentSha)
				|| !preg_match('/^[a-f0-9]{64}$/', $targetSha)
				|| $currentBytes < 1
				|| $targetBytes < 1) {
			throw new RuntimeException('Rollback preflight fingerprints are invalid.');
		}

		$status = $summary['total'] === 0 ? 'already_restored' : 'ready';
		$evidence = [
			'schema_version' => 1,
			'templateid' => $templateId,
			'uuid' => $currentUuid,
			'current_vendor_version' => (string) ($template['vendor_version'] ?? ''),
			'current_export_sha256' => $currentSha,
			'current_export_bytes' => $currentBytes,
			'target_manifest_file' => (string) ($artifact['manifest_file'] ?? ''),
			'target_vendor_version' => (string) ($artifact['vendor_version'] ?? ''),
			'target_sha256' => $targetSha,
			'target_bytes' => $targetBytes,
			'comparison_added' => (int) ($summary['added'] ?? 0),
			'comparison_updated' => (int) ($summary['updated'] ?? 0),
			'comparison_removed' => (int) ($summary['removed'] ?? 0),
			'comparison_total' => (int) ($summary['total'] ?? 0)
		];
		$encoded = json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($encoded)) {
			throw new RuntimeException('Unable to encode rollback preflight evidence.');
		}

		return [
			'status' => $status,
			'reason' => $status === 'already_restored' ? 'target_already_matches_current_template' : null,
			'write_enabled' => false,
			'template' => self::templateDescriptor($template),
			'target' => self::artifactDescriptor($artifact),
			'current_export' => self::exportDescriptor($currentExport),
			'comparison_summary' => $summary,
			'evidence_sha256' => hash('sha256', $encoded)
		];
	}

	private static function templateDescriptor(array $template): array {
		return [
			'templateid' => (string) ($template['templateid'] ?? ''),
			'uuid' => self::normalizeUuid((string) ($template['uuid'] ?? '')),
			'name' => (string) ($template['name'] ?? ''),
			'technical_name' => (string) ($template['technical_name'] ?? ''),
			'vendor_version' => (string) ($template['vendor_version'] ?? '')
		];
	}

	private static function artifactDescriptor(array $artifact): array {
		return [
			'templateid' => (string) ($artifact['templateid'] ?? ''),
			'uuid' => self::normalizeUuid((string) ($artifact['uuid'] ?? '')),
			'name' => (string) ($artifact['name'] ?? ''),
			'technical_name' => (string) ($artifact['technical_name'] ?? ''),
			'vendor_version' => (string) ($artifact['vendor_version'] ?? ''),
			'created_at' => (string) ($artifact['created_at'] ?? ''),
			'bytes' => (int) ($artifact['bytes'] ?? 0),
			'sha256' => (string) ($artifact['sha256'] ?? ''),
			'manifest_file' => (string) ($artifact['manifest_file'] ?? ''),
			'source_file' => (string) ($artifact['source_file'] ?? '')
		];
	}

	private static function exportDescriptor(array $export): array {
		return [
			'bytes' => (int) ($export['bytes'] ?? 0),
			'sha256' => (string) ($export['sha256'] ?? '')
		];
	}

	private function normalizeTemplateId(string $templateId): string {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for rollback preflight.');
		}
		return $templateId;
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
