<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';
require_once dirname(__DIR__).'/src/Support/ZabbixVersion.php';

class TemplateList extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$data = [
			'title' => _('Zabbix Template Update Manager'),
			'version' => '0.1.0-dev',
			'status' => _('Read-only inventory'),
			'zabbix_version' => ZabbixVersion::current(),
			'zabbix_supported' => ZabbixVersion::isSupported(),
			'templates' => [],
			'summary' => TemplateInventoryService::emptySummary(),
			'inventory_error' => null
		];

		if (!$data['zabbix_supported']) {
			$data['inventory_error'] = _('Template inventory is disabled on unsupported or undetected Zabbix versions.');
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		try {
			$inventory = (new TemplateInventoryService(new TemplateRepository()))->getInventory();
			$data['templates'] = $inventory['templates'];
			$data['summary'] = $inventory['summary'];
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Template inventory failed: %s',
				$exception->getMessage()
			));
			$data['inventory_error'] = _(
				'Unable to load the template inventory. Check frontend logs and the current user permissions.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
