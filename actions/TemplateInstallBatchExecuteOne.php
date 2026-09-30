<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Repository\BatchOperationRepository;
use Modules\ZabbixTemplateUpdateManager\Exception\ZtumException;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateControlledInstallService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationHistoryService;
use Throwable;

require_once dirname(__DIR__).'/src/Exception/ZtumException.php';
require_once dirname(__DIR__).'/src/Repository/BatchOperationRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateControlledInstallService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationLockService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationHistoryService.php';

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
						'error_code' => 'invalid_request',
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
		$operationId = strtolower(trim((string) $this->getInput('operation_id')));
		$output = ['ok' => false, 'result' => null, 'error_code' => null, 'error' => null];

		$operationException = null;

		$batchRepo = new BatchOperationRepository();
		$batchStarted = false;

		try {
			$evidence = (string) $this->getInput('evidence_sha256');
			$batchRepo->begin($operationId, 'uuid-'.$uuid, $evidence);
			$batchStarted = true;
			$result = (new TemplateOperationLockService())->run(
				'install',
				'uuid-'.$uuid,
				static fn(): array => (new TemplateControlledInstallService())->execute($uuid, $evidence)
			);

			$output['ok'] = true;
			$output['result'] = $result;
			$terminal = ($result['status'] ?? null) === 'installed' ? 'succeeded'
				: (($result['write_outcome'] ?? null) === 'uncertain' || !empty($result['write_performed']) ? 'uncertain' : 'failed');
			$batchRepo->finish($operationId, 'uuid-'.$uuid, $terminal, (string) ($result['status'] ?? ''), null);
		}
		catch (Throwable $exception) {
			$operationException = $exception;
			error_log(sprintf(
				'[Zabbix Template Update Manager] Request-bounded batch install failed for UUID %s: %s',
				$uuid,
				$exception->getMessage()
			));

			$output['error_code'] = $exception instanceof ZtumException
				? $exception->getMachineCode()
				: 'unexpected_error';
			if ($batchStarted) {
				try {
					$terminal = $output['error_code'] === 'lock_contended' ? 'failed' : 'uncertain';
					$batchRepo->finish($operationId, 'uuid-'.$uuid, $terminal, null, $output['error_code']);
				}
				catch (Throwable $batchException) {
					error_log('[Zabbix Template Update Manager] Unable to finalize persisted install batch state: '.$batchException->getMessage());
				}
			}

			$output['error'] = $exception->getMessage() !== ''
				? $exception->getMessage()
				: _(
					'Unable to complete this controlled installation request. Inspect the local template state before retrying.'
				);
		}

		(new TemplateOperationHistoryService())->recordBestEffort(
			'install',
			'uuid-'.$uuid,
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
