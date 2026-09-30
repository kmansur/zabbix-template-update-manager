<?php

namespace Modules\ZabbixTemplateUpdateManager\Exception;

require_once __DIR__.'/ZtumException.php';

final class LockContendedException extends ZtumException {

	public function __construct(string $message = 'Another Template Update Manager configuration operation is already in progress.') {
		parent::__construct('lock_contended', $message);
	}
}
