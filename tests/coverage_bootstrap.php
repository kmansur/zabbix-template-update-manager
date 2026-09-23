<?php

$coverageDir = trim((string) getenv('ZTUM_COVERAGE_DIR'));
if ($coverageDir === '' || !function_exists('xdebug_start_code_coverage')) {
	return;
}

@mkdir($coverageDir, 0700, true);

if (function_exists('xdebug_set_filter')
		&& defined('XDEBUG_FILTER_CODE_COVERAGE')
		&& defined('XDEBUG_PATH_INCLUDE')) {
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [dirname(__DIR__)]);
}

$flags = 0;
if (defined('XDEBUG_CC_UNUSED')) {
	$flags |= XDEBUG_CC_UNUSED;
}
if (defined('XDEBUG_CC_DEAD_CODE')) {
	$flags |= XDEBUG_CC_DEAD_CODE;
}
xdebug_start_code_coverage($flags);

register_shutdown_function(static function () use ($coverageDir): void {
	$data = xdebug_get_code_coverage();
	if (!is_array($data)) {
		return;
	}
	$name = basename((string) ($_SERVER['argv'][0] ?? 'php'));
	$file = $coverageDir.DIRECTORY_SEPARATOR
		.preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name)
		.'-'.getmypid().'.json';
	@file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
});
