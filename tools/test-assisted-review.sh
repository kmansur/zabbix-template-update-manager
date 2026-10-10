#!/bin/sh
# Read-only ZTUM baseline-free assisted-review regression smoke.
# Usage: sh tools/test-assisted-review.sh
set -eu
cd "$(dirname "$0")/.."
for file in \
  src/Service/BaselineFreeReviewAssessment.php \
  src/Service/UpdateReadinessEvaluator.php \
  src/Service/TemplateUpdatePreflightService.php \
  src/Service/TemplateUpdateAnalysisService.php \
  views/ztum.template.compare.php \
  views/ztum.template.preflight.php; do
  php -l "$file"
done
for test in \
  tests/unit/BaselineFreeReviewAssessmentTest.php \
  tests/unit/BaselineFreeAssistedReadinessTest.php \
  tests/unit/BaselineFreeAssistedFlowTest.php \
  tests/unit/BaselineFreeBatchRestrictionTest.php \
  tests/unit/UpdateReadinessEvaluatorTest.php \
  tests/unit/TemplateUpdatePreflightServiceTest.php \
  tests/unit/TemplateControlledUpdateServiceTest.php \
  tests/unit/NativeCustomizationReviewContractTest.php; do
  php "$test"
done
php tests/ui_native_guard.php
php tests/read_only_guard.php
printf '\nAssisted-review regression checks completed. No Zabbix configuration writes were requested.\n'
