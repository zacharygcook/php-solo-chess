<?php

declare(strict_types=1);

require __DIR__ . '/TestHarness.php';
require dirname(__DIR__) . '/backend/src/Services/SessionStore.php';
require dirname(__DIR__) . '/backend/src/Services/GameService.php';

$tests = new TestHarness();

require __DIR__ . '/GameServiceTest.php';

exit($tests->run());
