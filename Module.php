<?php

namespace Modules\ZabbixTemplateUpdateManager;

use APP;
use CMenuItem;
use Zabbix\Core\CModule;

class Module extends CModule {

public function init(): void {
APP::Component()
->get('menu.main')
->findOrAdd(_('Data collection'))
->getSubmenu()
->insertAfter(
_('Templates'),
(new CMenuItem(_('Template updates')))
->setAction('ztum.templates')
);
}
}