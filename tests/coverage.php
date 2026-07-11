<?php

declare(strict_types=1);

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--measure'], true)) {
    fwrite(STDERR, "Usage: XDEBUG_MODE=coverage php tests/coverage.php --check|--measure\n");
    exit(12);
}
if (!function_exists('xdebug_start_code_coverage')) {
    fwrite(STDERR, "Xdebug coverage is required. Use the devcontainer or install Xdebug locally.\n");
    exit(12);
}

$root = dirname(__DIR__);
$budgets = json_decode(
    (string) file_get_contents($root . '/config/quality-budgets.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
$sourceFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/backend/src', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $sourceFiles[] = $file->getPathname();
    }
}
sort($sourceFiles, SORT_STRING);
foreach ($sourceFiles as $sourceFile) {
    require_once $sourceFile;
}

define('SOLO_CHESS_COVERAGE', true);
$testExitCode = require __DIR__ . '/run.php';
$coverage = xdebug_get_code_coverage();
xdebug_stop_code_coverage();

if ($testExitCode !== 0) {
    exit($testExitCode);
}

$covered = 0;
$executable = 0;
$fileRows = [];
foreach ($sourceFiles as $sourceFile) {
    $lineCoverage = $coverage[$sourceFile] ?? [];
    $fileCovered = count(array_filter($lineCoverage, static fn(int $status): bool => $status === 1));
    $fileExecutable = count(array_filter($lineCoverage, static fn(int $status): bool => $status === 1 || $status === -1));
    $covered += $fileCovered;
    $executable += $fileExecutable;
    $relative = substr($sourceFile, strlen($root) + 1);
    $percentage = $fileExecutable === 0 ? 100.0 : ($fileCovered / $fileExecutable) * 100;
    $fileRows[] = sprintf(' - %s: %d/%d (%.2f%%)', $relative, $fileCovered, $fileExecutable, $percentage);
}

$percentage = $executable === 0 ? 0.0 : ($covered / $executable) * 100;
printf("Backend line coverage: %d/%d executable lines (%.2f%%).\n", $covered, $executable, $percentage);
fwrite(STDOUT, implode("\n", $fileRows) . "\n");

$minimum = (float) $budgets['minimum_line_coverage_percentage'];
if ($mode === '--check' && $percentage < $minimum) {
    fwrite(STDERR, sprintf("Line coverage %.2f%% is below the %.2f%% minimum.\n", $percentage, $minimum));
    exit(1);
}

if ($mode === '--check') {
    printf("Coverage threshold passed (minimum: %.2f%%).\n", $minimum);
}

return 0;
