<?php

declare(strict_types=1);

require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/backend/src/Observability/RequestLogger.php';
require_once dirname(__DIR__) . '/backend/src/Services/SessionStore.php';
require_once dirname(__DIR__) . '/backend/src/Services/GameService.php';

$tests = new TestHarness();

$testFiles = glob(__DIR__ . '/*Test.php');
if ($testFiles === false) {
    throw new RuntimeException('Unable to discover test files.');
}

sort($testFiles, SORT_STRING);
foreach ($testFiles as $testFile) {
    $registerTests = require $testFile;
    if (!$registerTests instanceof Closure) {
        throw new RuntimeException("Test file must return a registration closure: {$testFile}");
    }
    $registerTests($tests);
}

if ($tests->count() === 0) {
    fwrite(STDERR, "No tests discovered. Name test files *Test.php.\n");
    exit(1);
}

$exitCode = $tests->run();
if (defined('SOLO_CHESS_COVERAGE')) {
    return $exitCode;
}

exit($exitCode);
