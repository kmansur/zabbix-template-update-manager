<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use CImportReaderFactory;
use Modules\ZabbixTemplateUpdateManager\Repository\UpstreamTemplateSourceRepository;
use RuntimeException;

require_once dirname(__DIR__).'/Repository/UpstreamTemplateSourceRepository.php';
require_once __DIR__.'/UpstreamTemplateDocumentService.php';

/**
 * Re-fetches and validates the exact immutable upstream source selected by a
 * passing preflight, then isolates the single template import document.
 */
final class TemplateUpdateCandidateService {

	private $fetcher;
	private $parser;

	public function __construct(?callable $fetcher = null, ?callable $parser = null) {
		$this->fetcher = $fetcher ?? static fn(string $commit, string $path): array
			=> (new UpstreamTemplateSourceRepository())->fetchAtCommit($commit, $path);
		$this->parser = $parser ?? static function (string $source): array {
			$reader = CImportReaderFactory::getReader(CImportReaderFactory::YAML);
			return $reader->read($source);
		};
	}

	public function build(array $preflight): array {
		if (($preflight['status'] ?? null) !== 'passed') {
			throw new RuntimeException('A passing fresh preflight is required to build an update candidate.');
		}

		$candidate = is_array($preflight['candidate'] ?? null) ? $preflight['candidate'] : [];
		$commit = strtolower(trim((string) ($candidate['commit'] ?? '')));
		$path = trim((string) ($candidate['path'] ?? ''));
		$expectedSourceSha256 = strtolower(trim((string) ($candidate['source_sha256'] ?? '')));
		$expectedContentSha256 = strtolower(trim((string) ($candidate['content_sha256'] ?? '')));
		$uuid = self::normalizeUuid((string) ($candidate['uuid'] ?? ''));
		$name = trim((string) ($candidate['name'] ?? ''));
		$technicalName = trim((string) ($candidate['technical_name'] ?? ''));
		$vendorName = trim((string) ($candidate['vendor_name'] ?? ''));
		$vendorVersion = trim((string) ($candidate['vendor_version'] ?? ''));

		if (!preg_match('/^[a-f0-9]{40}$/', $commit)
				|| !self::isSafeTemplatePath($path)
				|| !preg_match('/^[a-f0-9]{64}$/', $expectedSourceSha256)
				|| !preg_match('/^[a-f0-9]{64}$/', $expectedContentSha256)
				|| !preg_match('/^[a-f0-9]{32}$/', $uuid)
				|| $name === ''
				|| $technicalName === ''
				|| $vendorName === ''
				|| $vendorVersion === '') {
			throw new RuntimeException('The preflight upstream candidate identity is incomplete or invalid.');
		}

		$fetched = ($this->fetcher)($commit, $path);
		if (!is_array($fetched)
				|| (string) ($fetched['commit'] ?? '') !== $commit
				|| (string) ($fetched['path'] ?? '') !== $path
				|| !is_string($fetched['content'] ?? null)
				|| $fetched['content'] === '') {
			throw new RuntimeException('The immutable upstream source response did not match the preflight candidate.');
		}

		$canonicalSource = $fetched['content'];
		$sourceSha256 = hash('sha256', $canonicalSource);
		if (!hash_equals($expectedSourceSha256, $sourceSha256)) {
			throw new RuntimeException('The immutable upstream raw source fingerprint does not match the validated upstream index.');
		}

		$document = ($this->parser)($canonicalSource);
		if (!is_array($document)) {
			throw new RuntimeException('The immutable upstream source parser returned an invalid document.');
		}

		$isolated = UpstreamTemplateDocumentService::buildImportSource(
			$document,
			$uuid,
			[
				'uuid' => $uuid,
				'name' => $name,
				'technical_name' => $technicalName,
				'vendor_name' => $vendorName,
				'vendor_version' => $vendorVersion
			]
		);

		$source = (string) ($isolated['source'] ?? '');
		if ($source === '') {
			throw new RuntimeException('The isolated update import source is empty.');
		}

		return [
			'commit' => $commit,
			'path' => $path,
			'source_sha256' => $sourceSha256,
			'content_sha256' => $expectedContentSha256,
			'uuid' => $uuid,
			'name' => $name,
			'technical_name' => $technicalName,
			'vendor_name' => $vendorName,
			'vendor_version' => $vendorVersion,
			'import_sha256' => hash('sha256', $source),
			'format' => 'json',
			'source' => $source
		];
	}

	private static function isSafeTemplatePath(string $path): bool {
		if ($path === '' || !str_starts_with($path, 'templates/') || !str_ends_with($path, '.yaml')) {
			return false;
		}

		foreach (explode('/', $path) as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				return false;
			}
		}

		return true;
	}

	private static function normalizeUuid(string $uuid): string {
		return strtolower(str_replace('-', '', trim($uuid)));
	}
}
