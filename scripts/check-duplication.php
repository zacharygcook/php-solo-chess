<?php

declare(strict_types=1);

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--measure'], true)) {
    fwrite(STDERR, "Usage: php scripts/check-duplication.php --check|--measure\n");
    exit(12);
}

$root = dirname(__DIR__);
$budgets = json_decode(
    (string) file_get_contents($root . '/config/quality-budgets.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$windowSize = (int) $budgets['duplicate_window_lines'];
$maximumPercentage = (float) $budgets['maximum_duplicated_line_percentage'];
$files = array_merge(
    recursiveFiles($root . '/backend/src', ['php']),
    recursiveFiles($root . '/frontend/assets/js', ['js']),
);
$windows = [];
$significantLineCount = 0;
$linesByFile = [];

foreach ($files as $file) {
    $relative = substr($file, strlen($root) + 1);
    $rawLines = file($file, FILE_IGNORE_NEW_LINES);
    if ($rawLines === false) {
        throw new RuntimeException("Unable to read {$relative}");
    }

    $significant = [];
    foreach ($rawLines as $index => $line) {
        $normalized = normalizeCodeLine($line);
        if ($normalized === null) {
            continue;
        }
        $significant[] = ['line' => $index + 1, 'code' => $normalized];
    }
    $linesByFile[$relative] = $significant;
    $significantLineCount += count($significant);

    for ($offset = 0, $limit = count($significant) - $windowSize; $offset <= $limit; $offset++) {
        $slice = array_slice($significant, $offset, $windowSize);
        $fingerprint = hash('sha256', implode("\n", array_column($slice, 'code')));
        $windows[$fingerprint][] = [
            'file' => $relative,
            'start' => $slice[0]['line'],
            'lines' => array_column($slice, 'line'),
        ];
    }
}

$duplicatedLines = [];
$duplicateGroups = [];
foreach ($windows as $occurrences) {
    if (count($occurrences) < 2) {
        continue;
    }

    $locations = array_unique(array_map(
        static fn(array $occurrence): string => $occurrence['file'] . ':' . $occurrence['start'],
        $occurrences,
    ));
    if (count($locations) < 2) {
        continue;
    }

    $duplicateGroups[] = $locations;
    foreach ($occurrences as $occurrence) {
        foreach ($occurrence['lines'] as $line) {
            $duplicatedLines[$occurrence['file'] . ':' . $line] = true;
        }
    }
}

$duplicatedLineCount = count($duplicatedLines);
$percentage = $significantLineCount === 0 ? 0.0 : ($duplicatedLineCount / $significantLineCount) * 100;
usort($duplicateGroups, static fn(array $left, array $right): int => count($right) <=> count($left));

printf(
    "Duplicate-code measure: %d of %d significant lines (%.2f%%), %d repeated windows of %d lines.\n",
    $duplicatedLineCount,
    $significantLineCount,
    $percentage,
    count($duplicateGroups),
    $windowSize,
);

foreach (array_slice($duplicateGroups, 0, 5) as $group) {
    fwrite(STDOUT, ' - ' . implode(', ', array_slice($group, 0, 6)) . "\n");
}

if ($mode === '--check' && $percentage > $maximumPercentage) {
    fwrite(STDERR, sprintf(
        "Duplicated-line percentage %.2f exceeds the %.2f%% non-regression budget.\n",
        $percentage,
        $maximumPercentage,
    ));
    exit(1);
}

/** @return list<string> */
function recursiveFiles(string $directory, array $extensions): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);

    return $files;
}

function normalizeCodeLine(string $line): ?string
{
    $line = trim($line);
    if ($line === '' || preg_match('~^(?://|/\*|\*|\*/|#)~', $line)) {
        return null;
    }

    $line = preg_replace('/\s+/', ' ', $line);
    $line = preg_replace('/(["\']).*?\1/', 'STRING', (string) $line);
    $line = preg_replace('/\b\d+(?:\.\d+)?\b/', 'NUMBER', (string) $line);

    return $line;
}
