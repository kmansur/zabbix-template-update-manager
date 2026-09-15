<?php

use Modules\ZabbixTemplateUpdateManager\Support\ZabbixVersion;

require_once __DIR__.'/../../src/Support/ZabbixVersion.php';

function assertSameValue($expected, $actual, string $message): void {
	if ($expected !== $actual) {
		fwrite(
			STDERR,
			sprintf(
				"FAIL: %s\nExpected: %s\nActual:   %s\n",
				$message,
				var_export($expected, true),
				var_export($actual, true)
			)
		);
		exit(1);
	}
}

assertSameValue(7, ZabbixVersion::major('7.0.30'), 'Detect Zabbix 7 major version.');
assertSameValue(7, ZabbixVersion::major('7.4.5'), 'Detect another Zabbix 7.x version.');
assertSameValue(8, ZabbixVersion::major('8.0.0rc1'), 'Detect Zabbix 8 release candidate major version.');
assertSameValue(null, ZabbixVersion::major('unknown'), 'Reject unknown versions.');
assertSameValue('7.0', ZabbixVersion::line('7.0.30'), 'Detect the 7.0 release line.');
assertSameValue('7.4', ZabbixVersion::line('7.4.5'), 'Detect the 7.4 release line.');
assertSameValue('8.0', ZabbixVersion::line('8.0.0beta2'), 'Detect the 8.0 prerelease line.');
assertSameValue(null, ZabbixVersion::line('unknown'), 'Reject an unknown release line.');
assertSameValue(true, ZabbixVersion::isSupported('7.0.30'), 'Zabbix 7 must be supported.');
assertSameValue(true, ZabbixVersion::isSupported('8.0.0rc1'), 'Zabbix 8 must be supported.');
assertSameValue(false, ZabbixVersion::isSupported('6.0.40'), 'Zabbix 6 must not be supported.');
assertSameValue(false, ZabbixVersion::isSupported('9.0.0'), 'Future unsupported majors must fail closed.');
assertSameValue([7, 8], ZabbixVersion::supportedMajors(), 'Supported major list must remain explicit.');

echo "ZabbixVersion tests passed.\n";
