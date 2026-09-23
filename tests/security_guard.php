<?php

$root = dirname(__DIR__);
$failures = [];
$runtimeFiles = [$root.'/Module.php'];

foreach (['src', 'actions', 'views'] as $directory) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
			$runtimeFiles[] = $file->getPathname();
		}
	}
}

$rules = [
	'/\b(?:eval|shell_exec|exec|system|passthru|popen|proc_open)\s*\(/i'
		=> 'Runtime shell/code-execution function is prohibited.',
	'/\bunserialize\s*\(/i'
		=> 'PHP unserialize() is prohibited in runtime code.',
	'/CURLOPT_SSL_VERIFYPEER\s*=>\s*false/i'
		=> 'TLS peer verification must not be disabled.',
	'/CURLOPT_SSL_VERIFYHOST\s*=>\s*(?:0|false)/i'
		=> 'TLS hostname verification must not be disabled.',
	'/[\'"]verify_peer[\'"]\s*=>\s*false/i'
		=> 'Stream TLS peer verification must not be disabled.',
	'/[\'"]verify_peer_name[\'"]\s*=>\s*false/i'
		=> 'Stream TLS hostname verification must not be disabled.',
	'/\b(?:chmod|mkdir)\s*\([^\n;]*\b0?777\b/i'
		=> 'World-writable runtime permissions are prohibited.',
	'/\bDBexecute\s*\(/i'
		=> 'Direct database writes are prohibited.',
	'/\bDB::(?:insert|update|delete)\s*\(/i'
		=> 'Direct database writes are prohibited.',
	'/API::Template\(\)->(?:create|update|delete)\s*\(/i'
		=> 'Direct template write APIs are prohibited.'
];

foreach (array_unique($runtimeFiles) as $file) {
	$content = (string) file_get_contents($file);
	foreach ($rules as $pattern => $message) {
		if (preg_match($pattern, $content) === 1) {
			$failures[] = $message.' File: '.substr($file, strlen($root) + 1);
		}
	}
	if ($file !== $root.'/src/Service/TemplateConfigurationImportService.php'
			&& strpos($content, 'API::Configuration()->import(') !== false) {
		$failures[] = 'configuration.import is allowed only in TemplateConfigurationImportService.php. File: '
			.substr($file, strlen($root) + 1);
	}
}

$secretPatterns = [
	'/-----BEGIN (?:RSA |OPENSSH |EC |DSA )?PRIVATE KEY-----/' => 'Private key material detected.',
	'/\bghp_[A-Za-z0-9]{30,}\b/' => 'GitHub classic token pattern detected.',
	'/\bgithub_pat_[A-Za-z0-9_]{40,}\b/' => 'GitHub fine-grained token pattern detected.',
	'/\bAKIA[0-9A-Z]{16}\b/' => 'AWS access key pattern detected.'
];

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
	if (!$file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)) {
		continue;
	}
	$size = $file->getSize();
	if ($size <= 0 || $size > 5 * 1024 * 1024) {
		continue;
	}
	$content = @file_get_contents($file->getPathname());
	if (!is_string($content) || strpos($content, "\0") !== false) {
		continue;
	}
	foreach ($secretPatterns as $pattern => $message) {
		if (preg_match($pattern, $content) === 1) {
			$failures[] = $message.' File: '.substr($file->getPathname(), strlen($root) + 1);
		}
	}
}

if ($failures !== []) {
	foreach (array_values(array_unique($failures)) as $failure) {
		fwrite(STDERR, $failure."\n");
	}
	exit(1);
}

echo "Security guard passed.\n";
