<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;
use CWebUser;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateUpdatePolicyRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateOperationLockService;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamMatcher;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Repository/TemplateUpdatePolicyRepository.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';
require_once dirname(__DIR__).'/src/Service/TemplateOperationLockService.php';
require_once dirname(__DIR__).'/src/Service/UpstreamMatcher.php';
require_once dirname(__DIR__).'/src/Support/ZabbixVersion.php';

/**
 * Changes the persistent ZTUM update policy for selected installed templates.
 *
 * This action never changes Zabbix configuration. Native CSRF validation stays
 * enabled and only Super Admin users may change the global policy.
 */
class TemplateUpdatePolicy extends CController {

	private const MAX_TEMPLATES = 500;

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateids' => 'required|array_id',
			'policy_operation' => 'required|in never_update,allow_updates'
		]);

		if ($ret) {
			$count = count(array_unique(array_map('strval', $this->getInput('templateids', []))));
			$ret = $count >= 1 && $count <= self::MAX_TEMPLATES;
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$templateIds = array_values(array_unique(array_map(
			'strval',
			$this->getInput('templateids', [])
		)));
		$operation = (string) $this->getInput('policy_operation');
		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
		);

		try {
			$repository = new TemplateRepository();
			$records = [];

			foreach ($templateIds as $templateId) {
				$record = $repository->findById($templateId);
				if ($record === null) {
					throw new RuntimeException('One or more selected templates are no longer visible.');
				}
				$records[] = $record;
			}

			$inventory = TemplateInventoryService::fromRecords($records);
			$templates = $inventory['templates'];
			if (count($templates) !== count($templateIds)) {
				throw new RuntimeException('Unable to rebuild the selected update-policy set.');
			}

			if ($operation === 'never_update') {
				$index = (new UpstreamIndexRepository())->load(ZabbixVersion::current());
				$matched = UpstreamMatcher::attach($templates, $index);
				$templates = $matched['templates'];

				foreach ($templates as $template) {
					if (($template['upstream_status'] ?? null) !== 'official_match'
							|| trim((string) ($template['uuid'] ?? '')) === '') {
						throw new RuntimeException(
							'Only installed templates with an authoritative official UUID match can be marked Never update.'
						);
					}
				}
			}

			$userId = trim((string) (CWebUser::$data['userid'] ?? ''));
			$result = (new TemplateOperationLockService())->run(
				'policy',
				'selected-templates',
				static function () use ($operation, $templates, $userId): array {
					$policy = new TemplateUpdatePolicyRepository();
					return $operation === 'never_update'
						? $policy->setNeverUpdate($templates, $userId)
						: $policy->allowUpdates($templates, $userId);
				}
			);

			$response->setFormData(['uncheck' => '1']);
			$count = count($templates);

			if ($operation === 'never_update') {
				CMessageHelper::setSuccessTitle(_n(
					'Template marked Never update',
					'Templates marked Never update',
					$count
				));
				info(_n(
					'The selected template will be blocked from ZTUM updates until updates are allowed again.',
					'The selected templates will be blocked from ZTUM updates until updates are allowed again.',
					$count
				));
			}
			else {
				CMessageHelper::setSuccessTitle(_n(
					'Template updates allowed',
					'Template updates allowed',
					$count
				));
				info(_n(
					'The selected template can participate in ZTUM updates again.',
					'The selected templates can participate in ZTUM updates again.',
					$count
				));
			}
		}
		catch (Throwable $exception) {
			error_log(
				'[Zabbix Template Update Manager] Update-policy change failed: '.$exception->getMessage()
			);
			CMessageHelper::setErrorTitle(_('Cannot change template update policy'));
			error(_(
				'The update policy was not changed. Check frontend logs and the ZTUM runtime directory permissions.'
			));
		}

		$this->setResponse($response);
	}
}
