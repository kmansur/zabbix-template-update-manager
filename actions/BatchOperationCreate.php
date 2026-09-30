<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Repository\BatchOperationRepository;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/BatchOperationRepository.php';

class BatchOperationCreate extends CController {

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'type' => 'required|in update,install',
			'entries' => 'required|string'
		]);
		if (!$ret) {
			$this->json(['ok' => false, 'operation' => null, 'error' => _('Invalid persisted batch request.')]);
		}
		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		try {
			$entries = json_decode((string) $this->getInput('entries'), true, 64, JSON_THROW_ON_ERROR);
			if (!is_array($entries)) {
				throw new \RuntimeException('Batch entries payload is invalid.');
			}
			$state = (new BatchOperationRepository())->create(
				(string) $this->getInput('type'),
				$entries,
				(string) (CWebUser::$data['userid'] ?? '')
			);
			$this->json(['ok' => true, 'operation' => $state, 'error' => null]);
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Persisted batch creation failed: '.$exception->getMessage());
			$this->json(['ok' => false, 'operation' => null, 'error' => _('Unable to persist the reviewed batch plan.')]);
		}
	}

	private function json(array $payload): void {
		$this->setResponse((new CControllerResponseData([
			'main_block' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
		]))->disableView());
	}
}
