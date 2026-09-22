<?php

namespace Modules\ZabbixTemplateUpdateManager\Service;

/**
 * Evaluates whether an official template update has enough proven comparison
 * evidence to advance through review, backup and controlled preflight.
 *
 * Hard blockers (missing/ambiguous baseline, unresolved identities or real
 * BASE/LOCAL/UPSTREAM conflicts) remain fail-closed. Known local-overwrite
 * differences and high technical risk enter an explicitly reviewed manual
 * path. Medium technical impact remains manual by default, except for narrowly
 * recognized standard-path-eligible changes proven by the risk analyzer (for
 * example bounded discard-only preprocessing maintenance with complete
 * three-way evidence and no local overwrite).
 *
 * This evaluator never authorizes a configuration write by itself.
 */
final class UpdateReadinessEvaluator {

	public static function evaluate(
		array $template,
		?array $historicalBaseline,
		?array $threeWayAnalysis,
		?array $updatePreview,
		?array $updateRisk,
		?array $backupVerification = null
	): array {
		$result = [
			'status' => 'not_applicable',
			'next_step' => 'none',
			'candidate_for_backup' => false,
			'backup_verified' => false,
			'manual_confirmation_required' => false,
			'manual_reasons' => [],
			'write_enabled' => false,
			'blockers' => [],
			'review_flags' => [],
			'direct_host_count' => max(0, (int) ($template['host_count'] ?? 0))
		];

		if (($template['upstream_status'] ?? null) !== 'official_match'
				|| ($template['version_status'] ?? null) !== 'update_available') {
			return $result;
		}

		if (!is_array($updatePreview)) {
			return self::blocked($result, 'blocked_unresolved', 'resolve_update_preview', 'update_preview_unavailable');
		}

		$previewSummary = is_array($updatePreview['summary'] ?? null) ? $updatePreview['summary'] : [];
		if ((int) ($previewSummary['unresolved'] ?? 0) > 0) {
			return self::blocked($result, 'blocked_unresolved', 'resolve_update_preview', 'update_preview_unresolved');
		}

		if (!is_array($historicalBaseline) || ($historicalBaseline['status'] ?? null) !== 'found') {
			$baselineStatus = is_array($historicalBaseline)
				? (string) ($historicalBaseline['status'] ?? 'unavailable')
				: 'unavailable';
			return self::blocked(
				$result,
				'blocked_baseline',
				'resolve_historical_baseline',
				'historical_baseline_'.$baselineStatus
			);
		}

		if (!is_array($threeWayAnalysis)) {
			return self::blocked($result, 'blocked_unresolved', 'resolve_three_way_analysis', 'three_way_unavailable');
		}

		$threeWaySummary = is_array($threeWayAnalysis['summary'] ?? null) ? $threeWayAnalysis['summary'] : [];
		if ((int) ($threeWaySummary['unresolved'] ?? 0) > 0) {
			return self::blocked($result, 'blocked_unresolved', 'resolve_three_way_analysis', 'three_way_unresolved');
		}

		if ((int) ($threeWaySummary['conflict'] ?? 0) > 0) {
			return self::blocked($result, 'blocked_conflict', 'resolve_conflicts', 'three_way_conflict');
		}

		if (!is_array($updateRisk)) {
			return self::blocked($result, 'blocked_unresolved', 'resolve_risk_analysis', 'risk_unavailable');
		}

		if (($updateRisk['coverage'] ?? null) !== 'complete') {
			return self::blocked($result, 'blocked_unresolved', 'resolve_three_way_analysis', 'risk_coverage_incomplete');
		}

		$riskLevel = (string) ($updateRisk['level'] ?? 'unknown');
		if ($riskLevel === 'conflict') {
			return self::blocked($result, 'blocked_conflict', 'resolve_conflicts', 'risk_conflict');
		}
		if ($riskLevel === 'unknown' || !in_array($riskLevel, ['none', 'low', 'medium', 'high'], true)) {
			return self::blocked($result, 'blocked_unresolved', 'resolve_risk_analysis', 'risk_unknown');
		}

		$manualReasons = [];
		if ((int) ($threeWaySummary['local_only_overwrite'] ?? 0) > 0) {
			$manualReasons[] = 'local_customization_overwrite';
		}
		if ($riskLevel === 'high') {
			$manualReasons[] = 'high_technical_risk';
		}
		elseif ($riskLevel === 'medium' && empty($updateRisk['standard_path_eligible'])) {
			$manualReasons[] = 'medium_technical_risk';
		}

		if ($manualReasons !== []) {
			$result['manual_confirmation_required'] = true;
			$result['manual_reasons'] = $manualReasons;
			$result['review_flags'] = $manualReasons;

			if (is_array($backupVerification)
					&& ($backupVerification['status'] ?? null) === 'current_match'
					&& !empty($backupVerification['current_match'])) {
				$result['status'] = 'review_backup_verified';
				$result['next_step'] = 'run_manual_preflight';
				$result['backup_verified'] = true;
				return $result;
			}

			$result['status'] = 'review_required';
			$result['next_step'] = 'create_and_verify_backup';
			$result['candidate_for_backup'] = true;
			return $result;
		}

		if (is_array($backupVerification)
				&& ($backupVerification['status'] ?? null) === 'current_match'
				&& !empty($backupVerification['current_match'])) {
			$result['status'] = 'backup_verified';
			$result['next_step'] = 'run_controlled_preflight';
			$result['backup_verified'] = true;
			return $result;
		}

		$result['status'] = 'candidate_for_backup';
		$result['next_step'] = 'create_and_verify_backup';
		$result['candidate_for_backup'] = true;

		return $result;
	}

	private static function blocked(array $result, string $status, string $nextStep, string $reason): array {
		$result['status'] = $status;
		$result['next_step'] = $nextStep;
		$result['blockers'][] = $reason;
		return $result;
	}
}
