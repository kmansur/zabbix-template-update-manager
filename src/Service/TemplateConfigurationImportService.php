<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use API;
use CMessageHelper;
use Modules\ZabbixTemplateUpdateManager\Exception\ConfigurationImportException;
use Throwable;

require_once dirname(__DIR__).'/Exception/ConfigurationImportException.php';
require_once __DIR__.'/TemplateImportCompareService.php';

/**
 * The single intentionally write-enabled Zabbix configuration boundary.
 *
 * Callers must complete fresh preflight validation before invoking this class.
 * This service imports one already isolated official template source using a
 * reviewed named rule profile supplied by TemplateImportCompareService. The
 * same profile must have been used by configuration.importcompare.
 */
final class TemplateConfigurationImportService {

	public function import(
		string $source,
		string $format = 'json',
		string $ruleProfile = TemplateImportCompareService::PROFILE_UPDATE
	): void {
		if ($source === '') {
			throw new ConfigurationImportException('The controlled template import source is empty.');
		}
		if (!in_array($format, ['json', 'yaml'], true)) {
			throw new ConfigurationImportException('The controlled template import format is not supported.');
		}

		$params = [
			'format' => $format,
			'source' => $source,
			'rules' => TemplateImportCompareService::rules($ruleProfile)
		];

		/*
		 * Prefer the native API client response when the frontend wrapper exposes
		 * it. CFrontendApiWrapper normally converts an API error into global
		 * CMessageHelper state plus false; using the underlying response here keeps
		 * this write boundary diagnostic independent of that global side effect.
		 *
		 * API::setWrapper(null) mirrors CFrontendApiWrapper::callMethod(): nested
		 * service calls must execute as local API services during the client call.
		 * The original wrapper is always restored.
		 */
		$wrapper = API::getWrapper();
		// Zabbix 8 changed the internal client authentication argument type.
		// Delegate 8.x writes to the frontend API wrapper; never retry a write.
		$majorVersion = defined('ZABBIX_VERSION')
			? (int) explode('.', (string) constant('ZABBIX_VERSION'))[0]
			: 0;
		if ($majorVersion >= 8) {
			$this->importViaFrontendWrapper($params);
			return;
		}

		if (is_object($wrapper) && method_exists($wrapper, 'getClient')
				&& isset($wrapper->auth) && is_array($wrapper->auth)) {
			$client = $wrapper->getClient();

			if (is_object($client) && method_exists($client, 'callMethod')) {
				try {
					API::setWrapper();
					$response = $client->callMethod('configuration', 'import', $params, $wrapper->auth);
				}
				catch (Throwable $exception) {
					throw new ConfigurationImportException(
						'Zabbix configuration import raised an API client exception: '.$exception->getMessage(),
						$exception
					);
				}
				finally {
					API::setWrapper($wrapper);
				}

				if (!is_object($response)) {
					throw new ConfigurationImportException(
						'Zabbix configuration import returned an invalid API client response.'
					);
				}

				$errorCode = (int) ($response->errorCode ?? 0);
				if ($errorCode !== 0) {
					$detail = trim((string) ($response->errorMessage ?? ''));
					throw new ConfigurationImportException(
						$detail !== ''
							? 'Zabbix configuration import failed: '.$detail
							: 'Zabbix configuration import failed with API error code '.$errorCode.'.'
					);
				}

				if (($response->data ?? null) !== true) {
					throw new ConfigurationImportException(
						'Zabbix configuration import did not report success.'
					);
				}

				return;
			}
		}

		/*
		 * Compatibility fallback for an unexpected frontend wrapper shape.
		 * Messages are scoped to this API call and are never the primary diagnostic
		 * path on supported Zabbix 7.x/8.x frontend wrappers.
		 */
		$this->importViaFrontendWrapper($params);
	}

	private function importViaFrontendWrapper(array $params): void {
		$messagesBefore = CMessageHelper::getMessages();

		try {
			$result = API::Configuration()->import($params);
		}
		catch (Throwable $exception) {
			throw new ConfigurationImportException(
				'Zabbix configuration import raised an exception: '.$exception->getMessage(),
				$exception
			);
		}

		if ($result === true) {
			return;
		}

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

		throw new ConfigurationImportException($message);
	}
}
