<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Throwable;

/**
 * Extracts host/template names from Zabbix trigger expressions.
 *
 * At runtime the native Zabbix expression parser is authoritative. The narrow
 * fallback exists for standalone repository tests where the Zabbix frontend
 * parser classes are not loaded.
 */
final class ZabbixExpressionHostExtractor {

	public static function extract(string $expression): array {
		$expression = trim($expression);
		if ($expression === '') {
			return [];
		}

		if (class_exists('\\CExpressionParser') && class_exists('\\CParser')) {
			try {
				$parser = new \CExpressionParser([
					'usermacros' => true,
					'lldmacros' => true
				]);

				if ($parser->parse($expression) === \CParser::PARSE_SUCCESS) {
					$result = $parser->getResult();
					if (is_object($result) && method_exists($result, 'getHosts')) {
						return self::normalize($result->getHosts());
					}
				}
			}
			catch (Throwable $exception) {
				// Standalone/future-version fallback below remains fail-safe:
				// it recognizes only function-argument /host/key references.
			}
		}

		return self::fallback($expression);
	}

	private static function fallback(string $expression): array {
		$hosts = [];

		// A history-function item query is the first function argument:
		// last(/host/key), min(/host/key,5m), find(/host/key,...).
		//
		// Requiring "(" or "," before the slash is intentional. A broad
		// "/host/key" scan falsely interprets arithmetic division such as
		// min(/host/a,5m)/last(/host/b) as an external host named "last(".
		if (preg_match_all('~(?:\\(|,)\\s*/([^/\\r\\n]+)/~u', $expression, $matches) !== false) {
			foreach (($matches[1] ?? []) as $host) {
				$host = trim((string) $host);
				if ($host !== '') {
					$hosts[$host] = true;
				}
			}
		}

		$names = array_keys($hosts);
		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		return $names;
	}

	private static function normalize($hosts): array {
		$unique = [];
		foreach ((array) $hosts as $host) {
			$host = trim((string) $host);
			if ($host !== '') {
				$unique[$host] = true;
			}
		}

		$names = array_keys($unique);
		sort($names, SORT_NATURAL | SORT_FLAG_CASE);
		return $names;
	}
}
