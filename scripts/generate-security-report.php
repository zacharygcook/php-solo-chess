<?php

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php generate-security-report.php <audit.json> <secret-status> <output.md>\n");
    exit(12);
}

$root = dirname(__DIR__);
$audit = json_decode((string) file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$secretStatus = (int) $argv[2];
$output = $argv[3];
$lock = json_decode((string) file_get_contents($root . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$bootstrap = (string) file_get_contents($root . '/backend/src/bootstrap.php');
$response = (string) file_get_contents($root . '/backend/src/Http/JsonResponse.php');
$frontend = (string) file_get_contents($root . '/frontend/index.html');
$apiFiles = glob($root . '/backend/public/api/*.php') ?: [];
$advisories = $audit['advisories'] ?? [];
$abandoned = $audit['abandoned'] ?? [];
$packageCount = count($lock['packages'] ?? []) + count($lock['packages-dev'] ?? []);
$methodGuarded = 0;

foreach ($apiFiles as $apiFile) {
    $source = (string) file_get_contents($apiFile);
    if (str_contains($source, "\$_SERVER['REQUEST_METHOD']")) {
        $methodGuarded++;
    }
}

$findings = [
    [
        'severity' => $secretStatus === 0 ? 'PASS' : 'HIGH',
        'control' => 'Local credential scan',
        'evidence' => $secretStatus === 0
            ? 'No high-signal private-key or token pattern found.'
            : 'The local credential scanner reported one or more findings.',
    ],
    [
        'severity' => $advisories === [] ? 'PASS' : 'HIGH',
        'control' => 'Composer advisories',
        'evidence' => sprintf('%d advisory package entries across %d locked packages.', count($advisories), $packageCount),
    ],
    [
        'severity' => $abandoned === [] ? 'PASS' : 'MEDIUM',
        'control' => 'Abandoned Composer packages',
        'evidence' => sprintf('%d abandoned package entries.', count($abandoned)),
    ],
    [
        'severity' => $methodGuarded === count($apiFiles) ? 'PASS' : 'HIGH',
        'control' => 'API method guards',
        'evidence' => sprintf('%d of %d PHP API entry points inspect REQUEST_METHOD.', $methodGuarded, count($apiFiles)),
    ],
    [
        'severity' => str_contains($response, "header('Content-Type: application/json')") ? 'PASS' : 'MEDIUM',
        'control' => 'JSON response content type',
        'evidence' => 'Static inspection of JsonResponse.',
    ],
    [
        'severity' => str_contains($bootstrap, 'session_set_cookie_params') ? 'PASS' : 'LOW',
        'control' => 'Session cookie hardening',
        'evidence' => str_contains($bootstrap, 'session_set_cookie_params')
            ? 'Cookie parameters are configured before session start.'
            : 'No explicit SameSite or HttpOnly parameters; risk is limited by the local-only HTTP runtime.',
    ],
    [
        'severity' => str_contains($frontend, 'integrity=') ? 'PASS' : 'MEDIUM',
        'control' => 'CDN script integrity',
        'evidence' => str_contains($frontend, 'integrity=')
            ? 'External frontend script has a subresource-integrity attribute.'
            : 'Pinned jQuery is loaded from a CDN without subresource integrity.',
    ],
];

$lines = [
    '# Local Security Review',
    '',
    '- Generated: ' . gmdate('c'),
    '- Commit: `' . trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD')) . '`',
    '- Scope: repository source, API entry points, session bootstrap, frontend CDN, Composer lock, and local secret scan',
    '',
    '## Findings',
    '',
    '| Severity | Control | Evidence |',
    '|---|---|---|',
];

foreach ($findings as $finding) {
    $lines[] = sprintf('| %s | %s | %s |', $finding['severity'], $finding['control'], $finding['evidence']);
}

$lines[] = '';
$lines[] = '## Interpretation';
$lines[] = '';
$lines[] = 'HIGH findings block the command. MEDIUM and LOW findings remain visible engineering risks;';
$lines[] = 'they are not silently converted into passes. This report is local evidence and does not create';
$lines[] = 'an external account, hosted scan, CI job, or paid service.';
$lines[] = '';

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, implode("\n", $lines));

$blocking = array_filter($findings, static fn(array $finding): bool => $finding['severity'] === 'HIGH');
fwrite(STDOUT, "Security review written to {$output}\n");
exit($blocking === [] ? 0 : 1);
