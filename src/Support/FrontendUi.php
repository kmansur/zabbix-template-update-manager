<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

/**
 * Small native-UI helpers.
 *
 * The module deliberately reuses Zabbix-provided classes and theme styles.
 * No custom colors are defined here; light/dark theme behavior stays owned by
 * the frontend.
 */
final class FrontendUi {

	public const SUCCESS = 'success';
	public const WARNING = 'warning';
	public const DANGER = 'danger';
	public const INFO = 'info';
	public const MUTED = 'muted';

	public static function status(string $text, string $tone = self::MUTED): \CSpan {
		$span = new \CSpan($text);
		$style = self::styleForTone($tone);

		if ($style !== '') {
			$span->addClass($style);
		}

		return $span;
	}

	public static function yesNo(bool $value): \CSpan {
		return self::status(
			$value ? _('Yes') : _('No'),
			$value ? self::SUCCESS : self::MUTED
		);
	}

	private static function styleForTone(string $tone): string {
		$constant = match ($tone) {
			self::SUCCESS => 'ZBX_STYLE_GREEN',
			self::WARNING => 'ZBX_STYLE_ORANGE',
			self::DANGER => 'ZBX_STYLE_RED',
			self::INFO => 'ZBX_STYLE_BLUE',
			default => 'ZBX_STYLE_GREY'
		};

		return defined($constant) ? (string) constant($constant) : '';
	}
}
