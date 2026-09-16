<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateVersionComparator;
use Modules\ZabbixTemplateUpdateManager\Service\UpstreamMatcher;
use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';
require_once dirname(__DIR__).'/src/Service/TemplateVersionComparator.php';
require_once dirname(__DIR__).'/src/Service/UpstreamMatcher.php';
require_once dirname(__DIR__).'/src/Support/ZabbixVersion.php';

/**
 * Rebuilds inventory/upstream/version state for a bounded set of explicitly
 * selected templates. This action is review-only and never imports config.
 */
class TemplateSelectionReview extends CController {

	private const MAX_SELECTED_TEMPLATES = 25;

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'templateids' => 'required|array_id'
		]);

		if ($ret) {
			$count = count(array_unique(array_map('strval', $this->getInput('templateids', []))));
			$ret = $count >= 1 && $count <= self::MAX_SELECTED_TEMPLATES;
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return in_array($this->getUserType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true);
	}

	protected function doAction(): void {
		$selectedIds = array_values(array_unique(array_map(
			'strval',
			$this->getInput('templateids', [])
		)));

		$data = [
			'title' => _('Selected template updates'),
			'zabbix_version' => ZabbixVersion::current(),
			'selected_count' => count($selectedIds),
			'templates' => [],
			'can_prepare' => $this->getUserType() === USER_TYPE_SUPER_ADMIN,
			'error' => null
		];

		try {
			$inventory = (new TemplateInventoryService(new TemplateRepository()))->getInventory();
			$selectedLookup = array_fill_keys($selectedIds, true);
			$templates = array_values(array_filter(
				$inventory['templates'],
				static fn(array $template): bool => isset($selectedLookup[(string) $template['templateid']])
			));

			if (count($templates) !== count($selectedIds)) {
				throw new \RuntimeException('One or more selected templates are no longer visible to the current user.');
			}

			$index = (new UpstreamIndexRepository())->load($data['zabbix_version']);
			$matched = UpstreamMatcher::attach($templates, $index);
			$versionComparison = TemplateVersionComparator::attach($matched['templates']);
			$data['templates'] = $versionComparison['templates'];
		}
		catch (Throwable $exception) {
			error_log(sprintf(
				'[Zabbix Template Update Manager] Selected-template review failed: %s',
				$exception->getMessage()
			));
			$data['error'] = _(
				'Unable to rebuild authoritative state for the selected templates. No configuration change was attempted.'
			);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
