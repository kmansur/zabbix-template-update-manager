<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateBatchPlanService.php';

/**
 * Creates/validates rollback artifacts and fresh preflight evidence for a
 * bounded selected set. This action never imports Zabbix configuration.
 */
class TemplateBatchPrepare extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateids' => 'required|array_id'
		]);

		if ($ret) {
			$count = count(array_unique(array_map('strval', $this->getInput('templateids', []))));
			$ret = $count >= 1 && $count <= TemplateBatchPlanService::MAX_TEMPLATES;
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
			'title' => _('Prepare selected template updates'),
			'plan' => null,
			'error' => null
		];

		try {
			$data['plan'] = (new TemplateBatchPlanService())->build(
				$this->getInput('templateids', []),
				true
			);
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Batch preparation failed: '.$exception->getMessage());
			$data['error'] = _('Unable to prepare the selected templates. No Zabbix configuration import was attempted.');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
