<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateBatchPlanService.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';

/**
 * Renders the batch-preparation queue shell.
 *
 * Heavy preparation is deliberately deferred to one request per template via
 * TemplateBatchPrepareOne, avoiding a single long-lived HTTP request for the
 * entire selection.
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
		$templateIds = array_values(array_unique(array_map(
			'strval',
			$this->getInput('templateids', [])
		)));

		$data = [
			'title' => _('Prepare template updates'),
			'templateids' => $templateIds,
			'templates' => [],
			'error' => null
		];

		try {
			$records = [];
			$repository = new TemplateRepository();

			foreach ($templateIds as $templateId) {
				$record = $repository->findById($templateId);
				if ($record === null) {
					throw new RuntimeException('One or more selected templates are no longer visible.');
				}
				$records[] = $record;
			}

			$inventory = TemplateInventoryService::fromRecords($records);
			$data['templates'] = $inventory['templates'];

			if (count($data['templates']) !== count($templateIds)) {
				throw new RuntimeException('Unable to rebuild the selected batch shell.');
			}
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Batch preparation shell failed: '.$exception->getMessage());
			$data['error'] = _(
				'Unable to prepare the selected template set. No configuration import was attempted.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
