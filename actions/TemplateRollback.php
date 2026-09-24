<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateRollbackService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateRollbackService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationLockService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationHistoryService.php';

/**
 * Performs one explicitly confirmed template rollback.
 *
 * Native CSRF validation remains enabled. The action is restricted to Zabbix
 * super administrators and delegates all preflight, recovery-backup, import and
 * validation logic to TemplateRollbackService.
 */
class TemplateRollback extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|db hosts.hostid',
			'manifest_file' => 'required|string',
			'evidence_sha256' => 'required|string',
			'confirm' => 'required|in 1'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$templateId = (string) $this->getInput('templateid');
		$manifestFile = (string) $this->getInput('manifest_file');
		$data = [
			'title' => _('Template rollback result'),
			'templateid' => $templateId,
			'manifest_file' => $manifestFile,
			'result' => null,
			'operation_error' => null
		];

		$operationException = null;

		try {
			$evidence = (string) $this->getInput('evidence_sha256');
			$data['result'] = (new TemplateOperationLockService())->run(
				'rollback',
				'template-'.$templateId,
				static fn(): array => (new TemplateRollbackService())->execute(
					$templateId,
					$manifestFile,
					$evidence
				)
			);
		}
		catch (Throwable $exception) {
			$operationException = $exception;
			error_log(sprintf(
				'[Zabbix Template Update Manager] Controlled rollback failed for template %s / %s: %s',
				$templateId,
				$manifestFile,
				$exception->getMessage()
			));
			$data['operation_error'] = _(
				'Rollback could not be completed. Review frontend logs and inspect the template before another write action.'
			);
		}

		(new TemplateOperationHistoryService())->recordBestEffort(
			'rollback',
			'template-'.$templateId,
			$data['result'],
			$operationException,
			(string) (CWebUser::$data['userid'] ?? '')
		);

		$this->setResponse(new CControllerResponseData($data));
	}
}
