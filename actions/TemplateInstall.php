<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledInstallService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateControlledInstallService.php';

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

		try {
			$data['result'] = (new TemplateControlledInstallService())->execute(
				$uuid,
				(string) $this->getInput('evidence_sha256')
			);
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Controlled install failed for template UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));
			$data['operation_error'] = _(
				'The controlled installation could not be completed. If the import had already started, inspect the local template inventory before retrying. No automatic uninstall is performed.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
