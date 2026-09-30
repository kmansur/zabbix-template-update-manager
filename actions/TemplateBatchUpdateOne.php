<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Exception\ZtumException;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledUpdateService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;
use Throwable;

require_once dirname(__DIR__).'/src/Exception/ZtumException.php';
require_once dirname(__DIR__).'/src/Service/TemplateControlledUpdateService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationLockService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationHistoryService.php';

/**
 * Executes exactly one previously prepared Ready template.
 *
 * Batch sequencing lives in the browser so cumulative batch duration never
 * occupies one reverse-proxy request. The controlled update service still
 * reruns fresh preflight, verifies bound evidence and owns fail-closed
 * semantics for this template.
 */
class TemplateBatchUpdateOne extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|id',
			'evidence_sha256' => 'required|string',
			'confirm' => 'required|in 1',
			'manual_override' => 'in 1'
		]);

		if ($ret) {
			$evidence = strtolower(trim((string) $this->getInput('evidence_sha256')));
			$ret = preg_match('/^[a-f0-9]{64}$/', $evidence) === 1;
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'ok' => false,
						'result' => null,
						'error_code' => 'invalid_request',
						'error' => _('Invalid single-template update execution request.')
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
		$evidence = strtolower(trim((string) $this->getInput('evidence_sha256')));
		$output = ['ok' => false, 'result' => null, 'error_code' => null, 'error' => null];

		$operationException = null;

		try {
			$manualOverride = (string) $this->getInput('manual_override', '') === '1';
			$result = (new TemplateOperationLockService())->run(
				'update',
				'template-'.$templateId,
				static fn(): array => (new TemplateControlledUpdateService())->execute(
					$templateId,
					$evidence,
					$manualOverride,
					true,
					true
				)
			);
			$output['ok'] = true;
			$output['result'] = $result;
		}
		catch (Throwable $exception) {
			$operationException = $exception;
			error_log(sprintf(
				'[Zabbix Template Update Manager] Request-bounded batch update failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));

			$output['error_code'] = $exception instanceof ZtumException
				? $exception->getMachineCode()
				: 'unexpected_error';
			$output['error'] = $exception->getMessage() !== ''
				? $exception->getMessage()
				: _('Unable to complete this controlled update request. Inspect the local template state before retrying.');
		}

		(new TemplateOperationHistoryService())->recordBestEffort(
			'update',
			'template-'.$templateId,
			$output['result'],
			$operationException,
			(string) (CWebUser::$data['userid'] ?? '')
		);

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
