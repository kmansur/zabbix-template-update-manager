<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateBatchPlanService;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateBatchPlanService.php';

/**
 * Prepares exactly one selected template for batch classification.
 *
 * This action is registered with layout.json so the browser queue receives
 * JSON rather than the default module layout.htmlpage wrapper.
 *
 * It may create/refresh rollback evidence but never imports Zabbix
 * configuration.
 */
class TemplateBatchPrepareOne extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|id'
		]);

		if (!$ret) {
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
}
