<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Throwable;

/**
 * Extracts host/template and item references from Zabbix trigger expressions.
 *
 * At runtime the native Zabbix expression parser is authoritative. The narrow
 * fallback exists for standalone repository tests where frontend parser
 * classes are not loaded.
 */
final class ZabbixExpressionHostExtractor {

	public static function extract(string $expression): array {
		$hosts = [];
		foreach (self::extractReferences($expression) as $reference) {
			$host = trim((string) ($reference['host'] ?? ''));
			if ($host !== '') {
				$hosts[$host] = true;
			}
		}

		$names = array_keys($hosts);
		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		return $names;
	}

	public static function extractReferences(string $expression): array {
		$expression = trim($expression);
		if ($expression === '') {
			return [];
		}

		if (class_exists('\\CExpressionParser')
				&& class_exists('\\CParser')
				&& class_exists('\\CExpressionParserResult')) {
			try {
				$parser = new \CExpressionParser([
					'usermacros' => true,
					'lldmacros' => true
				]);

				if ($parser->parse($expression) === \CParser::PARSE_SUCCESS) {
					$result = $parser->getResult();
					if (is_object($result) && method_exists($result, 'getTokensOfTypes')) {
						$references = [];
						$tokens = $result->getTokensOfTypes([
							\CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION
						]);
						foreach ($tokens as $token) {
							$data = $token['data']['parameters'][0]['data'] ?? null;
							if (!is_array($data)) {
								continue;
							}
							$host = trim((string) ($data['host'] ?? ''));
							$item = trim((string) ($data['item'] ?? ''));
							if ($host !== '') {
								$references[] = ['host' => $host, 'item' => $item];
							}
						}
						return self::uniqueReferences($references);
					}
				}
			}
			catch (Throwable $exception) {
				// Standalone/future-version fallback below remains bounded.
			}
		}

		return self::fallbackReferences($expression);
	}

	private static function fallbackReferences(string $expression): array {
		$references = [];

		// Standalone-only fallback. Requiring "(" or "," before the slash is
		// essential: a broad "/host/key" scan misreads arithmetic division such
		// as min(/host/a,5m)/last(/host/b) as a host named "last(".
		if (preg_match_all(
				'~(?:\\(|,)\\s*/([^/\\r\\n]+)/([^,\\)\\r\\n]+)~u',
				$expression,
				$matches,
				PREG_SET_ORDER
			) !== false) {
			foreach ($matches as $match) {
				$host = trim((string) ($match[1] ?? ''));
				$item = trim((string) ($match[2] ?? ''));
				if ($host !== '') {
					$references[] = ['host' => $host, 'item' => $item];
				}
			}
		}

		return self::uniqueReferences($references);
	}

	private static function uniqueReferences(array $references): array {
		$unique = [];
		foreach ($references as $reference) {
			if (!is_array($reference)) {
				continue;
			}
			$host = trim((string) ($reference['host'] ?? ''));
			$item = trim((string) ($reference['item'] ?? ''));
			if ($host === '') {
				continue;
			}
			$unique[$host."\0".$item] = ['host' => $host, 'item' => $item];
		}

		$result = array_values($unique);
		usort($result, static function (array $left, array $right): int {
			return [$left['host'], $left['item']] <=> [$right['host'], $right['item']];
		});
		return $result;
	}
}
