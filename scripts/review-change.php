<?php

declare(strict_types=1);

$options = getopt('', ['base:', 'head::', 'output:']);
$root = dirname(__DIR__);
$base = isset($options['base']) ? (string) $options['base'] : '';
$head = isset($options['head']) ? (string) $options['head'] : 'HEAD';
$output = isset($options['output']) ? (string) $options['output'] : '';

if ($base === '' || $output === '') {
    fwrite(STDERR, "Usage: php scripts/review-change.php --base=ref [--head=ref] --output=path\n");
    exit(12);
}

$range = $base . '..' . $head;
$changed = gitLines($root, ['diff', '--name-only', '--diff-filter=ACDMRTUXB', $range]);
$diffCheck = gitLines($root, ['diff', '--check', $range], true);
$findings = [];

$has = static function (string $pattern) use ($changed): bool {
    foreach ($changed as $path) {
        if (preg_match($pattern, $path) === 1) {
            return true;
        }
    }

    return false;
};

if ($diffCheck !== []) {
    $findings[] = ['blocking', 'Whitespace errors', implode('; ', $diffCheck)];
}
if ($has('~^backend/src/~') && !$has('~^tests/.*Test\.php$~')) {
    $findings[] = [
        'blocking',
        'Backend behavior changed without a focused test',
        'Add or update a `tests/*Test.php` case that proves the changed behavior and preserves state invariants.',
    ];
}
if ($has('~^backend/public/api/~') && !$has('~^(config/api-endpoints\.json|docs/API\.md|docs/openapi\.json)$~')) {
    $findings[] = [
        'warning',
        'API implementation changed without contract evidence',
        'Update `config/api-endpoints.json` and regenerate the API documentation, or explain why the contract is unchanged.',
    ];
}
if ($has('~^(composer\.json|composer\.lock)$~') && !$has('~^config/dependency-(policy|usage|weight)\.json$~')) {
    $findings[] = [
        'warning',
        'Composer dependency change lacks policy evidence',
        'Update dependency policy, usage, and weight attribution as applicable, then run the canonical check.',
    ];
}
if ($has('~^(scripts/router\.php|scripts/dast\.sh|backend/src/Http/)~') && !$has('~^(docs/RUNBOOKS\.md|SECURITY\.md|scripts/dast\.sh)$~')) {
    $findings[] = [
        'warning',
        'Request-boundary change lacks security validation updates',
        'Extend the DAST probes or security/runbook evidence for the changed trust boundary.',
    ];
}
if ($has('~^frontend/assets/js/~') && !$has('~^(scripts/check\.sh|scripts/dast\.sh|tests/)~')) {
    $findings[] = [
        'warning',
        'Frontend behavior changed without automated interaction coverage',
        'Add a focused local probe or document exact manual browser validation in the pull request.',
    ];
}

$risk = 'low';
if ($has('~^(backend/src/Services/|backend/public/api/|scripts/router\.php)~')) {
    $risk = 'high';
} elseif ($has('~^(backend/|frontend/|scripts/|config/|composer\.)~')) {
    $risk = 'medium';
}

$lines = [
    '# Local Pull Request Review',
    '',
    "- Range: `{$range}`",
    '- Risk: **' . strtoupper($risk) . '**',
    '- Changed files: ' . count($changed),
    '- Blocking findings: ' . count(array_filter($findings, static fn(array $finding): bool => $finding[0] === 'blocking')),
    '- Warnings: ' . count(array_filter($findings, static fn(array $finding): bool => $finding[0] === 'warning')),
    '',
    '## Review findings',
    '',
];

if ($findings === []) {
    $lines[] = 'No deterministic risk-pattern findings. A human must still review behavior and scope.';
    $lines[] = '';
} else {
    foreach ($findings as [$severity, $title, $detail]) {
        $lines[] = '### ' . strtoupper($severity) . ": {$title}";
        $lines[] = '';
        $lines[] = $detail;
        $lines[] = '';
    }
}

$lines[] = '## Changed files';
$lines[] = '';
foreach ($changed as $path) {
    $lines[] = "- `{$path}`";
}
$lines[] = '';
$lines[] = '## Required reviewer checks';
$lines[] = '';
$lines[] = '- Confirm the diff is scoped and preserves unrelated user changes.';
$lines[] = '- Run `./scripts/check.sh`; rules changes also require focused characterization tests.';
$lines[] = '- Verify chess correctness independently of visual success.';
$lines[] = '- Confirm no CI, paid service, external account, or hidden publication step was added.';
$lines[] = '';

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, implode("\n", $lines));
fwrite(STDOUT, "Local pull request review written to {$output}\n");

/** @param list<string> $arguments
 *  @return list<string>
 */
function gitLines(string $root, array $arguments, bool $allowFailure = false): array
{
    $command = 'git -C ' . escapeshellarg($root);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    exec($command . ' 2>&1', $rows, $status);
    if ($status !== 0 && !$allowFailure) {
        throw new RuntimeException('Git inspection failed: ' . implode("\n", $rows));
    }

    return array_values(array_filter($rows, static fn(string $row): bool => $row !== ''));
}
