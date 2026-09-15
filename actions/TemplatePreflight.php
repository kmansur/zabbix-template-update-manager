<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdatePreflightService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateUpdatePreflightService.php';

/**
 * Recomputes the complete update preflight for one template.
 *
 * CSRF validation intentionally remains enabled. The action performs no
 * Zabbix configuration write and returns only freshly recomputed evidence.
 */
class TemplatePreflight extends CController {

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
		$templateId = (string) $this->getInput('templateid');
		$data = [
			'title' => _('Template update preflight'),
			'templateid' => $templateId,
			'preflight' => null,
			'preflight_error' => null,
			'can_update' => $this->getUserType() === USER_TYPE_SUPER_ADMIN
		];

		try {
			$data['preflight'] = (new TemplateUpdatePreflightService())->run($templateId);
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Update preflight failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));
			$data['preflight_error'] = _(
				'Unable to complete the update preflight. No Zabbix configuration change was attempted.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
