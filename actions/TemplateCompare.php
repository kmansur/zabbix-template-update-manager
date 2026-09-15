<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CImportReaderFactory;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateHistoryRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use Modules\ZabbixTemplateUpdateManager\Service\ContentComparisonClassifier;
use Modules\ZabbixTemplateUpdateManager\Service\HistoricalTemplateBaselineService;
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
require_once dirname(__DIR__).'/src/Repository/UpstreamTemplateHistoryRepository.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamTemplateSourceRepository.php';
require_once dirname(__DIR__).'/src/Service/ContentComparisonClassifier.php';
require_once dirname(__DIR__).'/src/Service/HistoricalTemplateBaselineService.php';
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
			'comparison_error' => null,
			'historical_baseline' => null,
			'historical_summary' => ImportCompareSummary::summarize([]),
			'historical_error' => null
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

			$sourceRepository = new UpstreamTemplateSourceRepository();
			$sourceFile = $sourceRepository->fetch($index['source'], $template['upstream']);
			$reader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
			$document = $reader->read($sourceFile['content']);
			$isolated = UpstreamTemplateDocumentService::buildImportSource(
				$document,
				$template['uuid'],
				$template['upstream']
			);
			$compareService = new TemplateImportCompareService();
			$diff = $compareService->compare($isolated['source']);
			$data['comparison_summary'] = ImportCompareSummary::summarize($diff);
			$data['content_status'] = ContentComparisonClassifier::classify(
				$template['version_status'],
				$data['comparison_summary']
			);
			$data['source_path'] = $sourceFile['path'];

			if (($template['version_status'] ?? null) === 'update_available'
					&& ($template['vendor_version'] ?? '') !== '') {
				try {
					$baselineService = new HistoricalTemplateBaselineService(
						static fn(string $path, string $until, int $limit): array
							=> (new UpstreamTemplateHistoryRepository())->listCommits($path, $until, $limit),
						static fn(string $commit, string $path): array
							=> $sourceRepository->fetchAtCommit($commit, $path),
						static function (string $source): array {
							$historicalReader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
							return $historicalReader->read($source);
						}
					);

					$baseline = $baselineService->find(
						$sourceFile['path'],
						(string) ($index['source']['commit'] ?? ''),
						$template['uuid'],
						$template['vendor_version'],
						(string) ($template['upstream']['vendor_name'] ?? 'Zabbix')
					);

					$baselineSource = $baseline['source'];
					unset($baseline['source']);
					$data['historical_baseline'] = $baseline;

					if (($baseline['status'] ?? null) === 'found' && is_string($baselineSource)) {
						$historicalDiff = $compareService->compare($baselineSource);
						$data['historical_summary'] = ImportCompareSummary::summarize($historicalDiff);
					}

					$data['content_status'] = ContentComparisonClassifier::classify(
						$template['version_status'],
						$data['comparison_summary'],
						(string) ($baseline['status'] ?? ''),
						$data['historical_summary']
					);
				}
				catch (Throwable $exception) {
					error_log(sprintf(
						'[Zabbix Template Update Manager] Historical baseline lookup failed for template %s: %s',
						(string) $this->getInput('templateid'),
						$exception->getMessage()
					));
					$data['historical_error'] = _(
						'Unable to resolve the historical official baseline. The current-upstream comparison remains valid as an update preview.'
					);
				}
			}
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
