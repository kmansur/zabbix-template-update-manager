<?php

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root.'/src/Service/TemplateConfigurationImportService.php');
$view = (string) file_get_contents($root.'/views/ztum.template.list.php');
$diagnostics = (string) file_get_contents($root.'/src/Support/RuntimeEnvironmentDiagnostics.php');

function assertImportCompatibility(bool $passed, string $reason): void {
	if (!$passed) {
		fwrite(STDERR, $reason."\n");
		exit(1);
	}
}

assertImportCompatibility(
	strpos($service, 'if ($majorVersion >= 8)') !== false
		&& strpos($service, '$this->importViaFrontendWrapper($params);') !== false
		&& strpos($service, 'API::Configuration()->import($params)') !== false,
	'Zabbix 8 must use the supported frontend wrapper rather than the legacy auth-array call.'
);
assertImportCompatibility(
	strpos($service, 'if ($result === true)') !== false
		&& strpos($service, 'throw new ConfigurationImportException($message)') !== false,
	'Frontend import must fail closed unless the API reports strict boolean success.'
);
assertImportCompatibility(
	strpos($view, 'USER_TYPE_SUPER_ADMIN') !== false
		&& strpos($view, 'RuntimeEnvironmentDiagnostics::check()') !== false
		&& strpos($view, 'ztum-runtime-setup.sh --check') !== false
		&& strpos($view, 'ztum-runtime-setup.sh --apply') !== false,
	'Runtime diagnostics must be restricted to administrators and offer remediation commands.'
);
assertImportCompatibility(
	strpos($diagnostics, 'is_link($path)') !== false
		&& strpos($diagnostics, '0700') !== false
		&& strpos($diagnostics, 'is_writable($path)') !== false
		&& strpos($diagnostics, 'exec(') === false,
	'Runtime diagnostics must check private storage without applying privileged changes.'
);

echo "ZTUM runtime and Zabbix 8 import compatibility contracts passed.\n";
