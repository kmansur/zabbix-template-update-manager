<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallBatchPlanService;

require_once dirname(__DIR__).'/src/Service/TemplateInstallBatchPlanService.php';

class TemplateInstallBatchPrepare extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput(['uuids' => 'required|array']);

		if ($ret) {
			$uuids = $this->normalizeUuids($this->getInput('uuids', []));
			$ret = count($uuids) >= 1 && count($uuids) <= TemplateInstallBatchPlanService::MAX_TEMPLATES;
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
		$this->setResponse(new CControllerResponseData([
			'title' => _('Prepare selected template installations'),
			'uuids' => $this->normalizeUuids($this->getInput('uuids', []))
		]));
	}

	private function normalizeUuids(array $uuids): array {
		$result = [];
		foreach ($uuids as $uuid) {
			$uuid = strtolower(str_replace('-', '', trim((string) $uuid)));
			if (preg_match('/^[a-f0-9]{32}$/', $uuid) !== 1) {
				return [];
			}
			$result[$uuid] = true;
		}
		return array_keys($result);
	}
}
