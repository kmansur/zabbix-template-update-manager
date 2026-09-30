<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Repository\BatchOperationRepository;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/BatchOperationRepository.php';

class BatchOperationState extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'operation_id' => 'required|string',
			'recover_stale' => 'in 1'
		]);
		if (!$ret) {
			$this->json(['ok' => false, 'operation' => null, 'error' => _('Invalid batch state request.')]);
		}
		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		try {
			$repo = new BatchOperationRepository();
			$id = (string) $this->getInput('operation_id');
			$state = (string) $this->getInput('recover_stale', '') === '1'
				? $repo->markStaleRunningUncertain($id)
				: $repo->load($id);
			$this->json(['ok' => true, 'operation' => $state, 'error' => null]);
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Batch state read failed: '.$exception->getMessage());
			$this->json(['ok' => false, 'operation' => null, 'error' => _('Unable to read the persisted batch state.')]);
		}
	}

	private function json(array $payload): void {
		$this->setResponse((new CControllerResponseData([
			'main_block' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
		]))->disableView());
	}
}
