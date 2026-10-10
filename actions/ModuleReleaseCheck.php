<?php
namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixTemplateUpdateManager\Service\ModuleReleaseCheckService;
use Modules\ZabbixTemplateUpdateManager\Support\ProjectVersion;
use Throwable;

require_once dirname(__DIR__).'/src/Service/ModuleReleaseCheckService.php';
require_once dirname(__DIR__).'/src/Support/ProjectVersion.php';

class ModuleReleaseCheck extends CController {
    public function init(): void {
        $this->disableCsrfValidation(); // Read-only GET; no state changes.
    }
    protected function checkInput(): bool {
        return true;
    }
    protected function checkPermissions(): bool {
        return $this->getUserType() === USER_TYPE_SUPER_ADMIN;
    }
    protected function doAction(): void {
        $data = [
            'title' => _('Check for ZTUM updates'),
            'installed' => ProjectVersion::current(),
            'result' => null,
            'error' => null
        ];
        try {
            $data['result'] = (new ModuleReleaseCheckService())->check($data['installed']);
        }
        catch (Throwable $e) {
            error_log('[ZTUM] Release check failed: '.$e->getMessage());
            $data['error'] = _('Unable to check GitHub releases. The installed module was not changed.');
        }
        $this->setResponse(new CControllerResponseData($data));
    }
}
