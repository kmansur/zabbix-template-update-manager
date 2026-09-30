<?php

use Modules\ZabbixTemplateUpdateManager\Repository\OfflineBundleRepository;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamIndexRepository;

require_once dirname(__DIR__, 2).'/src/Repository/UpstreamIndexRepository.php';
require_once dirname(__DIR__, 2).'/src/Repository/OfflineBundleRepository.php';

function assertSignedOffline(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

if (!function_exists('sodium_crypto_sign_keypair')) {
	fwrite(STDERR, "Sodium extension is required for signed offline bundle tests.\n");
	exit(1);
}

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$public = sodium_crypto_sign_publickey($keypair);
putenv('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64='.base64_encode($public));
putenv('ZTUM_REQUIRE_SIGNED_INDEX=1');

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ztum-signed-offline-'.bin2hex(random_bytes(6));
@mkdir($root.DIRECTORY_SEPARATOR.'indexes', 0700, true);

$commit = str_repeat('a', 40);
$uuid = str_repeat('b', 32);
$index = json_encode([
	'schema_version' => 1,
	'source' => ['line' => '7.0', 'ref' => 'release/7.0', 'commit' => $commit],
	'templates' => [
		$uuid => [
			'uuid' => $uuid,
			'paths' => ['templates/test/template_test.yaml'],
			'content_sha256s' => [str_repeat('c', 64)],
			'sources' => [[
				'path' => 'templates/test/template_test.yaml',
				'sha256' => str_repeat('d', 64)
			]]
		]
	]
], JSON_UNESCAPED_SLASHES)."\n";

$keyId = 'ed25519:'.substr(hash('sha256', $public), 0, 24);
$sign = static function (string $content) use ($secret, $keyId): string {
	return json_encode([
		'schema_version' => 1,
		'algorithm' => 'ed25519',
		'key_id' => $keyId,
		'content_sha256' => hash('sha256', $content),
		'signature_b64' => base64_encode(sodium_crypto_sign_detached($content, $secret))
	], JSON_UNESCAPED_SLASHES)."\n";
};

file_put_contents($root.'/indexes/7.0.json', $index);
file_put_contents($root.'/indexes/7.0.json.sig.json', $sign($index));

$manifest = json_encode([
	'schema_version' => 1,
	'generated_at' => gmdate('c'),
	'files' => [
		'indexes/7.0.json' => hash('sha256', $index),
		'indexes/7.0.json.sig.json' => hash('sha256', $sign($index))
	]
], JSON_UNESCAPED_SLASHES)."\n";
file_put_contents($root.'/manifest.json', $manifest);
file_put_contents($root.'/manifest.sig.json', $sign($manifest));

$bundle = new OfflineBundleRepository($root, true);
$repo = new UpstreamIndexRepository(sys_get_temp_dir().'/ztum-signed-cache-'.bin2hex(random_bytes(3)), $bundle);
$loaded = $repo->load('7.0.0');
assertSignedOffline(($loaded['runtime']['upstream_trust']['verified'] ?? false) === true,
	'Signed offline index must report verified trust.');

file_put_contents($root.'/manifest.json', $manifest." ");
try {
	(new OfflineBundleRepository($root, true))->readIndex('7.0');
	assertSignedOffline(false, 'Tampered signed manifest must fail.');
}
catch (RuntimeException $exception) {
	assertSignedOffline(str_contains($exception->getMessage(), 'signature'), 'Manifest tamper must fail at signature verification.');
}

file_put_contents($root.'/manifest.json', $manifest);
file_put_contents($root.'/indexes/7.0.json', $index." ");
try {
	(new UpstreamIndexRepository(sys_get_temp_dir().'/ztum-signed-cache-'.bin2hex(random_bytes(3)), new OfflineBundleRepository($root, true)))->load('7.0.0');
	assertSignedOffline(false, 'Tampered signed index must fail.');
}
catch (RuntimeException $exception) {
	assertSignedOffline(
		str_contains($exception->getMessage(), 'integrity verification failed')
			|| str_contains($exception->getMessage(), 'signature'),
		'Index tamper must fail closed before use.'
	);
}

putenv('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64');
putenv('ZTUM_REQUIRE_SIGNED_INDEX');

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
	$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
}
@rmdir($root);

echo "Signed offline bundle tamper tests passed.\n";
