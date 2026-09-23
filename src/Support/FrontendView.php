<?php

namespace Modules\ZabbixTemplateUpdateManager\Support;

class FrontendView {

	public static function status(string $text, string $tone = 'grey'): \CSpan {
		$class = match ($tone) {
			'green' => ZBX_STYLE_GREEN,
			'orange' => ZBX_STYLE_ORANGE,
			'red' => ZBX_STYLE_RED,
			default => ZBX_STYLE_GREY
		};

		return (new \CSpan($text))->addClass($class);
	}

	public static function message(string $text, string $tone = 'info'): \CTag {
		$class = match ($tone) {
			'good' => ZBX_STYLE_MSG_GOOD,
			'warning' => ZBX_STYLE_MSG_WARNING,
			'bad' => ZBX_STYLE_MSG_BAD,
			default => ZBX_STYLE_MSG_INFO
		};

		return makeMessageBox($class, [], $text, false);
	}

	public static function section(string $title, $content, $footer = null): \CSection {
		$section = (new \CSection($content))
			->setHeader(new \CSpan($title));

		if ($footer !== null) {
			$section->setFooter($footer);
		}

		return $section;
	}

	public static function controls(array $items): \CTag {
		$list = new \CList();
		foreach ($items as $item) {
			if ($item !== null) {
				$list->addItem($item);
			}
		}

		return (new \CTag('nav', true, $list))
			->setAttribute('aria-label', _('Content controls'));
	}
}
