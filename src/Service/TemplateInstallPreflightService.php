<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use CImportReaderFactory;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;

require_once dirname(__DIR__).'/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/Repository/UpstreamTemplateSourceRepository.php';
require_once dirname(__DIR__).'/Support/ZabbixVersion.php';
require_once __DIR__.'/ImportCompareSummary.php';
require_once __DIR__.'/TemplateImportCompareService.php';
require_once __DIR__.'/TemplateInstallDependencyService.php';
require_once __DIR__.'/TemplateInstallReferenceAuditService.php';
require_once __DIR__.'/TemplateInstallPreviewGate.php';
require_once __DIR__.'/UpdatePreviewAnalyzer.php';
require_once __DIR__.'/UpstreamTemplateDocumentService.php';

/**
 * Rebuilds authoritative evidence for installing one official template that is
 * currently absent locally. This service is read-only.
 */
final class TemplateInstallPreflightService {

	public function run(string $uuid): array {
		$uuid = self::normalizeUuid($uuid);
		if (!preg_match('/^[a-f0-9]{32}$/', $uuid)) {
			throw new RuntimeException('A valid official template UUID is required for installation review.');
		}

		$zabbixVersion = ZabbixVersion::current();
		if (!ZabbixVersion::isSupported($zabbixVersion)) {
			return self::blocked('blocked_version', 'unsupported_zabbix_version');
		}

		$index = (new UpstreamIndexRepository())->load($zabbixVersion);
		$record = $index['templates'][$uuid] ?? null;
		if (!is_array($record) || self::normalizeUuid((string) ($record['uuid'] ?? '')) !== $uuid) {
			return self::blocked('blocked_candidate', 'official_template_not_found');
		}

		$vendorVersion = trim((string) ($record['vendor_version'] ?? ''));
		if ($vendorVersion === '') {
			return self::blocked('blocked_candidate', 'upstream_version_missing');
		}

		$localRecords = (new TemplateRepository())->findAll();
		$localState = self::localState($uuid, $record, $localRecords);
		if ($localState['installed']) {
			return self::blocked('blocked_already_installed', 'template_already_installed', [
				'candidate' => self::candidateIdentity($record, $index['source'] ?? [])
			]);
		}
		if ($localState['technical_name_collision']) {
			return self::blocked('blocked_collision', 'technical_name_collision', [
				'candidate' => self::candidateIdentity($record, $index['source'] ?? []),
				'collision' => $localState['collision']
			]);
		}

		$sourceFile = (new UpstreamTemplateSourceRepository())->fetch($index['source'] ?? [], $record);
		if (!preg_match('/^[a-f0-9]{64}$/', (string) ($sourceFile['source_sha256'] ?? ''))) {
			throw new RuntimeException('The immutable upstream source is missing a validated raw fingerprint.');
		}

		$candidate = self::candidateIdentity($record, $index['source'] ?? []) + [
			'path' => (string) ($sourceFile['path'] ?? ''),
			'source_sha256' => (string) ($sourceFile['source_sha256'] ?? ''),
			'content_sha256' => (string) ($sourceFile['content_sha256'] ?? '')
		];

		$reader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
		$document = $reader->read($sourceFile['content']);

		try {
			$isolated = UpstreamTemplateDocumentService::buildImportSource(
				$document,
				$uuid,
				$record,
				true
			);
		}
		catch (TemplateIsolationSafetyException $exception) {
			return self::blocked('blocked_isolation', $exception->getReasonCode(), [
				'candidate' => $candidate,
				'isolation_detail' => $exception->getMessage()
			]);
		}

		$dependencies = TemplateInstallDependencyService::analyze(
			$isolated['template'],
			$localRecords,
			is_array($isolated['external_template_names'] ?? null)
				? $isolated['external_template_names']
				: []
		);
		$candidate['import_sha256'] = hash('sha256', $isolated['source']);

		if (!$dependencies['complete']) {
			return self::blocked('blocked_dependencies', 'missing_template_dependencies', [
				'candidate' => $candidate,
				'dependencies' => $dependencies
			]);
		}

		$referenceAudit = TemplateInstallReferenceAuditService::analyze(
			$isolated['template'],
			$dependencies['required']
		);

		if (!$referenceAudit['safe']) {
			return self::blocked('blocked_references', 'unresolved_internal_references', [
				'candidate' => $candidate,
				'dependencies' => $dependencies,
				'reference_audit' => $referenceAudit
			]);
		}

		$diff = (new TemplateImportCompareService())->compare($isolated['source'], 'json');
		$summary = ImportCompareSummary::summarize($diff);
		$preview = UpdatePreviewAnalyzer::analyze($diff, 200);
		$previewGate = TemplateInstallPreviewGate::evaluate($summary, $preview);
		if (!$previewGate['safe']) {
			return self::blocked('blocked_preview', (string) $previewGate['reason'], [
				'candidate' => $candidate,
				'dependencies' => $dependencies,
				'comparison_summary' => $summary,
				'preview' => $preview
			]);
		}

		$evidence = [
			'uuid' => $uuid,
			'name' => (string) ($record['name'] ?? ''),
			'technical_name' => (string) ($record['technical_name'] ?? ''),
			'vendor_name' => (string) ($record['vendor_name'] ?? ''),
			'vendor_version' => $vendorVersion,
			'commit' => (string) ($candidate['commit'] ?? ''),
			'path' => (string) ($candidate['path'] ?? ''),
			'source_sha256' => (string) ($candidate['source_sha256'] ?? ''),
			'content_sha256' => (string) ($candidate['content_sha256'] ?? ''),
			'import_sha256' => (string) ($candidate['import_sha256'] ?? ''),
			'dependencies' => $dependencies['required'],
			'external_template_names' => is_array($isolated['external_template_names'] ?? null)
				? array_values(array_map('strval', $isolated['external_template_names']))
				: [],
			'reference_audit' => [
				'counts' => $referenceAudit['counts']
			],
			'comparison_summary' => [
				'added' => (int) ($summary['added'] ?? 0),
				'updated' => (int) ($summary['updated'] ?? 0),
				'removed' => (int) ($summary['removed'] ?? 0),
				'total' => (int) ($summary['total'] ?? 0)
			]
		];

		return [
			'status' => 'passed',
			'reason' => null,
			'write_enabled' => true,
			'candidate' => $candidate,
			'dependencies' => $dependencies,
			'reference_audit' => $referenceAudit,
			'comparison_summary' => $summary,
			'preview' => $preview,
			'evidence_sha256' => self::evidenceSha256($evidence),
			'import_source' => $isolated['source'],
			'import_format' => 'json'
		];
	}

