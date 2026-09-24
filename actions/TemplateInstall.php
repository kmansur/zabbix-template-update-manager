<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledInstallService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateControlledInstallService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationLockService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationHistoryService.php';

class TemplateInstall extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'uuid' => 'required|string',
			'evidence_sha256' => 'required|string',
			'confirm' => 'required|in 1'
		]);

		if ($ret) {
			$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
			$evidence = strtolower(trim((string) $this->getInput('evidence_sha256')));
			$ret = preg_match('/^[a-f0-9]{32}$/', $uuid) === 1
				&& preg_match('/^[a-f0-9]{64}$/', $evidence) === 1;
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
		$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
		$data = [
			'title' => _('Template installation result'),
			'uuid' => $uuid,
			'result' => null,
			'operation_error' => null
		];

		$operationException = null;

		try {
			$evidence = (string) $this->getInput('evidence_sha256');
			$data['result'] = (new TemplateOperationLockService())->run(
				'install',
				'uuid-'.$uuid,
				static fn(): array => (new TemplateControlledInstallService())->execute($uuid, $evidence)
			);
		}
		catch (Throwable $exception) {
			$operationException = $exception;
			error_log(sprintf(
				'[Zabbix Template Update Manager] Controlled install failed for template UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));
			$data['operation_error'] = _(
				'Installation could not be completed. Inspect the local template inventory before retrying. Automatic uninstall is never performed.'
			);
		}

		(new TemplateOperationHistoryService())->recordBestEffort(
			'install',
			'uuid-'.$uuid,
			$data['result'],
			$operationException,
			(string) (CWebUser::$data['userid'] ?? '')
		);

		$this->setResponse(new CControllerResponseData($data));
	}
}
