<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use RuntimeException;

require_once __DIR__.'/TemplateUpdateAnalysisService.php';
require_once __DIR__.'/TemplateBackupService.php';
require_once __DIR__.'/TemplateUpdatePreflightService.php';

/**
 * Prepares an individual update without importing Zabbix configuration.
 * Backup creation is automatic, but only after authoritative readiness checks.
 * The fresh final preflight (not this service) controls whether the UI can
 * offer a separately confirmed import.
 */
final class TemplateUpdatePreparationService {
    private $analyze;
    private $createBackup;
    private $preflight;

    public function __construct(
        ?callable $analyze = null,
        ?callable $createBackup = null,
        ?callable $preflight = null
    ) {
        $this->analyze = $analyze ?? static fn(string $id): array
            => (new TemplateUpdateAnalysisService())->analyze($id);
        $this->createBackup = $createBackup ?? static fn(array $template): array
            => (new TemplateBackupService())->create($template);
        $this->preflight = $preflight ?? static fn(string $id, bool $manual): array
            => (new TemplateUpdatePreflightService())->run($id, $manual);
    }

    public function prepare(string $templateId): array {
        if (!ctype_digit($templateId) || (int) $templateId < 1) {
            throw new RuntimeException('Invalid template ID for update preparation.');
        }

        $analysis = ($this->analyze)($templateId);
        if (!is_array($analysis)) {
            throw new RuntimeException('Template analysis unavailable.');
        }

        $readiness = is_array($analysis['update_readiness'] ?? null)
            ? $analysis['update_readiness'] : [];
        $status = (string) ($readiness['status'] ?? '');
        if (!in_array($status, [
            'candidate_for_backup', 'review_required',
            'backup_verified', 'review_backup_verified'
        ], true)) {
            throw new RuntimeException('Template is not eligible for controlled preparation.');
        }

        if (in_array($status, ['candidate_for_backup', 'review_required'], true)) {
            if (empty($readiness['candidate_for_backup'])
                || !is_array($analysis['template'] ?? null)
                || (string) ($analysis['template']['templateid'] ?? '') !== $templateId) {
                throw new RuntimeException('Rollback candidate identity is not valid.');
            }
            ($this->createBackup)($analysis['template']);
        }

        // Never accept a cached readiness result after creating the backup.
        $fresh = ($this->analyze)($templateId);
        if (!is_array($fresh) || !is_array($fresh['update_readiness'] ?? null)) {
            throw new RuntimeException('Unable to revalidate preparation and rollback evidence.');
        }
        $freshStatus = (string) ($fresh['update_readiness']['status'] ?? '');
        if (!in_array($freshStatus, ['backup_verified', 'review_backup_verified'], true)
            || empty($fresh['update_readiness']['backup_verified'])
            || !is_array($fresh['backup_verification'] ?? null)
            || ($fresh['backup_verification']['status'] ?? '') !== 'current_match'
            || empty($fresh['backup_verification']['current_match'])) {
            throw new RuntimeException('Automatic rollback backup was not verified against the installed template.');
        }

        $manual = $freshStatus === 'review_backup_verified';
        $preflight = ($this->preflight)($templateId, $manual);
        if (!is_array($preflight)) {
            throw new RuntimeException('Fresh update preflight returned invalid evidence.');
        }
        return $preflight;
    }
}
