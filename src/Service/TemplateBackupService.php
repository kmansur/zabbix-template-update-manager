<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;

/**
 * Creates a private local rollback artifact from the current installed template.
 *
 * Export is read-only from Zabbix; persistence is limited to the local backup
 * repository and does not alter Zabbix configuration.
 */
final class TemplateBackupService {

	private TemplateExportService $exportService;
	private TemplateBackupRepository $backupRepository;

	public function __construct(
		?TemplateExportService $exportService = null,
		?TemplateBackupRepository $backupRepository = null
	) {
		$this->exportService = $exportService ?? new TemplateExportService();
		$this->backupRepository = $backupRepository ?? new TemplateBackupRepository();
	}

	public function create(array $template): array {
		$export = $this->exportService->export((string) ($template['templateid'] ?? ''));
		return $this->backupRepository->store($template, $export);
	}
}