	private static function localState(string $uuid, array $record, array $localRecords): array {
		$technicalName = trim((string) ($record['technical_name'] ?? ''));
		$installed = false;
		$collision = null;

		foreach ($localRecords as $local) {
			if (!is_array($local)) {
				continue;
			}

			$localUuid = self::normalizeUuid((string) ($local['uuid'] ?? ''));
			if ($localUuid === $uuid) {
				$installed = true;
				break;
			}

			if ($technicalName !== '' && trim((string) ($local['host'] ?? '')) === $technicalName) {
				$collision = [
					'templateid' => (string) ($local['templateid'] ?? ''),
					'technical_name' => (string) ($local['host'] ?? ''),
					'uuid' => $localUuid
				];
			}
		}

		return [
			'installed' => $installed,
			'technical_name_collision' => $collision !== null,
			'collision' => $collision
		];
	}

	private static function candidateIdentity(array $record, array $source): array {
		return [
			'uuid' => self::normalizeUuid((string) ($record['uuid'] ?? '')),
			'name' => trim((string) ($record['name'] ?? '')),
			'technical_name' => trim((string) ($record['technical_name'] ?? '')),
			'vendor_name' => trim((string) ($record['vendor_name'] ?? '')),
			'vendor_version' => trim((string) ($record['vendor_version'] ?? '')),
			'commit' => strtolower(trim((string) ($source['commit'] ?? '')))
		];
	}

	private static function blocked(string $status, string $reason, array $extra = []): array {
		return $extra + [
			'status' => $status,
			'reason' => $reason,
			'write_enabled' => false,
			'dependencies' => $extra['dependencies'] ?? ['required' => [], 'installed' => [], 'missing' => [], 'complete' => false],
			'comparison_summary' => $extra['comparison_summary'] ?? ImportCompareSummary::summarize([]),
			'preview' => $extra['preview'] ?? null,
			'evidence_sha256' => '',
			'import_source' => '',
			'import_format' => 'json'
		];
	}

	private static function evidenceSha256(array $evidence): string {
		$canonical = self::canonicalize($evidence);
		$json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new RuntimeException('Unable to encode installation preflight evidence.');
		}
		return hash('sha256', $json);
	}

	private static function canonicalize($value) {
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			return array_map([self::class, 'canonicalize'], $value);
		}
		ksort($value, SORT_STRING);
		foreach ($value as &$child) {
			$child = self::canonicalize($child);
		}
		unset($child);
		return $value;
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
