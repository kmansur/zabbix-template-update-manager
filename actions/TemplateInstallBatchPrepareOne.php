<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInstallBatchPlanService;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateInstallBatchPlanService.php';

class TemplateInstallBatchPrepareOne extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput(['uuid' => 'required|string']);
		if ($ret) {
			$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
			$ret = preg_match('/^[a-f0-9]{32}$/', $uuid) === 1;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'ok' => false,
						'item' => null,
						'error' => _('Invalid single-template installation preparation request.')
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
		$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
		$output = ['ok' => false, 'item' => null, 'error' => null];

		try {
			$plan = (new TemplateInstallBatchPlanService())->build([$uuid]);
			$item = $plan['items'][0] ?? null;
			if (!is_array($item)) {
				throw new RuntimeException('Single-template installation preparation returned no item.');
			}

			$output['ok'] = true;
			$output['item'] = $item;
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Batch install preparation failed for UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));
			$output['error'] = _(
				'Unable to prepare this installation candidate. No Zabbix configuration import was attempted.'
			);
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
