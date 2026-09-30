#!/usr/bin/env php
<?php

if (!function_exists('sodium_crypto_sign_keypair')) {
	fwrite(STDERR, "PHP Sodium extension is required.\n");
	exit(2);
}

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$public = sodium_crypto_sign_publickey($keypair);
$keyId = 'ed25519:'.substr(hash('sha256', $public), 0, 24);

echo "key_id=".$keyId."\n";
echo "public_key_b64=".base64_encode($public)."\n";
echo "secret_key_b64=".base64_encode($secret)."\n";
echo "\nStore secret_key_b64 only in a protected GitHub Actions secret or offline signing system.\n";
