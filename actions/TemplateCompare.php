<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdateAnalysisService;

require_once dirname(__DIR__).'/src/Service/TemplateUpdateAnalysisService.php';

class TemplateCompare extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|db hosts.hostid'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
	}

	protected function doAction(): void {
		$data = (new TemplateUpdateAnalysisService())->analyze(
			(string) $this->getInput('templateid')
		);
		$data['title'] = _('Template update review');

		$this->setResponse(new CControllerResponseData($data));
	}
}
