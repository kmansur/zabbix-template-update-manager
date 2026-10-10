<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$backUrl = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');
$page = (new CHtmlPage())
    ->setTitle($data['title'])
    ->setControls(
        (new CTag('nav', true,
            (new CList())->addItem(new CLink(_('Back to template updates'), $backUrl))
        ))->setAttribute('aria-label', _('Content controls'))
    );

if ($data['error'] !== null) {
    $page->addItem(FrontendUi::message((string) $data['error'], FrontendUi::DANGER))->show();
    return;
}

$page->addItem(FrontendUi::description(_(
    'Only installed templates with at least one integrity-verified rollback backup are shown. '
    .'Open backup history to select an artifact; rollback requires a separate review and explicit confirmation.'
)));
if ((int) $data['unavailable'] > 0) {
    $page->addItem(FrontendUi::message(_(
        'Some backup directories could not be inspected safely. The list may be incomplete; check frontend logs and directory permissions.'
    ), FrontendUi::WARNING));
}
$table = (new CTableInfo())
    ->setHeader([
        _('Template'), _('Technical name'), _('Installed version'),
        _('Valid backups'), _('Invalid backups'), _('More than 50'), _('Action')
    ])
    ->setNoDataMessage(_('No valid rollback backups found.'), _('No installed template has a verified backup in the accessible repository.'));

foreach ($data['templates'] as $template) {
    $historyUrl = (new CUrl('zabbix.php'))
        ->setArgument('action', 'ztum.template.backups')
        ->setArgument('templateid', $template['templateid']);
    $table->addRow([
        $template['name'],
        $template['technical_name'] !== '' ? $template['technical_name'] : '—',
        $template['vendor_version'] !== '' ? $template['vendor_version'] : '—',
        $template['valid'],
        $template['invalid'],
        $template['truncated'] ? _('Yes') : _('No'),
        new CLink(_('View backups / Review rollback'), $historyUrl)
    ]);
}
$page->addItem(FrontendUi::section(_('Templates with valid backups')))
    ->addItem($table)
    ->show();
