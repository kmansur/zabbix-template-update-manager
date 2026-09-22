<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CPagerHelper;
use CUrl;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateVersionComparator;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamCatalogService;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamMatcher;
use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';
require_once dirname(__DIR__).'/src/Service/TemplateVersionComparator.php';
require_once dirname(__DIR__).'/src/Service/UpstreamCatalogService.php';
require_once dirname(__DIR__).'/src/Service/UpstreamMatcher.php';
require_once dirname(__DIR__).'/src/Support/ProjectVersion.php';
require_once dirname(__DIR__).'/src/Support/ZabbixVersion.php';

class TemplateList extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'page' => 'ge 1'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$canAdminister = in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
		$zabbixVersion = ZabbixVersion::current();

		$data = [
			'title' => _('Zabbix Template Update Manager'),
			'version' => ProjectVersion::current(),
			'status' => _('Laboratory beta: inventory, comparison, controlled update and rollback'),
			'zabbix_version' => $zabbixVersion,
			'zabbix_supported' => ZabbixVersion::isSupported(),
			'can_compare' => $canAdminister,
			'can_install' => $this->getUserType() === USER_TYPE_SUPER_ADMIN,
			'show_diagnostics' => $canAdminister,
			'templates' => [],
			'summary' => TemplateInventoryService::emptySummary(),
			'upstream_summary' => UpstreamMatcher::emptySummary(),
			'catalog_summary' => UpstreamCatalogService::emptySummary(),
			'version_summary' => TemplateVersionComparator::emptySummary(),
			'paging' => null,
			'upstream_source' => null,
			'upstream_runtime' => null,
			'upstream_diagnostics' => [
				'endpoint' => UpstreamIndexRepository::endpointForVersion($zabbixVersion),
				'transports' => UpstreamIndexRepository::transportCapabilities(),
				'detail' => null
			],
			'inventory_error' => null,
			'upstream_error' => null,
			'upstream_warning' => null
		];

		if (!$data['zabbix_supported']) {
			$data['inventory_error'] = _('Template inventory is disabled on unsupported or undetected Zabbix versions.');
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		try {
			$inventory = (new TemplateInventoryService(new TemplateRepository()))->getInventory();
			$data['templates'] = $inventory['templates'];
			$data['summary'] = $inventory['summary'];
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Template inventory failed: %s',
				$exception->getMessage()
			));
			$data['inventory_error'] = _(
				'Unable to load the template inventory. Check frontend logs and the current user permissions.'
			);
			$this->setResponse(new CControllerResponseData($data));
			return;
		}

		try {
			$index = (new UpstreamIndexRepository())->load($data['zabbix_version']);
			$matched = UpstreamMatcher::attach($data['templates'], $index);
			$catalog = UpstreamCatalogService::merge($matched['templates'], $index);
			$data['templates'] = $catalog['templates'];
			$data['upstream_summary'] = $matched['summary'];
			$data['catalog_summary'] = $catalog['summary'];
			$data['upstream_source'] = $index['source'] ?? null;
			$data['upstream_runtime'] = $index['runtime'] ?? null;

			if (($data['upstream_runtime']['cache_status'] ?? null) === 'stale') {
				$data['upstream_warning'] = _(
					'The upstream repository could not be refreshed. A previously cached index is being used.'
				);
			}
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Upstream index failed: %s',
				$exception->getMessage()
			));
			$matched = UpstreamMatcher::attach($data['templates'], null);
			$data['templates'] = $matched['templates'];
			$data['upstream_summary'] = $matched['summary'];
			$data['upstream_error'] = _(
				'Unable to check the official upstream template index. The local inventory remains available.'
			);
			if ($data['show_diagnostics']) {
				$data['upstream_diagnostics']['detail'] = self::diagnosticMessage($exception);
			}
		}

		$versionComparison = TemplateVersionComparator::attach($data['templates']);
		$data['templates'] = $versionComparison['templates'];
		$data['version_summary'] = $versionComparison['summary'];

		order_result($data['templates'], 'name', ZBX_SORT_UP);
		$pageNum = $this->getInput('page', 1);
		CPagerHelper::savePage('ztum.template.catalog', $pageNum);
		$data['paging'] = CPagerHelper::paginate(
			$pageNum,
			$data['templates'],
			ZBX_SORT_UP,
			(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
		);

		$this->setResponse(new CControllerResponseData($data));
	}

	private static function diagnosticMessage(Throwable $exception): string {
		$message = trim($exception->getMessage());
		$message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? '';
		return strlen($message) > 600 ? substr($message, 0, 600).'…' : $message;
	}
}
