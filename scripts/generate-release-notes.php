<?php

declare(strict_types=1);

$options = getopt('', ['from::', 'to::', 'output:']);
$root = dirname(__DIR__);
$to = isset($options['to']) ? (string) $options['to'] : 'HEAD';
$from = isset($options['from']) ? (string) $options['from'] : '';
$output = isset($options['output']) ? (string) $options['output'] : '';

if ($output === '') {
    fwrite(STDERR, "Usage: php scripts/generate-release-notes.php [--from=ref] [--to=ref] --output=path\n");
    exit(12);
}

if ($from === '') {
    $from = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-list --max-parents=0 HEAD'));
}

$range = $from . '..' . $to;
$command = sprintf(
    'git -C %s log --reverse --format=%s %s 2>&1',
    escapeshellarg($root),
    escapeshellarg('%h%x09%s'),
    escapeshellarg($range),
);
exec($command, $rows, $status);
if ($status !== 0) {
    throw new RuntimeException("Unable to read Git range {$range}: " . implode("\n", $rows));
}

$groups = ['Fixes' => [], 'Improvements' => [], 'Documentation and workflow' => [], 'Other' => []];
foreach ($rows as $row) {
    [$hash, $subject] = array_pad(explode("\t", $row, 2), 2, '');
    $normalized = strtolower($subject);
    if (preg_match('/\b(fix|repair|correct|harden)\b/', $normalized)) {
        $group = 'Fixes';
    } elseif (preg_match('/\b(add|create|generate|implement|enforce|install|pin)\b/', $normalized)) {
        $group = 'Improvements';
    } elseif (preg_match('/\b(document|guide|template|readiness|workflow)\b/', $normalized)) {
        $group = 'Documentation and workflow';
    } else {
        $group = 'Other';
    }
    $groups[$group][] = "- {$subject} (`{$hash}`)";
}

$resolvedTo = trim((string) shell_exec(
    'git -C ' . escapeshellarg($root) . ' rev-parse ' . escapeshellarg($to),
));
$lines = [
    '# Release Notes',
    '',
    "- Range: `{$range}`",
    "- Target commit: `{$resolvedTo}`",
    '- Commits: ' . count($rows),
    '',
];

foreach ($groups as $heading => $items) {
    if ($items === []) {
        continue;
    }
    $lines[] = "## {$heading}";
    $lines[] = '';
    $lines = array_merge($lines, $items, ['']);
}

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, implode("\n", $lines));
fwrite(STDOUT, "Release notes written to {$output}\n");
