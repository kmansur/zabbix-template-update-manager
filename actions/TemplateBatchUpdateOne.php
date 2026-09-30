<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Repository\BatchOperationRepository;
use Modules\ZabbixTemplateUpdateManager\Exception\ZtumException;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledUpdateService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;
use Throwable;

require_once dirname(__DIR__).'/src/Exception/ZtumException.php';
require_once dirname(__DIR__).'/src/Repository/BatchOperationRepository.php';
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
			'operation_id' => 'required|string',
			'manual_override' => 'in 1'
		]);

		if ($ret) {
			$evidence = strtolower(trim((string) $this->getInput('evidence_sha256')));
			$operationId = strtolower(trim((string) $this->getInput('operation_id')));
			$ret = preg_match('/^[a-f0-9]{64}$/', $evidence) === 1
				&& preg_match('/^[a-f0-9]{32}$/', $operationId) === 1;
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
		$operationId = strtolower(trim((string) $this->getInput('operation_id')));
		$output = ['ok' => false, 'result' => null, 'error_code' => null, 'error' => null];

		$operationException = null;

		$batchRepo = new BatchOperationRepository();
		$batchStarted = false;

		try {
			$batchRepo->begin($operationId, 'template-'.$templateId, $evidence, 'update', (string) (CWebUser::$data['userid'] ?? ''));
			$batchStarted = true;
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
			$output['result'] = $result;
			$terminal = ($result['status'] ?? null) === 'updated' ? 'succeeded'
				: (!empty($result['write_performed']) ? 'uncertain' : 'failed');
			$batchRepo->finish($operationId, 'template-'.$templateId, $terminal, (string) ($result['status'] ?? ''), null);
			$output['ok'] = true;
		}
		catch (Throwable $exception) {
			$output['ok'] = false;
			$operationException = $exception;
			error_log(sprintf(
				'[Zabbix Template Update Manager] Request-bounded batch update failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));

			$output['error_code'] = $exception instanceof ZtumException
				? $exception->getMachineCode()
				: 'unexpected_error';
			if ($batchStarted) {
				try {
					$terminal = $output['error_code'] === 'lock_contended' ? 'failed' : 'uncertain';
					$batchRepo->finish($operationId, 'template-'.$templateId, $terminal, null, $output['error_code']);
				}
				catch (Throwable $batchException) {
					error_log('[Zabbix Template Update Manager] Unable to finalize persisted update batch state: '.$batchException->getMessage());
				}
			}

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
