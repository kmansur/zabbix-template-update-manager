<?php

namespace Modules\ZabbixTemplateUpdateManager\Exception;

require_once __DIR__.'/ZtumException.php';

final class ConfigurationImportException extends ZtumException {

	public function __construct(string $message, ?\Throwable $previous = null) {
		parent::__construct('configuration_import_failed', $message, $previous);
	}
}
