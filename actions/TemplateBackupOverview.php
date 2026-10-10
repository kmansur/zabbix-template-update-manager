<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateBackupRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\TemplateRepository;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateInventoryService;
use Throwable;

require_once dirname(__DIR__).'/src/Repository/TemplateBackupRepository.php';
require_once dirname(__DIR__).'/src/Repository/TemplateRepository.php';
require_once dirname(__DIR__).'/src/Service/TemplateInventoryService.php';

/**
 * Read-only summary of installed templates with valid rollback evidence.
 * Never exposes backup contents or performs a configuration import.
 */
class TemplateBackupOverview extends CController {
    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $data = [
            'title' => _('Templates with rollback backups'),
            'templates' => [],
            'scanned' => 0,
            'unavailable' => 0,
            'error' => null
        ];
        try {
            $inventory = (new TemplateInventoryService(new TemplateRepository()))->getInventory();
            $repository = new TemplateBackupRepository();
            foreach ($inventory['templates'] as $template) {
                $id = (string) ($template['templateid'] ?? '');
                if (!ctype_digit($id) || (int) $id <= 0) {
                    continue;
                }
                $data['scanned']++;
                $inspection = $repository->inspectForTemplate($id, 50);
                if (($inspection['status'] ?? '') === 'repository_unavailable') {
                    $data['unavailable']++;
                    continue;
                }
                $valid = (int) ($inspection['valid'] ?? 0);
                if ($valid < 1) {
                    continue;
                }
                $data['templates'][] = [
                    'templateid' => $id,
                    'name' => (string) ($template['name'] ?? $id),
                    'technical_name' => (string) ($template['technical_name'] ?? ''),
                    'vendor_version' => (string) ($template['vendor_version'] ?? ''),
                    'valid' => $valid,
                    'invalid' => (int) ($inspection['invalid'] ?? 0),
                    'truncated' => !empty($inspection['truncated'])
                ];
            }
            usort($data['templates'], static fn(array $a, array $b): int =>
                strcasecmp($a['name'], $b['name']));
        }
        catch (Throwable $exception) {
            error_log('[Zabbix Template Update Manager] Backup overview failed: '.$exception->getMessage());
            $data['error'] = _('Unable to load backup overview. Check frontend logs and backup repository access.');
        }
        $this->setResponse(new CControllerResponseData($data));
    }
}
