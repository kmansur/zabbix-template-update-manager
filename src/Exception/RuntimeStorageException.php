<?php

namespace Modules\ZabbixTemplateUpdateManager\Exception;

require_once __DIR__.'/ZtumException.php';

final class RuntimeStorageException extends ZtumException {

	public function __construct(string $message, ?\Throwable $previous = null) {
		parent::__construct('runtime_storage_failure', $message, $previous);
	}
}
