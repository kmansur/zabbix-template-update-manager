<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateRollbackService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateRollbackService.php';

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

		try {
			$data['result'] = (new TemplateRollbackService())->execute(
				$templateId,
				$manifestFile,
				(string) $this->getInput('evidence_sha256')
			);
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Controlled rollback failed for template %s / %s: %s',
				$templateId,
				$manifestFile,
				$exception->getMessage()
			));
			$data['operation_error'] = _(
				'The controlled rollback could not be completed. Review frontend logs and inspect the template before attempting another configuration write.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
