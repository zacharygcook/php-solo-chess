<?php

declare(strict_types=1);

use SoloChess\Observability\RequestLogger;

return static function (TestHarness $tests): void {
    $tests->test('request logger preserves safe request identifiers', function () use ($tests): void {
        $tests->assertSame('readiness-1234', RequestLogger::requestId('readiness-1234'));
        $tests->assertSame(16, strlen(RequestLogger::requestId('unsafe value')));
    });

    $tests->test('request logger emits an allowlisted JSON completion record', function () use ($tests): void {
        $record = RequestLogger::completionRecord(
            'readiness-1234',
            hrtime(true),
            422,
            'POST',
            '/backend/public/api/move.php',
        );
        $decoded = json_decode(RequestLogger::encode($record), true, flags: JSON_THROW_ON_ERROR);

        $tests->assertSame('warning', $decoded['level']);
        $tests->assertSame('http.request.completed', $decoded['event']);
        $tests->assertSame('readiness-1234', $decoded['request_id']);
        $tests->assertSame('/backend/public/api/move.php', $decoded['path']);
        $tests->assertSame(false, array_key_exists('body', $decoded));
        $tests->assertSame(false, array_key_exists('headers', $decoded));
        $tests->assertSame(false, array_key_exists('query', $decoded));
    });
};
