<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallPreflightService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateInstallPreflightService.php';

class TemplateInstallReview extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(['uuid' => 'required|string']);
		if ($ret) {
			$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
			$ret = preg_match('/^[a-f0-9]{32}$/', $uuid) === 1;
		}
		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}
		return $ret;
	}

	protected function checkPermissions(): bool {
		return in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
	}

	protected function doAction(): void {
		$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
		$data = [
			'title' => _('Template installation review'),
			'uuid' => $uuid,
			'preflight' => null,
			'preflight_error' => null,
			'can_install' => $this->getUserType() === USER_TYPE_SUPER_ADMIN
		];

		try {
			$data['preflight'] = (new TemplateInstallPreflightService())->run($uuid);
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Install review failed for template UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));
			$data['preflight_error'] = _(
				'Unable to complete the installation review. No Zabbix configuration change was attempted.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
