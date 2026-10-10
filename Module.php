<?php

namespace Modules\ZabbixTemplateUpdateManager;

use APP;
use CMenuItem;
use CWebUser;
use Zabbix\Core\CModule;

class Module extends CModule {

	public function init(): void {
		if (CWebUser::getType() !== USER_TYPE_SUPER_ADMIN) {
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
