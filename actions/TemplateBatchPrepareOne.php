<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateBatchPlanService.php';

/**
 * Prepares exactly one selected template for batch classification.
 *
 * The browser drives the bounded queue one template at a time so preparation
 * never depends on one long-lived HTTP request for the complete selection.
 * This action may create/refresh rollback evidence but never imports Zabbix
 * configuration.
 */
class TemplateBatchPrepareOne extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|id'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$templateId = (string) $this->getInput('templateid');
		$output = [
			'ok' => false,
			'item' => null,
			'error' => null
		];

		try {
			$plan = (new TemplateBatchPlanService())->build([$templateId], true);
			$item = $plan['items'][0] ?? null;
			if (!is_array($item)) {
				throw new \RuntimeException('Single-template batch preparation returned no item.');
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

		$response = new CControllerResponseData([
			'main_block' => json_encode(
				$output,
				JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
			)
		]);
		$this->setResponse($response->disableView());
	}
}
