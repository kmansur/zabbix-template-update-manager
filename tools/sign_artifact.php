#!/usr/bin/env php
<?php

if ($argc !== 3) {
	fwrite(STDERR, "Usage: tools/sign_artifact.php INPUT OUTPUT.sig.json\n");
	exit(2);
}
if (!function_exists('sodium_crypto_sign_detached')) {
	fwrite(STDERR, "PHP Sodium extension is required.\n");
	exit(3);
}

$secretEncoded = trim((string) getenv('ZTUM_INDEX_SIGNING_SECRET_KEY_B64'));
$secret = base64_decode($secretEncoded, true);
if (!is_string($secret) || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
	fwrite(STDERR, "ZTUM_INDEX_SIGNING_SECRET_KEY_B64 must contain a base64 Ed25519 secret key.\n");
	exit(4);
}

$content = file_get_contents($argv[1]);
if (!is_string($content) || $content === '') {
	fwrite(STDERR, "Unable to read signing input.\n");
	exit(5);
}

$public = sodium_crypto_sign_publickey_from_secretkey($secret);
$keyId = 'ed25519:'.substr(hash('sha256', $public), 0, 24);
$document = [
	'schema_version' => 1,
	'algorithm' => 'ed25519',
	'key_id' => $keyId,
	'content_sha256' => hash('sha256', $content),
	'signature_b64' => base64_encode(sodium_crypto_sign_detached($content, $secret))
];

file_put_contents(
	$argv[2],
	json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
	LOCK_EX
);
fwrite(STDOUT, $keyId." ".$document['content_sha256']."\n");
