<?php

(new CHtmlPage())
->setTitle($data['title'])
->addItem(
new CDiv([
new CTag('p', true, _('Template discovery and update management.')),
new CTag('p', true, _('Version: ').$data['version']),
new CTag('p', true, _('Status: ').$data['status'])
])
)
->show();