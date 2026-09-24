<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

/**
 * Central compatibility layer for native Zabbix frontend differences.
 *
 * Keep version-specific UI details here instead of branching the complete
 * module codebase or spreading defined()/constant() checks across controllers
 * and views.
 */
final class ZabbixUiCompat {

	private const PAGER_STYLE_CANDIDATES = [
		'ZBX_STYLE_PAGER',
		'ZBX_STYLE_TABLE_PAGING'
	];

	private const PAGER_CONTAINER_STYLE_CANDIDATES = [
		'ZBX_STYLE_PAGER_CONTAINER',
		'ZBX_STYLE_PAGING_BTN_CONTAINER'
	];

	public static function pagerClass(): string {
		return self::firstDefinedStyle(self::PAGER_STYLE_CANDIDATES);
	}

	public static function pagerContainerClass(): string {
		return self::firstDefinedStyle(self::PAGER_CONTAINER_STYLE_CANDIDATES);
	}

	/**
	 * Return the first available native Zabbix style constant.
	 *
	 * Candidates must be ordered from the current/preferred frontend name to
	 * older supported fallbacks.
	 */
	public static function firstDefinedStyle(array $constantNames): string {
		foreach ($constantNames as $constantName) {
			if (!is_string($constantName) || $constantName === '' || !defined($constantName)) {
				continue;
			}

			$value = constant($constantName);
			if (is_string($value) && $value !== '') {
				return $value;
			}
		}

		return '';
	}
}
