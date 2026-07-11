<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/backend/src/Services/SessionStore.php';
require dirname(__DIR__) . '/backend/src/Services/GameService.php';

$tests = new TestHarness();

$testFiles = glob(__DIR__ . '/*Test.php');
if ($testFiles === false) {
    throw new RuntimeException('Unable to discover test files.');
}

sort($testFiles, SORT_STRING);
foreach ($testFiles as $testFile) {
    require $testFile;
}

if ($tests->count() === 0) {
    fwrite(STDERR, "No tests discovered. Name test files *Test.php.\n");
    exit(1);
}

exit($tests->run());
