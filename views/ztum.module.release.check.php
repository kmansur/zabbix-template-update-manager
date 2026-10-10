<?php
use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;
require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$back = (new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates');
$page = (new CHtmlPage())->setTitle($data['title']);
$page->addItem(new CLink(_('Back to template updates'), $back));
$page->addItem(FrontendUi::description(_('Installed ZTUM version: ').$data['installed']));
$page->addItem(FrontendUi::description(_('Manual check only. No update is downloaded or installed.')));
if ($data['error'] !== null) {
    $page->addItem(FrontendUi::message($data['error'], FrontendUi::WARNING));
}
elseif ($data['result']['latest'] === null) {
    $page->addItem(FrontendUi::message(_('No published GitHub releases could be verified.'), FrontendUi::WARNING));
}
else {
    $r = $data['result'];
    $page->addItem(FrontendUi::message(
        $r['update_available']
            ? sprintf(_('ZTUM update available: %s'), $r['latest'])
            : sprintf(_('No newer published release found. Most recent: %s'), $r['latest']),
        $r['update_available'] ? FrontendUi::INFO : FrontendUi::SUCCESS
    ));
    $page->addItem(new CLink(_('View release notes on GitHub'), $r['url']));
    if ($r['prerelease']) {
        $page->addItem(FrontendUi::description(_('The selected release is a prerelease (beta or RC).')));
    }
}
$page->show();
