<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CDiv;
use CLink;
use CPagerHelper;
use CProfile;
use CTag;
use CUrl;
use CWebUser;
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
			'page' => 'ge 1',
			'filter_set' => 'in 1',
			'filter_rst' => 'in 1',
			'filter_status' => 'in all,current,not_applicable,update_available,not_installed',
			'show_all' => 'in 1'
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

		if ($this->hasInput('filter_set')) {
			CProfile::update(
				'web.ztum.templates.filter.status',
				$this->getInput('filter_status', 'all'),
				PROFILE_TYPE_STR
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.ztum.templates.filter.status');
		}

		$filterStatus = (string) CProfile::get('web.ztum.templates.filter.status', 'all');
		$allowedStatuses = ['all', 'current', 'not_applicable', 'update_available', 'not_installed'];
		if (!in_array($filterStatus, $allowedStatuses, true)) {
			$filterStatus = 'all';
		}

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
			'filter' => [
				'status' => $filterStatus
			],
			'filter_profile' => 'web.ztum.templates.filter',
			'filter_active_tab' => CProfile::get('web.ztum.templates.filter.active', 1),
			'show_all' => $this->hasInput('show_all'),
			'filtered_count' => 0,
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

		if ($filterStatus !== 'all') {
			$data['templates'] = array_values(array_filter(
				$data['templates'],
				static fn(array $template): bool =>
					(string) ($template['version_status'] ?? 'not_applicable') === $filterStatus
			));
		}

		order_result($data['templates'], 'name', ZBX_SORT_UP);
		$data['filtered_count'] = count($data['templates']);
		$rowsPerPage = max(1, (int) (CWebUser::$data['rows_per_page'] ?? 1));
		$needsPagination = $data['filtered_count'] > $rowsPerPage;

		// Do not expose All/Pages display controls when the complete filtered
		// result already fits on one native Zabbix page.
		if (!$needsPagination) {
			$data['show_all'] = false;
		}

		$listUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');

		if ($data['show_all']) {
			$pagesUrl = clone $listUrl;
			$pagesUrl->removeArgument('show_all');

			$data['paging'] = (new CDiv())
				->addClass(ZBX_STYLE_TABLE_PAGING)
				->addItem(
					(new CTag('nav', true))
						->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
						->setAttribute('role', 'navigation')
						->setAttribute('aria-label', _x('Pager', 'page navigation'))
						->addItem(
							(new CLink(_('Pages'), $pagesUrl->getUrl()))
								->setAttribute('aria-label', _('Return to paginated view'))
						)
						->addItem(
							(new CDiv())
								->addClass(ZBX_STYLE_TABLE_STATS)
								->addItem(_s('Displaying all %1$s found', $data['filtered_count']))
						)
				);
		}
		else {
			$pageNum = $this->getInput('page', 1);
			CPagerHelper::savePage('ztum.template.catalog', $pageNum);
			$data['paging'] = CPagerHelper::paginate(
				$pageNum,
				$data['templates'],
				ZBX_SORT_UP,
				$listUrl
			);

			if ($needsPagination) {
				$allUrl = clone $listUrl;
				$allUrl->setArgument('show_all', '1');

				$data['paging']->addItem(
					(new CTag('nav', true,
						(new CLink(_('All'), $allUrl->getUrl()))
							->setAttribute('aria-label', _('Show all matching templates'))
					))
						->addClass(ZBX_STYLE_PAGING_BTN_CONTAINER)
						->setAttribute('role', 'navigation')
						->setAttribute('aria-label', _('Catalog display mode'))
				);
			}
		}

		$this->setResponse(new CControllerResponseData($data));
	}

	private static function diagnosticMessage(Throwable $exception): string {
		$message = trim($exception->getMessage());
		$message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? '';
		return strlen($message) > 600 ? substr($message, 0, 600).'…' : $message;
	}
}
