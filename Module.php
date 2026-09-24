<?php

namespace Modules\ZabbixTemplateUpdateManager;

use APP;
use CMenuItem;
use CWebUser;
use Zabbix\Core\CModule;

class Module extends CModule {

	public function init(): void {
		if (!in_array(CWebUser::getType(), [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN], true)) {
			return;
		}

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
