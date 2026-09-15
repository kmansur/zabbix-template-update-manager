<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

require_once __DIR__.'/TemplateUpdateAnalysisService.php';

/**
 * Recomputes the complete update analysis and converts it into a deterministic
 * preflight result for a future write-enabled milestone.
 *
 * This service never enables or performs a Zabbix configuration write. A
 * passing result only proves that the current read-only prerequisites were
 * freshly re-evaluated and that the rollback artifact still matches LOCAL.
 */
final class TemplateUpdatePreflightService {

	private $analyzer;

	public function __construct(?callable $analyzer = null) {
		$this->analyzer = $analyzer ?? static fn(string $templateId): array
			=> (new TemplateUpdateAnalysisService())->analyze($templateId);
	}

	public function run(string $templateId): array {
		$templateId = trim($templateId);
		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for update preflight.');
		}

		$analysis = ($this->analyzer)($templateId);
		if (!is_array($analysis)) {
			throw new RuntimeException('Update analysis returned an invalid result.');
		}

		$result = [
			'status' => 'blocked_analysis',
			'write_enabled' => false,
			'next_step' => 'resolve_analysis',
			'reason' => 'analysis_unavailable',
			'template' => null,
			'candidate' => null,
			'rollback' => null,
			'direct_host_count' => 0,
			'evidence_sha256' => null
		];

		$template = is_array($analysis['template'] ?? null) ? $analysis['template'] : null;
		if ($template !== null) {
			$result['template'] = [
				'templateid' => (string) ($template['templateid'] ?? ''),
				'uuid' => self::normalizeUuid((string) ($template['uuid'] ?? '')),
				'name' => (string) ($template['name'] ?? ''),
				'technical_name' => (string) ($template['technical_name'] ?? ''),
				'installed_version' => (string) ($template['vendor_version'] ?? ''),
				'available_version' => (string) ($template['upstream_vendor_version'] ?? '')
			];
			$result['direct_host_count'] = max(0, (int) ($template['host_count'] ?? 0));
		}

		if (($analysis['comparison_error'] ?? null) !== null || $template === null) {
			return $result;
		}

		if ($result['template']['templateid'] !== $templateId
				|| !preg_match('/^[a-f0-9]{32}$/', $result['template']['uuid'])) {
			$result['reason'] = 'template_identity_mismatch';
			return $result;
		}

		$readiness = is_array($analysis['update_readiness'] ?? null)
			? $analysis['update_readiness']
			: null;
		if ($readiness === null || ($readiness['status'] ?? null) !== 'backup_verified') {
			$result['status'] = 'blocked_readiness';
			$result['next_step'] = 'resolve_readiness';
			$result['reason'] = 'readiness_'.($readiness['status'] ?? 'unavailable');
			return $result;
		}

		if (!empty($readiness['write_enabled'])) {
			throw new RuntimeException('Read-only readiness unexpectedly enabled configuration writes.');
		}

		$candidate = $this->candidateDescriptor($analysis, $template);
		if ($candidate === null) {
			$result['status'] = 'blocked_candidate';
			$result['next_step'] = 'resolve_candidate_identity';
			$result['reason'] = 'candidate_identity_unresolved';
			return $result;
		}

		$rollback = $this->rollbackDescriptor($analysis);
		if ($rollback === null) {
			$result['status'] = 'blocked_backup';
			$result['next_step'] = 'reverify_backup';
			$result['reason'] = 'rollback_evidence_unresolved';
			return $result;
		}

		$result['candidate'] = $candidate;
		$result['rollback'] = $rollback;

		$evidence = [
			'schema_version' => 1,
			'templateid' => $result['template']['templateid'],
			'uuid' => $result['template']['uuid'],
			'installed_version' => $result['template']['installed_version'],
			'available_version' => $result['template']['available_version'],
			'upstream_commit' => $candidate['commit'],
			'upstream_path' => $candidate['path'],
			'rollback_sha256' => $rollback['sha256'],
			'current_export_sha256' => $rollback['current_export_sha256'],
			'direct_host_count' => $result['direct_host_count']
		];

		$encoded = json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($encoded)) {
			throw new RuntimeException('Unable to encode update preflight evidence.');
		}

		$result['status'] = 'passed';
		$result['next_step'] = 'await_write_enabled_milestone';
		$result['reason'] = null;
		$result['evidence_sha256'] = hash('sha256', $encoded);

		return $result;
	}

	private function candidateDescriptor(array $analysis, array $template): ?array {
		$source = is_array($analysis['upstream_source'] ?? null) ? $analysis['upstream_source'] : [];
		$commit = strtolower(trim((string) ($source['commit'] ?? '')));
		$path = trim((string) ($analysis['source_path'] ?? ''));
		$uuid = self::normalizeUuid((string) ($template['uuid'] ?? ''));
		$availableVersion = trim((string) ($template['upstream_vendor_version'] ?? ''));

		if (!preg_match('/^[a-f0-9]{40}$/', $commit)
				|| !self::isSafeTemplatePath($path)
				|| !preg_match('/^[a-f0-9]{32}$/', $uuid)
				|| $availableVersion === '') {
			return null;
		}

		return [
			'commit' => $commit,
			'path' => $path,
			'uuid' => $uuid,
			'vendor_version' => $availableVersion
		];
	}

	private function rollbackDescriptor(array $analysis): ?array {
		$verification = is_array($analysis['backup_verification'] ?? null)
			? $analysis['backup_verification']
			: [];
		$latest = is_array($verification['latest'] ?? null) ? $verification['latest'] : [];
		$currentExport = is_array($verification['current_export'] ?? null)
			? $verification['current_export']
			: [];

		$storedSha = strtolower(trim((string) ($latest['sha256'] ?? '')));
		$currentSha = strtolower(trim((string) ($currentExport['sha256'] ?? '')));
		$createdAt = trim((string) ($latest['created_at'] ?? ''));
		$bytes = (int) ($latest['bytes'] ?? -1);
		$currentBytes = (int) ($currentExport['bytes'] ?? -2);

		if (($verification['status'] ?? null) !== 'current_match'
				|| empty($verification['current_match'])
				|| !preg_match('/^[a-f0-9]{64}$/', $storedSha)
				|| !preg_match('/^[a-f0-9]{64}$/', $currentSha)
				|| !hash_equals($storedSha, $currentSha)
				|| $bytes < 1
				|| $bytes !== $currentBytes) {
			return null;
		}

		return [
			'created_at' => $createdAt,
			'bytes' => $bytes,
			'sha256' => $storedSha,
			'current_export_sha256' => $currentSha
		];
	}

	private static function isSafeTemplatePath(string $path): bool {
		if ($path === '' || !str_starts_with($path, 'templates/') || !str_ends_with($path, '.yaml')) {
			return false;
		}

		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				return false;
			}
		}

		return true;
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
