<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledUpdateService;\nuse Modules\\ZabbixTemplateUpdateManager\\Service\\TemplateOperationLockService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateControlledUpdateService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationLockService.php';

/**
 * Performs one explicitly confirmed official-template update.
 *
 * Native CSRF validation remains enabled. The action is restricted to Zabbix
 * super administrators and delegates all fail-closed preflight/write/validation
 * logic to TemplateControlledUpdateService.
 */
class TemplateUpdate extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|db hosts.hostid',
			'evidence_sha256' => 'required|string',
			'confirm' => 'required|in 1',
			'manual_override' => 'in 1',
			'confirm_manual_override' => 'in 1',
			'confirm_local_overwrite' => 'in 1'
		]);

		if ($ret && (string) $this->getInput('manual_override', '') === '1') {
			$ret = (string) $this->getInput('confirm_manual_override', '') === '1';
		}

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
		$data = [
			'title' => _('Template update result'),
			'templateid' => $templateId,
			'result' => null,
			'operation_error' => null
		];

		try {
			$evidence = (string) $this->getInput('evidence_sha256');
			$manualOverride = (string) $this->getInput('manual_override', '') === '1';
			$localOverwriteConfirmed = (string) $this->getInput('confirm_local_overwrite', '') === '1';

			$data['result'] = (new TemplateOperationLockService())->run(
				'update',
				'template-'.$templateId,
				static fn(): array => (new TemplateControlledUpdateService())->execute(
					$templateId,
					$evidence,
					$manualOverride,
					$localOverwriteConfirmed
				)
			);
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Controlled update failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));
			$data['operation_error'] = _(
				'The controlled update could not be completed. Review frontend logs and re-open the template comparison before retrying. If the import had already started, verify the current template state before taking any further action.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
