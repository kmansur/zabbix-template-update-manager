<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';

/**
 * Read-only rollback-backup inventory for one template.
 */
class TemplateBackupList extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateid' => 'required|db hosts.hostid'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
	}

	protected function doAction(): void {
		$templateId = (string) $this->getInput('templateid');
		$data = [
			'title' => _('Rollback backup history'),
			'template' => null,
			'can_rollback' => $this->getUserType() === USER_TYPE_SUPER_ADMIN,
			'repository_status' => 'repository_unavailable',
			'artifacts' => [],
			'scanned' => 0,
			'valid' => 0,
			'invalid' => 0,
			'truncated' => false,
			'error' => null
		];

		try {
			$record = (new TemplateRepository())->findById($templateId);
			if ($record === null) {
				throw new RuntimeException('The requested template is not visible to the current user.');
			}

			$inventory = TemplateInventoryService::fromRecords([$record]);
			$data['template'] = $inventory['templates'][0];

			$inspection = (new TemplateBackupRepository())->inspectForTemplate($templateId, 50);
			$data['repository_status'] = (string) ($inspection['status'] ?? 'repository_unavailable');
			$data['scanned'] = (int) ($inspection['scanned'] ?? 0);
			$data['valid'] = (int) ($inspection['valid'] ?? 0);
			$data['invalid'] = (int) ($inspection['invalid'] ?? 0);
			$data['truncated'] = !empty($inspection['truncated']);

			foreach (($inspection['artifacts'] ?? []) as $artifact) {
				if (!is_array($artifact)) {
					continue;
				}

				$data['artifacts'][] = [
					'status' => (string) ($artifact['status'] ?? 'invalid'),
					'reason' => (string) ($artifact['reason'] ?? ''),
					'created_at' => (string) ($artifact['created_at'] ?? ''),
					'vendor_version' => (string) ($artifact['vendor_version'] ?? ''),
					'bytes' => array_key_exists('bytes', $artifact) ? (int) $artifact['bytes'] : null,
					'sha256' => (string) ($artifact['sha256'] ?? ''),
					'source_file' => (string) ($artifact['source_file'] ?? ''),
					'manifest_file' => (string) ($artifact['manifest_file'] ?? '')
				];
			}
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Backup history failed for template %s: %s',
				$templateId,
				$exception->getMessage()
			));
			$data['error'] = _(
				'Unable to load rollback backup history. Check frontend logs and persistent backup-directory permissions.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
