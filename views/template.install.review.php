<?php

use Modules\ZabbixTemplateUpdateManager\Support\FrontendUi;

require_once dirname(__DIR__).'/src/Support/FrontendUi.php';

$statusLabels = [
	'passed' => _('Passed — eligible for controlled installation'),
	'blocked_version' => _('Blocked — unsupported Zabbix version'),
	'blocked_candidate' => _('Blocked — official candidate unavailable'),
	'blocked_already_installed' => _('Blocked — template is already installed'),
	'blocked_collision' => _('Blocked — local technical-name collision'),
	'blocked_dependencies' => _('Blocked — required template dependencies are missing'),
	'blocked_isolation' => _('Blocked — template cannot be isolated safely'),
	'blocked_references' => _('Blocked — structural references are unresolved'),
	'blocked_preview' => _('Blocked — import preview is not creation-only')
];

$reasonLabels = [
	'unsupported_zabbix_version' => _('Unsupported or undetected Zabbix version'),
	'official_template_not_found' => _('The selected UUID is not present in the validated official catalog'),
	'upstream_version_missing' => _('The official candidate has no vendor version'),
	'template_already_installed' => _('A local template with this official UUID already exists'),
	'technical_name_collision' => _('A different local template already uses the same technical name'),
	'missing_template_dependencies' => _('One or more linked templates must be installed first'),
	'cross_template_graph_dependency' => _('The template has a top-level graph that references another template'),
	'cross_template_trigger_dependency' => _('The template has a top-level trigger that references another template'),
	'cross_template_dashboard_dependency' => _('The template dashboard references a graph from another template'),
	'missing_dashboard_graph_dependency' => _('The template dashboard references a graph that cannot be included safely'),
	'unresolved_internal_references' => _('One or more statically verifiable template references cannot be resolved safely'),
	'install_would_modify_existing_configuration' => _('The import preview would update or remove existing configuration'),
	'install_preview_contains_no_creations' => _('The import preview did not contain any creation')
];

$page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(
					_('Back to template catalog'),
					(new CUrl('zabbix.php'))->setArgument('action', 'ztum.templates')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['preflight_error'] !== null) {
	$page->addItem(new CTag('p', true, $data['preflight_error']))->show();
	return;
}

$preflight = is_array($data['preflight']) ? $data['preflight'] : [];
$status = (string) ($preflight['status'] ?? 'blocked_candidate');
$reason = (string) ($preflight['reason'] ?? '');
$candidate = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];
$dependencies = is_array($preflight['dependencies'] ?? null) ? $preflight['dependencies'] : [];
$summary = is_array($preflight['comparison_summary'] ?? null) ? $preflight['comparison_summary'] : [];
$referenceAudit = is_array($preflight['reference_audit'] ?? null) ? $preflight['reference_audit'] : [];
$evidence = (string) ($preflight['evidence_sha256'] ?? '');

$page
	->addItem(new CTag('h4', true, _('Installation safety check')))
	->addItem(
		(new CTableInfo())
			->setHeader([_('State'), _('Reason'), _('Configuration write enabled')])
			->addRow([
				FrontendUi::status(
					$statusLabels[$status] ?? $status,
					$status === 'passed' ? FrontendUi::SUCCESS : FrontendUi::DANGER
				),
				$reason !== '' ? ($reasonLabels[$reason] ?? $reason) : '—',
				FrontendUi::yesNo(!empty($preflight['write_enabled']))
			])
	);

if ($candidate !== []) {
	$page
		->addItem(new CTag('h4', true, _('Official candidate')))
		->addItem(
			(new CTableInfo())
				->setHeader([
					_('Template'),
					_('Version'),
					_('UUID'),
					_('Upstream commit'),
					_('Source path'),
					_('Raw source SHA-256')
				])
				->addRow([
					(string) ($candidate['name'] ?? '—'),
					(string) ($candidate['vendor_version'] ?? '—'),
					(string) ($candidate['uuid'] ?? $data['uuid']),
					isset($candidate['commit']) ? substr((string) $candidate['commit'], 0, 16) : '—',
					(string) ($candidate['path'] ?? '—'),
					isset($candidate['source_sha256'])
						? substr((string) $candidate['source_sha256'], 0, 20)
						: '—'
				])
		);
}

