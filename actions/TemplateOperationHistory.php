<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CPagerHelper;
use CUrl;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateOperationHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateHistorySubjectResolver;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateOperationHistoryRepository.php';
require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateHistorySubjectResolver.php';

class TemplateOperationHistory extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return $this->validateInput(['page' => 'ge 1']);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$data = [
			'title' => _('ZTUM operation history'),
			'entries' => [],
			'paging' => null,
			'error' => null
		];

		try {
			$data['entries'] = (new TemplateOperationHistoryRepository())->recent(1000);
			// Resolve display labels in one read-only Zabbix API query. Historical
			// subjects are retained verbatim for missing or deleted templates.
			try {
				$data['entries'] = TemplateHistorySubjectResolver::resolve(
					$data['entries'],
					(new TemplateRepository())->findAll()
				);
			}
			catch (Throwable $lookupException) {
				error_log('[Zabbix Template Update Manager] History template-name lookup unavailable: '.$lookupException->getMessage());
			}

			$pageNum = $this->getInput('page', 1);
			CPagerHelper::savePage('ztum.operation.history', $pageNum);
			$data['paging'] = CPagerHelper::paginate(
				$pageNum,
				$data['entries'],
				ZBX_SORT_UP,
				(new CUrl('zabbix.php'))->setArgument('action', 'ztum.operations')
			);
		}
		catch (Throwable $exception) {
			error_log('[Zabbix Template Update Manager] Operation history read failed: '.$exception->getMessage());
			$data['error'] = _(
				'Operation history is unavailable. Check the private ZTUM runtime directory and frontend logs.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
