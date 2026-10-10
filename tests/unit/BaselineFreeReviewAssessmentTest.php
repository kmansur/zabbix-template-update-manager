<?php
use Modules\ZabbixTemplateUpdateManager\Service\BaselineFreeReviewAssessment as Review;
require_once dirname(__DIR__, 2).'/src/Service/BaselineFreeReviewAssessment.php';
function expectReview(string $expected, array $actual): void {
    if ($actual['status'] !== $expected || $actual['write_enabled'] !== false) {
        throw new RuntimeException('Unexpected assisted review evidence: '.var_export($actual, true));
    }
}
$detail = ['path' => 'templates/0/items/0', 'entity_type' => 'items', 'change_type' => 'updated'];
$preview = ['summary' => ['total' => 1, 'unresolved' => 0], 'details' => [$detail], 'details_truncated' => false];
expectReview('candidate_for_assisted_review', Review::evaluate(['status' => 'ambiguous'], $preview));
expectReview('baseline_available', Review::evaluate(['status' => 'found'], $preview));
expectReview('unverified', Review::evaluate(null, null));
expectReview('unverified', Review::evaluate(null, array_merge($preview, ['details_truncated' => true])));
expectReview('unverified', Review::evaluate(null, array_merge($preview, ['details' => []])));
expectReview('unverified', Review::evaluate(null, array_merge($preview, ['summary' => ['total' => 1, 'unresolved' => 1]])));
expectReview('unverified', Review::evaluate(null, array_merge($preview, ['details' => [['entity_type' => 'items', 'change_type' => 'updated']]])));
echo "BaselineFreeReviewAssessment tests passed.\n";
