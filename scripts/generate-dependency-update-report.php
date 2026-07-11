<?php

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php generate-dependency-update-report.php <composer.json> <jquery.json> <output.md>\n");
    exit(12);
}

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$jquery = json_decode((string) file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
$policy = json_decode(
    (string) file_get_contents($root . '/config/dependency-policy.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$output = $argv[3];
$installed = $composer['installed'] ?? [];
$lines = [
    '# Dependency Update Proposals',
    '',
    '- Generated: ' . gmdate('c'),
    '- Policy: direct releases must be at least 30 days old before adoption',
    '- Action: review only; this command never edits manifests, opens PRs, or publishes changes',
    '',
    '## Composer direct dependencies',
    '',
    '| Package | Current | Latest | Status | Proposed action |',
    '|---|---:|---:|---|---|',
];

foreach ($installed as $package) {
    $current = ltrim((string) $package['version'], 'v');
    $latest = ltrim((string) $package['latest'], 'v');
    $status = $package['latest-status'] ?? ($current === $latest ? 'up-to-date' : 'update-available');
    $action = $current === $latest
        ? 'None.'
        : 'Verify upstream release date, wait 30 days, run tests/security review, then update the approval record and lock intentionally.';
    $lines[] = "| `{$package['name']}` | `{$current}` | `{$latest}` | {$status} | {$action} |";
}

$currentJquery = $policy['runtime_cdn']['jquery']['version'];
$latestJquery = ltrim((string) ($jquery['tag_name'] ?? ''), 'v');
$published = (string) ($jquery['published_at'] ?? 'unknown');
$jqueryAction = $latestJquery === $currentJquery
    ? 'None.'
    : 'Treat as a frontend compatibility change; verify age, review migration notes, and run browser plus canonical QA.';
$lines = array_merge($lines, [
    '',
    '## Browser runtime dependency',
    '',
    '| Package | Current | Latest release | Published | Proposed action |',
    '|---|---:|---:|---|---|',
    "| `jquery` | `{$currentJquery}` | `{$latestJquery}` | {$published} | {$jqueryAction} |",
    '',
    '## Required review sequence',
    '',
    '1. Confirm license, cost, account, and PHP/browser compatibility.',
    '2. Confirm the selected release satisfies `DEPENDENCY_POLICY.md` or document a security exception.',
    '3. Update one direct dependency at a time and inspect the complete lock or CDN diff.',
    '4. Run `composer audit`, `./scripts/security-review.sh`, `./scripts/test-flakiness.sh`, and `./scripts/check.sh`.',
    '',
]);

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, implode("\n", $lines));
fwrite(STDOUT, "Dependency update report written to {$output}\n");
