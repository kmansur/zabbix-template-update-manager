<?php

$root = dirname(__DIR__);
$version = trim((string) file_get_contents($root.'/VERSION'));
$plan = (string) file_get_contents($root.'/docs/lab-test-plan.md');

function failLabMetadata(string $message): void {
	fwrite(STDERR, $message."\n");
	exit(1);
}

if ($version === '') {
	failLabMetadata('VERSION must not be empty.');
}

if (str_contains($plan, 'verify against the published GitHub Release')
		|| str_contains($plan, 'Archives:   verify against the published SHA256SUMS')) {
	failLabMetadata('Laboratory release metadata must not contain publication placeholders.');
}

if (str_contains($version, '-beta.')) {
	$tag = 'v'.$version;
	if (!str_contains($plan, '# Laboratory test plan — '.$version)
			|| !str_contains($plan, 'Tag:        '.$tag)) {
		failLabMetadata('The laboratory plan must identify the current beta VERSION and immutable tag.');
	}

	if (preg_match('/Tag commit:\s+([a-f0-9]{40})/', $plan) !== 1) {
		failLabMetadata('The laboratory plan must pin a 40-character release commit SHA.');
	}

	if (preg_match('/Tar SHA-256:\s+([a-f0-9]{64})/', $plan) !== 1
			|| preg_match('/ZIP SHA-256:\s+([a-f0-9]{64})/', $plan) !== 1) {
		failLabMetadata('The laboratory plan must pin release archive SHA-256 fingerprints.');
	}

	if (str_contains($plan, 'git checkout main')) {
		failLabMetadata('Immutable beta field evidence must not instruct testers to checkout main.');
	}
}

echo "Laboratory release metadata validation passed.\n";
