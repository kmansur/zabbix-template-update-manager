<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;
use Throwable;

require_once __DIR__.'/TemplateConfigurationImportService.php';
require_once __DIR__.'/TemplatePostUpdateValidationService.php';
require_once __DIR__.'/TemplateUpdateCandidateService.php';
require_once __DIR__.'/TemplateUpdatePreflightService.php';

/**
 * Executes one controlled official-template update after a fresh preflight.
 *
 * The caller must provide the evidence fingerprint shown during explicit user
 * confirmation. This service reruns preflight immediately before the write and
 * rejects stale evidence before building/importing the candidate.
 */
final class TemplateControlledUpdateService {

	private $preflightRunner;
	private $candidateBuilder;
	private $importer;
	private $validator;

	public function __construct(
		?callable $preflightRunner = null,
		?callable $candidateBuilder = null,
		?callable $importer = null,
		?callable $validator = null
	) {
		$this->preflightRunner = $preflightRunner ?? static fn(string $templateId, bool $manualOverride = false): array
			=> (new TemplateUpdatePreflightService())->run($templateId, $manualOverride);
		$this->candidateBuilder = $candidateBuilder ?? static fn(array $preflight): array
			=> (new TemplateUpdateCandidateService())->build($preflight);
		$this->importer = $importer ?? static function (array $candidate): void {
			(new TemplateConfigurationImportService())->import(
				(string) ($candidate['source'] ?? ''),
				(string) ($candidate['format'] ?? 'json')
			);
		};
		$this->validator = $validator ?? static fn(string $templateId, array $candidate): array
			=> (new TemplatePostUpdateValidationService())->validate($templateId, $candidate);
	}

	public function execute(string $templateId, string $expectedEvidenceSha256, bool $manualOverride = false): array {
		$templateId = trim($templateId);
		$expectedEvidenceSha256 = strtolower(trim($expectedEvidenceSha256));

		if ($templateId === '' || !ctype_digit($templateId) || (int) $templateId <= 0) {
			throw new RuntimeException('A valid numeric template ID is required for controlled update.');
		}
		if (!preg_match('/^[a-f0-9]{64}$/', $expectedEvidenceSha256)) {
			throw new RuntimeException('A valid preflight evidence fingerprint is required for controlled update.');
		}

		$preflight = ($this->preflightRunner)($templateId, $manualOverride);
		if (!is_array($preflight)) {
			throw new RuntimeException('Fresh update preflight returned an invalid result.');
		}

		if (($preflight['status'] ?? null) !== 'passed') {
			return [
				'status' => 'blocked_preflight',
				'write_performed' => false,
				'reason' => (string) ($preflight['reason'] ?? 'preflight_not_passed'),
				'preflight_status' => (string) ($preflight['status'] ?? 'unknown'),
				'preflight' => $preflight,
				'candidate' => null,
				'validation' => null
			];
		}

		if (!empty($preflight['write_enabled'])) {
			throw new RuntimeException('Preflight must remain a non-write evidence gate.');
		}

		$freshEvidence = strtolower(trim((string) ($preflight['evidence_sha256'] ?? '')));
		if (!preg_match('/^[a-f0-9]{64}$/', $freshEvidence)
				|| !hash_equals($expectedEvidenceSha256, $freshEvidence)) {
			return [
				'status' => 'blocked_evidence_changed',
				'write_performed' => false,
				'reason' => 'preflight_evidence_changed',
				'preflight_status' => 'passed',
				'preflight' => $preflight,
				'candidate' => null,
				'validation' => null
			];
		}

		$candidate = ($this->candidateBuilder)($preflight);
		if (!is_array($candidate)) {
			throw new RuntimeException('Update candidate builder returned an invalid result.');
		}

		$this->assertCandidateMatchesPreflight($candidate, $preflight);

		($this->importer)($candidate);

		try {
			$validation = ($this->validator)($templateId, $candidate);
			if (!is_array($validation)) {
				throw new RuntimeException('Post-update validation returned an invalid result.');
			}
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Post-update validation failed after import for template %s: %s',
				$templateId,
				$exception->getMessage()
			));
			$validation = [
				'status' => 'validation_error',
				'valid' => false,
				'reasons' => ['validation_exception'],
				'expected_version' => (string) ($candidate['vendor_version'] ?? ''),
				'installed_version' => '',
				'content_status' => 'not_available',
				'remaining_changes' => -1
			];
		}

		return [
			'status' => !empty($validation['valid']) ? 'updated' : 'validation_failed',
			'write_performed' => true,
			'reason' => !empty($validation['valid']) ? null : 'post_update_validation_failed',
			'preflight_status' => 'passed',
			'preflight_evidence_sha256' => $freshEvidence,
			'manual_override' => !empty($preflight['manual_override']),
			'manual_reasons' => is_array($preflight['manual_reasons'] ?? null) ? $preflight['manual_reasons'] : [],
			'rollback_sha256' => (string) ($preflight['rollback']['sha256'] ?? ''),
			'candidate' => [
				'commit' => (string) ($candidate['commit'] ?? ''),
				'path' => (string) ($candidate['path'] ?? ''),
				'content_sha256' => (string) ($candidate['content_sha256'] ?? ''),
				'uuid' => (string) ($candidate['uuid'] ?? ''),
				'vendor_version' => (string) ($candidate['vendor_version'] ?? ''),
				'canonical_sha256' => (string) ($candidate['canonical_sha256'] ?? ''),
				'import_sha256' => (string) ($candidate['import_sha256'] ?? '')
			],
			'validation' => $validation
		];
	}

	private function assertCandidateMatchesPreflight(array $candidate, array $preflight): void {
		$expected = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];

		foreach (['commit', 'path', 'content_sha256', 'uuid', 'name', 'technical_name', 'vendor_name', 'vendor_version'] as $field) {
			if ((string) ($candidate[$field] ?? '') !== (string) ($expected[$field] ?? '')) {
				throw new RuntimeException('The rebuilt update candidate does not match the fresh preflight identity.');
			}
		}

		if (!preg_match('/^[a-f0-9]{64}$/', (string) ($candidate['content_sha256'] ?? ''))
				|| !preg_match('/^[a-f0-9]{64}$/', (string) ($candidate['canonical_sha256'] ?? ''))
				|| !hash_equals((string) $candidate['content_sha256'], (string) $candidate['canonical_sha256'])
				|| !preg_match('/^[a-f0-9]{64}$/', (string) ($candidate['import_sha256'] ?? ''))
				|| !is_string($candidate['source'] ?? null)
				|| $candidate['source'] === '') {
			throw new RuntimeException('The rebuilt update candidate content evidence is invalid.');
		}
	}
}
