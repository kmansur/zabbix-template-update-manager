<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledInstallService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateControlledInstallService.php';

class TemplateInstallBatchExecuteOne extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'uuid' => 'required|string',
			'evidence_sha256' => 'required|string',
			'confirm' => 'required|in 1'
		]);

		if ($ret) {
			$uuid = strtolower(str_replace('-', '', trim((string) $this->getInput('uuid'))));
			$evidence = strtolower(trim((string) $this->getInput('evidence_sha256')));

			$ret = preg_match('/^[a-f0-9]{32}$/', $uuid) === 1
				&& preg_match('/^[a-f0-9]{64}$/', $evidence) === 1;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'ok' => false,
						'result' => null,
						'error' => _('Invalid single-template installation execution request.')
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
		$output = ['ok' => false, 'result' => null, 'error' => null];

		try {
			$result = (new TemplateControlledInstallService())->execute(
				$uuid,
				(string) $this->getInput('evidence_sha256')
			);

			$output['ok'] = true;
			$output['result'] = $result;
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Request-bounded batch install failed for UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));

			$output['error'] = _(
				'Unable to complete this controlled installation request. Inspect the local template state before retrying.'
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
