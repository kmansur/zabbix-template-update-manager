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
 * Serves both the lightweight batch-preparation queue shell and one bounded
 * asynchronous preparation request for a single template.
 *
 * Reusing the already registered action avoids depending on a second module
 * route for the browser-driven queue. This action never imports Zabbix
 * configuration.
 */
class TemplateBatchPrepare extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateids' => 'array_id',
			'templateid' => 'id',
			'async' => 'in 1'
		]);

		$async = (int) $this->getInput('async', 0) === 1;

		if ($ret && $async) {
			$templateId = trim((string) $this->getInput('templateid', ''));
			$ret = $templateId !== '' && ctype_digit($templateId) && (int) $templateId > 0;
		}
		elseif ($ret) {
			$templateIds = $this->getInput('templateids', []);
			$count = count(array_unique(array_map('strval', is_array($templateIds) ? $templateIds : [])));
			$ret = $count >= 1 && $count <= TemplateBatchPlanService::MAX_TEMPLATES;
		}

		if (!$ret) {
			if ($async) {
				$this->setResponse(
					(new CControllerResponseData([
						'main_block' => json_encode([
							'ok' => false,
							'item' => null,
							'error' => _('Invalid single-template preparation request.')
						], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
					]))->disableView()
				);
			}
			else {
				$this->setResponse(new CControllerResponseFatal());
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		if ((int) $this->getInput('async', 0) === 1) {
			$this->prepareOne((string) $this->getInput('templateid'));
			return;
		}

		$this->renderQueue();
	}

	private function prepareOne(string $templateId): void {
		$output = [
			'ok' => false,
			'item' => null,
			'error' => null
		];

		try {
			$plan = (new TemplateBatchPlanService())->build([$templateId], true);
			$item = $plan['items'][0] ?? null;
			if (!is_array($item)) {
				throw new RuntimeException('Single-template batch preparation returned no item.');
			}

			$output['ok'] = true;
			$output['item'] = $item;
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Batch preparation failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));
			$output['error'] = _('Unable to prepare this template. No Zabbix configuration import was attempted.');
		}

		$this->setResponse(
			(new CControllerResponseData([
				'main_block' => json_encode(
					$output,
					JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
				)
			]))->disableView()
		);
	}

	private function renderQueue(): void {
		$templateIds = array_values(array_unique(array_map(
			'strval',
			$this->getInput('templateids', [])
		)));

		$data = [
			'title' => _('Prepare selected template updates'),
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
				'Unable to rebuild the selected template set. No preparation or configuration import was attempted.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
