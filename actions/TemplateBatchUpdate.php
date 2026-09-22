<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchUpdateService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateBatchPlanService.php';
require_once dirname(__DIR__).'/src/Service/TemplateBatchUpdateService.php';

class TemplateBatchUpdate extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateids' => 'required|array_id',
			'evidence' => 'required|array',
			'confirm' => 'required|in 1'
		]);

		if ($ret) {
			$count = count(array_unique(array_map('strval', $this->getInput('templateids', []))));
			$ret = $count === 1;
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
			'title' => _('Selected template update results'),
			'result' => null,
			'error' => null
		];

		try {
			$data['result'] = (new TemplateBatchUpdateService())->execute(
				$this->getInput('templateids', []),
				$this->getInput('evidence', [])
			);
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Batch update failed: '.$exception->getMessage());
			$data['error'] = _('Unable to execute the selected update batch. Inspect Zabbix and frontend logs before retrying.');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
