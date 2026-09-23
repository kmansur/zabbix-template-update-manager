<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateRollbackPreflightService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateRollbackPreflightService.php';

/**
 * Read-only rollback review. It revalidates the selected artifact and compares
 * it with the current installed template before any confirmation is rendered.
 */
class TemplateRollbackReview extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|db hosts.hostid',
			'manifest_file' => 'required|string'
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
		$manifestFile = (string) $this->getInput('manifest_file');
		$data = [
			'title' => _('Template rollback review'),
			'templateid' => $templateId,
			'manifest_file' => $manifestFile,
			'preflight' => null,
			'preflight_error' => null
		];

		try {
			$data['preflight'] = (new TemplateRollbackPreflightService())->run($templateId, $manifestFile);
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Rollback review failed for template %s / %s: %s',
				$templateId,
				$manifestFile,
				$exception->getMessage()
			));
			$data['preflight_error'] = _(
				'The selected rollback artifact could not be safely revalidated. Review frontend logs and backup repository integrity.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
