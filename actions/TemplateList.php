<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CPagerHelper;
use CProfile;
use CUrl;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateUpdatePolicyRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateVersionComparator;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamCatalogService;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamMatcher;
use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Repository/TemplateUpdatePolicyRepository.php';
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
			'filter_name' => 'string',
			'filter_status' => 'in all,current,not_applicable,update_available,not_installed,never_update'
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
		$canAdminister = in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
		$zabbixVersion = ZabbixVersion::current();

		if ($this->hasInput('filter_set')) {
			CProfile::update(
				'web.ztum.templates.filter.name',
				trim((string) $this->getInput('filter_name', '')),
				PROFILE_TYPE_STR
			);
			CProfile::update(
				'web.ztum.templates.filter.status',
				$this->getInput('filter_status', 'all'),
				PROFILE_TYPE_STR
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.ztum.templates.filter.name');
			CProfile::delete('web.ztum.templates.filter.status');
		}

		$filterName = trim((string) CProfile::get('web.ztum.templates.filter.name', ''));
		$filterStatus = (string) CProfile::get('web.ztum.templates.filter.status', 'all');
		$allowedStatuses = ['all', 'current', 'not_applicable', 'update_available', 'not_installed', 'never_update'];
		if (!in_array($filterStatus, $allowedStatuses, true)) {
			$filterStatus = 'all';
		}

		$data = [
			'title' => _('Zabbix Template Update Manager'),
			'version' => ProjectVersion::current(),
			'status' => _('Laboratory beta'),
			'zabbix_version' => $zabbixVersion,
			'zabbix_supported' => ZabbixVersion::isSupported(),
			'can_compare' => $canAdminister,
			'can_prepare_updates' => $this->getUserType() === USER_TYPE_SUPER_ADMIN,
			'can_install' => $this->getUserType() === USER_TYPE_SUPER_ADMIN,
			'can_manage_policy' => $this->getUserType() === USER_TYPE_SUPER_ADMIN,
			'show_diagnostics' => $canAdminister,
			'templates' => [],
			'summary' => TemplateInventoryService::emptySummary(),
			'upstream_summary' => UpstreamMatcher::emptySummary(),
			'catalog_summary' => UpstreamCatalogService::emptySummary(),
			'version_summary' => TemplateVersionComparator::emptySummary(),
			'policy_summary' => ['never_update' => 0],
			'paging' => null,
			'filter' => [
				'name' => $filterName,
				'status' => $filterStatus
			],
			'filter_profile' => 'web.ztum.templates.filter',
			'filter_active_tab' => CProfile::get('web.ztum.templates.filter.active', 1),
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
			'upstream_warning' => null,
			'policy_error' => null
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
				'Unable to load template inventory. Check frontend logs and user permissions.'
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
					'Upstream could not be refreshed. A previously cached index is being used.'
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
				'Unable to load the official upstream catalog. Local inventory remains available.'
			);
			if ($data['show_diagnostics']) {
				$data['upstream_diagnostics']['detail'] = self::diagnosticMessage($exception);
			}
		}

		$versionComparison = TemplateVersionComparator::attach($data['templates']);
		$data['templates'] = $versionComparison['templates'];
		$data['version_summary'] = $versionComparison['summary'];

		try {
			$data['templates'] = (new TemplateUpdatePolicyRepository())->annotate($data['templates']);
			$neverUpdate = 0;
			$actionableUpdates = 0;

			foreach ($data['templates'] as $template) {
				$isInstalled = (string) ($template['installation_status'] ?? 'installed') === 'installed';

				if ($isInstalled && !empty($template['never_update'])) {
					$neverUpdate++;
				}
				elseif ($isInstalled && ($template['version_status'] ?? null) === 'update_available') {
					$actionableUpdates++;
				}
			}

			$data['policy_summary']['never_update'] = $neverUpdate;
			$data['version_summary']['update_available'] = $actionableUpdates;
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Update-policy inventory lookup failed: %s',
				$exception->getMessage()
			));
			$data['policy_error'] = _(
				'Unable to read the template update policy. Update execution is disabled until the policy store is available.'
			);
			$data['can_prepare_updates'] = false;
			$data['can_manage_policy'] = false;

			foreach ($data['templates'] as &$template) {
				$template['update_policy'] = 'unavailable';
				$template['never_update'] = false;
			}
			unset($template);
		}

		if ($filterName !== '') {
			$data['templates'] = array_values(array_filter(
				$data['templates'],
				static function (array $template) use ($filterName): bool {
					$name = trim((string) ($template['name'] ?? ''));
					$technicalName = trim((string) ($template['technical_name'] ?? ''));
					$visibleName = $name;

					if ($technicalName !== '' && $technicalName !== $name) {
						$visibleName .= ' ('.$technicalName.')';
					}

					return function_exists('mb_stripos')
						? mb_stripos($visibleName, $filterName) !== false
						: stripos($visibleName, $filterName) !== false;
				}
			));
		}

		if ($filterStatus === 'never_update') {
			$data['templates'] = array_values(array_filter(
				$data['templates'],
				static fn(array $template): bool =>
					(string) ($template['installation_status'] ?? 'installed') === 'installed'
					&& !empty($template['never_update'])
			));
		}
		elseif ($filterStatus !== 'all') {
			$data['templates'] = array_values(array_filter(
				$data['templates'],
				static fn(array $template): bool =>
					(string) ($template['version_status'] ?? 'not_applicable') === $filterStatus
					&& ($filterStatus !== 'update_available' || empty($template['never_update']))
			));
		}

		order_result($data['templates'], 'name', ZBX_SORT_UP);
		$data['filtered_count'] = count($data['templates']);

		$listUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');
		$pageNum = ($this->hasInput('filter_set') || $this->hasInput('filter_rst'))
			? 1
			: $this->getInput('page', 1);
		CPagerHelper::savePage('ztum.template.catalog', $pageNum);
		$data['paging'] = CPagerHelper::paginate(
			$pageNum,
			$data['templates'],
			ZBX_SORT_UP,
			$listUrl
		);

		$this->setResponse(new CControllerResponseData($data));
	}

	private static function diagnosticMessage(Throwable $exception): string {
		$message = trim($exception->getMessage());
		$message = preg_replace('/[\r\n\t]+/', ' ', $message) ?? '';
		return strlen($message) > 600 ? substr($message, 0, 600).'…' : $message;
	}
}
