<?php

namespace Modules\ZabbixTemplateUpdateManager\Actions;

use CController;
use CControllerResponseData;

class TemplateList extends CController {

public function init(): void {
$this->disableCsrfValidation();
}

protected function checkInput(): bool {
return true;
}

protected function checkPermissions(): bool {
return true;
}

protected function doAction(): void {
$data = [
'title' => _('Zabbix Template Update Manager'),
'version' => '0.1.0-dev',
'status' => _('Development version')
];

$this->setResponse(new CControllerResponseData($data));
}
}