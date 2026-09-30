<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use JsonException;
use RuntimeException;

final class UpstreamSignatureVerifier {

	public function isConfigured(): bool {
		return $this->publicKey() !== null;
	}

	public function isRequired(): bool {
		$value = strtolower(trim((string) getenv('ZTUM_REQUIRE_SIGNED_INDEX')));
		return in_array($value, ['1', 'true', 'yes', 'on'], true);
	}

	public function keyId(): ?string {
		$key = $this->publicKey();
		return $key === null ? null : 'ed25519:'.substr(hash('sha256', $key), 0, 24);
	}

	public function verify(string $content, string $signatureJson): array {
		if (!function_exists('sodium_crypto_sign_verify_detached')) {
			throw new RuntimeException('Ed25519 verification requires the PHP Sodium extension.');
		}

		$key = $this->publicKey();
		if ($key === null) {
			throw new RuntimeException('No trusted upstream Ed25519 public key is configured.');
		}

		try {
			$signature = json_decode($signatureJson, true, 32, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception) {
			throw new RuntimeException('The upstream signature document is invalid JSON.', 0, $exception);
		}

		$expectedKeyId = $this->keyId();
		$declaredSha = strtolower(trim((string) ($signature['content_sha256'] ?? '')));
		$encodedSignature = trim((string) ($signature['signature_b64'] ?? ''));

		if (!is_array($signature)
				|| ($signature['schema_version'] ?? null) !== 1
				|| ($signature['algorithm'] ?? null) !== 'ed25519'
				|| ($signature['key_id'] ?? null) !== $expectedKeyId
				|| preg_match('/^[a-f0-9]{64}$/', $declaredSha) !== 1
				|| !hash_equals($declaredSha, hash('sha256', $content))) {
			throw new RuntimeException('The upstream signature metadata does not match the trusted content/key.');
		}

		$detached = base64_decode($encodedSignature, true);
		if (!is_string($detached) || strlen($detached) !== SODIUM_CRYPTO_SIGN_BYTES) {
			throw new RuntimeException('The upstream Ed25519 signature encoding is invalid.');
		}

		if (!sodium_crypto_sign_verify_detached($detached, $content, $key)) {
			throw new RuntimeException('The upstream Ed25519 signature verification failed.');
		}

		return [
			'verified' => true,
			'algorithm' => 'ed25519',
			'key_id' => $expectedKeyId,
			'content_sha256' => $declaredSha
		];
	}

	private function publicKey(): ?string {
		$encoded = trim((string) getenv('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64'));
		if ($encoded === '') {
			return null;
		}
		$key = base64_decode($encoded, true);
		if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
			throw new RuntimeException('ZTUM_TRUSTED_INDEX_PUBLIC_KEY_B64 is not a valid Ed25519 public key.');
		}
		return $key;
	}
}
