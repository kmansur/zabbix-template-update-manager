<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

use Modules\ZabbixTemplateUpdateManager\Repository\TemplateOperationHistoryRepository;
use Throwable;

require_once dirname(__DIR__).'/Repository/TemplateOperationHistoryRepository.php';

/**
 * Produces bounded, sanitized supplemental operator-history records.
 *
 * History is never used as authorization, preflight evidence or proof that a
 * configuration write succeeded. Failure to record history is logged and must
 * not rewrite the authoritative controlled-operation result.
 */
final class TemplateOperationHistoryService {

	public function record(
		string $operation,
		string $subject,
		?array $result,
		?Throwable $exception,
		string $actorUserId = ''
	): array {
		$status = $exception !== null
			? 'error'
			: trim((string) ($result['status'] ?? 'completed'));
		if ($status === '') {
			$status = 'completed';
		}

		$writePerformed = null;
		if (is_array($result) && array_key_exists('write_performed', $result)) {
			$writePerformed = (bool) $result['write_performed'];
		}

		$detail = '';
		if ($exception !== null) {
			$detail = $exception->getMessage();
		}
		elseif (is_array($result)) {
			foreach (['error_detail', 'reason', 'detail'] as $key) {
				$value = trim((string) ($result[$key] ?? ''));
				if ($value !== '') {
					$detail = $value;
					break;
				}
			}
		}

		return (new TemplateOperationHistoryRepository())->append([
			'id' => bin2hex(random_bytes(16)),
			'created_at' => gmdate('c'),
			'operation' => $operation,
			'subject' => $subject,
			'status' => $status,
			'write_performed' => $writePerformed,
			'actor_userid' => trim($actorUserId),
			'detail' => $detail
		]);
	}

	public function recordBestEffort(
		string $operation,
		string $subject,
		?array $result,
		?Throwable $exception,
		string $actorUserId = ''
	): void {
		try {
			$this->record($operation, $subject, $result, $exception, $actorUserId);
		}
		catch (Throwable $historyException) {
			error_log(
				'[Zabbix Template Update Manager] Supplemental operation-history write failed: '
				.$historyException->getMessage()
			);
		}
	}
}
