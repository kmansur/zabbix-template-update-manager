<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CImportReaderFactory;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use Modules\ZabbixTemplateUpdateManager\Service\ContentComparisonClassifier;
use Modules\ZabbixTemplateUpdateManager\Service\ImportCompareSummary;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateImportCompareService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateVersionComparator;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamMatcher;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamTemplateDocumentService;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamTemplateSourceRepository.php';
require_once dirname(__DIR__).'/src/Service/ContentComparisonClassifier.php';
require_once dirname(__DIR__).'/src/Service/ImportCompareSummary.php';
require_once dirname(__DIR__).'/src/Service/TemplateImportCompareService.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';
require_once dirname(__DIR__).'/src/Service/TemplateVersionComparator.php';
require_once dirname(__DIR__).'/src/Service/UpstreamMatcher.php';
require_once dirname(__DIR__).'/src/Service/UpstreamTemplateDocumentService.php';
require_once dirname(__DIR__).'/src/Support/ZabbixVersion.php';

class TemplateCompare extends CController {

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
		$data = [
			'title' => _('Template content comparison'),
			'zabbix_version' => ZabbixVersion::current(),
			'template' => null,
			'upstream_source' => null,
			'source_path' => '',
			'comparison_summary' => ImportCompareSummary::summarize([]),
			'content_status' => 'not_available',
			'comparison_error' => null
		];

		if (!ZabbixVersion::isSupported($data['zabbix_version'])) {
			$data['comparison_error'] = _('Content comparison is disabled on unsupported or undetected Zabbix versions.');
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		try {
			$record = (new TemplateRepository())->findById((string) $this->getInput('templateid'));
			if ($record === null) {
				throw new RuntimeException('The requested template is not visible to the current user.');
			}

			$inventory = TemplateInventoryService::fromRecords([$record]);
			$template = $inventory['templates'][0];
			$index = (new UpstreamIndexRepository())->load($data['zabbix_version']);
			$matched = UpstreamMatcher::attach([$template], $index);
			$versioned = TemplateVersionComparator::attach($matched['templates']);
			$template = $versioned['templates'][0];
			$data['template'] = $template;
			$data['upstream_source'] = $index['source'] ?? null;

			if (($template['upstream_status'] ?? null) !== 'official_match'
					|| !is_array($template['upstream'] ?? null)) {
				$data['comparison_error'] = _(
					'Content comparison is available only for templates with an authoritative official UUID match.'
				);
				$this->setResponse(new CControllerResponseData($data));
				return;
			}

			$sourceFile = (new UpstreamTemplateSourceRepository())->fetch($index['source'], $template['upstream']);
			$reader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
			$document = $reader->read($sourceFile['content']);
			$isolated = UpstreamTemplateDocumentService::buildImportSource(
				$document,
				$template['uuid'],
				$template['upstream']
			);
			$diff = (new TemplateImportCompareService())->compare($isolated['source']);
			$data['comparison_summary'] = ImportCompareSummary::summarize($diff);
			$data['content_status'] = ContentComparisonClassifier::classify(
				$template['version_status'],
				$data['comparison_summary']
			);
			$data['source_path'] = $sourceFile['path'];
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Content comparison failed for template %s: %s',
				(string) $this->getInput('templateid'),
				$exception->getMessage()
			));
			$data['comparison_error'] = _(
				'Unable to complete the read-only content comparison. Check frontend logs, network access to the official Zabbix repository and the current user role permissions.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
