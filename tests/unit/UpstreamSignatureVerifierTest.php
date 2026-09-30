<?php

use Modules\ZabbixTemplateUpdateManager\Service\UpstreamSignatureVerifier;

require_once dirname(__DIR__, 2).'/src/Service/UpstreamSignatureVerifier.php';

function assertSignature(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

if (!function_exists('sodium_crypto_sign_keypair')) {
	fwrite(STDERR, "Sodium extension is required for signature tests.\n");
	exit(1);
}

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$public = sodium_crypto_sign_publickey($keypair);
putenv('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64='.base64_encode($public));

$content = "{\"schema_version\":1}\n";
$keyId = 'ed25519:'.substr(hash('sha256', $public), 0, 24);
$signature = json_encode([
	'schema_version' => 1,
	'algorithm' => 'ed25519',
	'key_id' => $keyId,
	'content_sha256' => hash('sha256', $content),
	'signature_b64' => base64_encode(sodium_crypto_sign_detached($content, $secret))
], JSON_THROW_ON_ERROR);

$verifier = new UpstreamSignatureVerifier();
$result = $verifier->verify($content, $signature);
assertSignature($result['verified'] === true, 'Valid Ed25519 signature must verify.');
assertSignature($result['key_id'] === $keyId, 'Verified signature must expose the trusted key ID.');

try {
	$verifier->verify($content."tamper", $signature);
	assertSignature(false, 'Tampered content must fail signature verification.');
}
catch (RuntimeException $exception) {
	assertSignature(str_contains($exception->getMessage(), 'does not match'), 'Tamper failure must be explicit.');
}

$other = sodium_crypto_sign_keypair();
putenv('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64='.base64_encode(sodium_crypto_sign_publickey($other)));
try {
	(new UpstreamSignatureVerifier())->verify($content, $signature);
	assertSignature(false, 'Wrong trusted key must fail.');
}
catch (RuntimeException $exception) {
	assertSignature(true, 'Wrong key failure is expected.');
}

putenv('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64');
echo "UpstreamSignatureVerifier tests passed.\n";