$required = is_array($dependencies['required'] ?? null) ? $dependencies['required'] : [];
$missing = is_array($dependencies['missing'] ?? null) ? $dependencies['missing'] : [];
$page
	->addItem(new CTag('h4', true, _('Template dependencies')))
	->addItem(
		(new CTableInfo())
			->setHeader([_('Required linked templates'), _('Missing')])
			->addRow([
				$required !== [] ? implode(', ', array_map('strval', $required)) : _('None'),
				$missing !== [] ? implode(', ', array_map('strval', $missing)) : _('None')
			])
	);

if ($referenceAudit !== []) {
	$counts = is_array($referenceAudit['counts'] ?? null) ? $referenceAudit['counts'] : [];
	$issues = is_array($referenceAudit['issues'] ?? null) ? $referenceAudit['issues'] : [];
	$issueText = [];

	foreach (array_slice($issues, 0, 10) as $issue) {
		if (!is_array($issue)) {
			continue;
		}

		$code = trim((string) ($issue['code'] ?? ''));
		$reference = trim((string) ($issue['reference'] ?? ''));
		if ($code !== '') {
			$issueText[] = $code.($reference !== '' ? ': '.$reference : '');
		}
	}

	$page
		->addItem(new CTag('h4', true, _('Structural reference audit')))
		->addItem(
			(new CTableInfo())
				->setHeader([
					_('State'),
					_('Master items'),
					_('Value maps'),
					_('Dashboard items'),
					_('Trigger hosts'),
					_('Graph hosts'),
					_('Issues')
				])
				->addRow([
					!empty($referenceAudit['safe']) ? _('Passed') : _('Blocked'),
					(int) ($counts['master_item_references'] ?? 0),
					(int) ($counts['value_map_references'] ?? 0),
					(int) ($counts['dashboard_item_references'] ?? 0),
					(int) ($counts['trigger_host_references'] ?? 0),
					(int) ($counts['graph_item_host_references'] ?? 0),
					$issueText !== [] ? implode(' | ', $issueText) : _('None')
				])
		);
}

if ($summary !== []) {
	$page
		->addItem(new CTag('h4', true, _('Installation import preview')))
		->addItem(
			(new CTableInfo())
				->setHeader([_('Added'), _('Updated'), _('Removed'), _('Total changes')])
				->addRow([
					(int) ($summary['added'] ?? 0),
					(int) ($summary['updated'] ?? 0),
					(int) ($summary['removed'] ?? 0),
					(int) ($summary['total'] ?? 0)
				])
		);
}

if ($evidence !== '') {
	$page
		->addItem(new CTag('h4', true, _('Preflight evidence fingerprint')))
		->addItem(new CTag('p', true, $evidence));
}

if ($status === 'passed' && !empty($data['can_install']) && $evidence !== '') {
	$installAction = (new CUrl('zabbix.php'))
		->setArgument('action', 'ztum.template.install')
		->getUrl();

	$form = (new CForm('post'))
		->setId('ztum-template-install-form')
		->setAction($installAction)
		->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
		->addItem([
			(new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('ztum.template.install')))->removeId(),
			(new CVar('uuid', $data['uuid']))->removeId(),
			(new CVar('evidence_sha256', $evidence))->removeId()
		])
		->addItem(
			(new CFormList())->addRow(
				_('Confirmation'),
				(new CCheckBox('confirm', '1'))->setLabel(_(
					'I reviewed the official candidate, dependencies and creation-only import preview and want to install this template.'
				))
			)
		)
		->addItem(makeFormFooter(new CSubmitButton(_('Install official template'))));

	$page
		->addItem(new CTag('h4', true, _('Controlled installation')))
		->addItem(new CTag('p', true, _(
			'This operation creates Zabbix configuration and is restricted to super administrators. Because the template is not currently installed, there is no prior local rollback artifact. If post-install validation fails, ZTUM will not automatically uninstall the imported configuration.'
		)))
		->addItem($form);
}
elseif ($status === 'passed') {
	$page->addItem(new CTag('p', true, _(
		'A Zabbix super administrator is required to install the official template.'
	)));
}
else {
	$page->addItem(new CTag('p', true, _(
		'Installation remains blocked. Resolve the reported prerequisite and reopen this review.'
	)));
}

$page->show();
