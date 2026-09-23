<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

/**
 * Native Zabbix UI helpers shared by every user-facing module view.
 *
 * The module intentionally relies only on Zabbix-provided components, style
 * constants and theme behavior. No custom colors or inline CSS are introduced.
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

	public static function message(string $text, string $tone = self::INFO): \CTag {
		$class = match ($tone) {
			self::SUCCESS => defined('ZBX_STYLE_MSG_GOOD') ? ZBX_STYLE_MSG_GOOD : '',
			self::WARNING => defined('ZBX_STYLE_MSG_WARNING') ? ZBX_STYLE_MSG_WARNING : '',
			self::DANGER => defined('ZBX_STYLE_MSG_BAD') ? ZBX_STYLE_MSG_BAD : '',
			default => defined('ZBX_STYLE_MSG_INFO') ? ZBX_STYLE_MSG_INFO : ''
		};

		if ($class !== '' && function_exists('makeMessageBox')) {
			return makeMessageBox($class, [], $text, false);
		}

		return new \CTag('p', true, self::status($text, $tone));
	}

	public static function section(string $title): \CTag {
		return new \CTag('h4', true, $title);
	}

	public static function description($content): \CTag {
		return new \CTag('p', true, $content);
	}

	public static function fingerprint(string $value, int $visibleLength = 20): \CSpan {
		$value = trim($value);
		$display = $value === ''
			? '—'
			: (strlen($value) > $visibleLength ? substr($value, 0, $visibleLength).'…' : $value);

		$span = new \CSpan($display);
		if (defined('ZBX_STYLE_MONOSPACE_FONT')) {
			$span->addClass(ZBX_STYLE_MONOSPACE_FONT);
		}
		if ($value !== '' && $display !== $value) {
			$span->setAttribute('title', $value);
		}

		return $span;
	}

	public static function reason(?string $code): string {
		$code = trim((string) $code);
		if ($code === '') {
			return '—';
		}

		$labels = [
			'unsupported_zabbix_version' => _('Unsupported Zabbix version'),
			'official_template_not_found' => _('Official template not found'),
			'upstream_version_missing' => _('Upstream version is missing'),
			'template_already_installed' => _('Template is already installed'),
			'technical_name_collision' => _('Technical name is already used by another template'),
			'missing_template_dependencies' => _('Required template dependencies are missing'),
			'cross_template_graph_dependency' => _('Cross-template graph dependency must be installed first'),
			'cross_template_trigger_dependency' => _('Cross-template trigger dependency must be installed first'),
			'cross_template_dashboard_dependency' => _('Cross-template dashboard dependency must be installed first'),
			'missing_dashboard_graph_dependency' => _('Required dashboard graph dependency is missing'),
			'unresolved_internal_references' => _('Template references could not be resolved safely'),
			'install_would_modify_existing_configuration' => _('Installation would modify existing configuration'),
			'install_preview_contains_no_creations' => _('Installation preview contains no new objects'),
			'historical_baseline_unavailable' => _('Historical baseline unavailable'),
			'historical_baseline_ambiguous' => _('Historical baseline is ambiguous'),
			'historical_baseline_time_budget_reached' => _('Historical baseline scan will continue'),
			'historical_baseline_continuation_limit_reached' => _('Historical baseline scan limit reached'),
			'invalid_preflight_evidence' => _('Invalid preflight evidence'),
			'post_install_validation_failed' => _('Post-install validation failed'),
			'content_not_current_upstream' => _('Installed content does not match current upstream'),
			'remaining_import_differences' => _('Import differences remain after validation'),
			'request_failed' => _('Request failed'),
			'state_unknown_after_failure' => _('State is unknown after the failed request'),
			'evidence_changed' => _('Preflight evidence changed'),
			'current_state_changed' => _('Current template state changed')
		];

		if (array_key_exists($code, $labels)) {
			return $labels[$code];
		}

		$label = str_replace(['_', '-'], ' ', $code);
		return ucfirst($label);
	}

	public static function reasonList(array $codes): string {
		$labels = [];
		foreach ($codes as $code) {
			$label = self::reason(is_scalar($code) ? (string) $code : '');
			if ($label !== '—') {
				$labels[] = $label;
			}
		}

		return $labels !== [] ? implode(', ', array_values(array_unique($labels))) : '—';
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
