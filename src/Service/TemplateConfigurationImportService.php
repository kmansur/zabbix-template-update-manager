<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use CMessageHelper;
use RuntimeException;

require_once __DIR__.'/TemplateImportCompareService.php';

/**
 * The single intentionally write-enabled Zabbix configuration boundary.
 *
 * Callers must complete fresh preflight validation before invoking this class.
 * This service imports one already isolated official template source using the
 * exact same rule profile used by configuration.importcompare.
 */
final class TemplateConfigurationImportService {

	public function import(
		string $source,
		string $format = 'json',
		string $ruleProfile = TemplateImportCompareService::PROFILE_UPDATE
	): void {
		if ($source === '') {
			throw new RuntimeException('The controlled template import source is empty.');
		}
		if (!in_array($format, ['json', 'yaml'], true)) {
			throw new RuntimeException('The controlled template import format is not supported.');
		}

		$messagesBefore = CMessageHelper::getMessages();

		$result = API::Configuration()->import([
			'format' => $format,
			'source' => $source,
			'rules' => TemplateImportCompareService::rules($ruleProfile)
		]);

		if ($result !== true) {
			$messagesAfter = CMessageHelper::getMessages();
			$newMessages = array_slice($messagesAfter, count($messagesBefore));
			$details = [];

			foreach ($newMessages as $message) {
				if (!is_array($message) || ($message['type'] ?? null) !== CMessageHelper::MESSAGE_TYPE_ERROR) {
					continue;
				}

				$text = trim((string) ($message['message'] ?? ''));
				if ($text !== '') {
					$details[] = $text;
				}
			}

			$details = array_values(array_unique($details));
			$message = 'Zabbix configuration import did not report success.';

			if ($details !== []) {
				$message .= ' '.implode(' | ', array_slice($details, -3));
			}

			throw new RuntimeException($message);
		}
	}
}
