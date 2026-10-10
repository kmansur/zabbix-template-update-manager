<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateUpdatePreparationService;
use Throwable;

require_once dirname(__DIR__).'/src/Service/TemplateUpdatePreparationService.php';

/**
 * POST-only preparation: may create local rollback evidence, but never imports.
 * Uses the same native confirmation view as the existing preflight action.
 */
class TemplateUpdatePrepare extends CController {
    protected function checkInput(): bool {
        $ok = $this->validateInput(['templateid' => 'required|db hosts.hostid']);
        if (!$ok) {
            $this->setResponse(new CControllerResponseFatal());
        }
        return $ok;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
    }

    protected function doAction(): void {
        $id = (string) $this->getInput('templateid');
        $data = [
            'title' => _('Template update confirmation'),
            'templateid' => $id,
            'preflight' => null,
            'preflight_error' => null,
            'can_update' => true
        ];
        try {
            $data['preflight'] = (new TemplateUpdatePreparationService())->prepare($id);
            if (($data['preflight']['status'] ?? '') !== 'passed') {
                $data['preflight_error'] = _('Automatic preparation did not pass the final safety checks. No configuration import was attempted.');
            }
        }
        catch (Throwable $exception) {
            error_log(sprintf(
                '[Zabbix Template Update Manager] Individual preparation failed for template %s: %s',
                $id, $exception->getMessage()
            ));
            $data['preflight_error'] = _(
                'Unable to prepare and verify the rollback backup or fresh preflight. No Zabbix configuration import was attempted.'
            );
        }
        $this->setResponse(new CControllerResponseData($data));
    }
}
