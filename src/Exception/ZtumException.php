<?php

namespace Modules\ZabbixTemplateUpdateManager\Exception;

use RuntimeException;
use Throwable;

class ZtumException extends RuntimeException {

	private string $machineCode;

	public function __construct(string $machineCode, string $message, ?Throwable $previous = null) {
		$machineCode = strtolower(trim($machineCode));
		if (preg_match('/^[a-z][a-z0-9_]{2,63}$/', $machineCode) !== 1) {
			$machineCode = 'internal_error';
		}

		$this->machineCode = $machineCode;
		parent::__construct($message, 0, $previous);
	}

	public function getMachineCode(): string {
		return $this->machineCode;
	}
}
