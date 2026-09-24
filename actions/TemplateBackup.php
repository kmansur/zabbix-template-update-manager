<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBackupService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateExportService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateBackupService.php';
require_once dirname(__DIR__).'/src/Service/TemplateExportService.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationHistoryService.php';

/**
 * Creates a persistent local rollback artifact for one visible template.
 *
 * CSRF validation intentionally remains enabled through the CController default.
 * The action reads Zabbix configuration through configuration.export and writes
 * only the module's local backup files.
 */
class TemplateBackup extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|db hosts.hostid'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
	}

	protected function doAction(): void {
		$templateId = (string) $this->getInput('templateid');
		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'ztum.template.compare')
				->setArgument('templateid', $templateId)
		);

		$historyResult = null;
		$operationException = null;

		try {
			$record = (new TemplateRepository())->findById($templateId);
			if ($record === null) {
				throw new RuntimeException('The requested template is not visible to the current user.');
			}

			$inventory = TemplateInventoryService::fromRecords([$record]);
			$template = $inventory['templates'][0] ?? null;
			if (!is_array($template)) {
				throw new RuntimeException('Unable to normalize the requested template for backup.');
			}

			$artifact = (new TemplateBackupService())->create($template);
			$historyResult = [
				'status' => 'created',
				'write_performed' => false,
				'detail' => 'sha256='.substr((string) ($artifact['sha256'] ?? ''), 0, 16)
			];

			CMessageHelper::setSuccessTitle(_('Template rollback backup created'));
			info(sprintf(
				_('A persistent rollback backup was created for "%1$s". SHA-256: %2$s.'),
				$template['name'],
				substr((string) $artifact['sha256'], 0, 16)
			));
		}
		catch (Throwable $exception) {
			$operationException = $exception;
			error_log(sprintf(
				'[Zabbix Template Update Manager] Template backup failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));
			CMessageHelper::setErrorTitle(_('Cannot create template rollback backup'));
			error(sprintf(
				_('Unable to export or persist the template backup. Verify frontend logs and write access to %1$s.'),
				TemplateBackupRepository::defaultBackupDirectory()
			));
		}

		(new TemplateOperationHistoryService())->recordBestEffort(
			'backup',
			'template-'.$templateId,
			$historyResult,
			$operationException,
			(string) (CWebUser::$data['userid'] ?? '')
		);

		$this->setResponse($response);
	}
}
