<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallBatchPlanService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallBatchService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateInstallBatchPlanService.php';
require_once dirname(__DIR__).'/src/Service/TemplateInstallBatchService.php';

class TemplateInstallBatch extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'uuids' => 'required|array',
			'evidence' => 'required|array',
			'confirm' => 'required|in 1'
		]);

		if ($ret) {
			$normalized = [];
			foreach ($this->getInput('uuids', []) as $uuid) {
				$uuid = strtolower(str_replace('-', '', trim((string) $uuid)));
				if (preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
					$ret = false;
					break;
				}
				$normalized[$uuid] = true;
			}
			$ret = $ret
				&& count($normalized) >= 1
				&& count($normalized) <= TemplateInstallBatchPlanService::MAX_TEMPLATES;
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
		$data = [
			'title' => _('Selected template installation results'),
			'result' => null,
			'error' => null
		];

		try {
			$data['result'] = (new TemplateInstallBatchService())->execute(
				$this->getInput('uuids', []),
				$this->getInput('evidence', [])
			);
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Batch installation failed: '.$exception->getMessage());
			$data['error'] = _(
				'Unable to execute the selected installation batch. Inspect Zabbix and frontend logs before retrying.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
