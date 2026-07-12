<?php

declare(strict_types=1);

use SoloChess\Repositories\GameRecord;
use SoloChess\Repositories\MoveRecord;
use SoloChess\Services\PgnExporter;

return static function (TestHarness $tests): void {
    $tests->test('pgn exporter writes standard headers and incomplete untimed movetext from canonical records', function () use ($tests): void {
        $pgn = (new PgnExporter())->export(
            pgnGameRecord(
                whiteLabel: 'Ada',
                blackLabel: 'Grace',
                status: 'active',
                result: null,
                timeControlJson: json_encode(['kind' => 'untimed', 'label' => 'Untimed']),
                createdAt: '2026-07-12 03:04:05',
            ),
            [
                pgnMoveRecord(1, 'e2', 'e4', 'e2e4', 'e4'),
                pgnMoveRecord(2, 'e7', 'e5', 'e7e5', 'e5'),
                pgnMoveRecord(3, 'g1', 'f3', 'g1f3', 'Nf3'),
            ],
        );

        $tests->assertSame(
            "[Event \"PHP Solo Chess Local Game\"]\n"
                . "[Site \"Local PHP Solo Chess\"]\n"
                . "[Date \"2026.07.12\"]\n"
                . "[Round \"-\"]\n"
                . "[White \"Ada\"]\n"
                . "[Black \"Grace\"]\n"
                . "[Result \"*\"]\n"
                . "[TimeControl \"-\"]\n"
                . "\n"
                . "1. e4 e5 2. Nf3 *\n",
            $pgn,
        );
    });

    $tests->test('pgn exporter writes completed timed results and special SAN from ordered move records', function () use ($tests): void {
        $pgn = (new PgnExporter())->export(
            pgnGameRecord(
                result: '1-0',
                timeControlJson: json_encode([
                    'kind' => 'preset',
                    'label' => '3+2',
                    'baseMilliseconds' => 180_000,
                    'incrementMilliseconds' => 2_000,
                ]),
                createdAt: '2026-07-11T23:00:00+00:00',
                completedAt: '2026-07-12T01:02:03+00:00',
            ),
            [
                pgnMoveRecord(1, 'e2', 'e4', 'e2e4', 'e4'),
                pgnMoveRecord(2, 'e7', 'e5', 'e7e5', 'e5'),
                pgnMoveRecord(3, 'g1', 'f3', 'g1f3', 'Nf3'),
                pgnMoveRecord(4, 'b8', 'c6', 'b8c6', 'Nc6'),
                pgnMoveRecord(5, 'e1', 'g1', 'e1g1', 'O-O'),
                pgnMoveRecord(6, 'd7', 'd5', 'd7d5', 'd5'),
                pgnMoveRecord(7, 'e5', 'd6', 'e5d6', 'exd6'),
                pgnMoveRecord(8, 'a7', 'a6', 'a7a6', 'a6'),
                pgnMoveRecord(9, 'e7', 'e8', 'e7e8q', 'e8=Q+'),
                pgnMoveRecord(10, 'a6', 'a5', 'a6a5', 'a5'),
                pgnMoveRecord(11, 'f3', 'f7', 'f3f7', 'Nxf7#'),
            ],
        );

        $tests->assertTrue(str_contains($pgn, "[Date \"2026.07.12\"]\n"));
        $tests->assertTrue(str_contains($pgn, "[Result \"1-0\"]\n"));
        $tests->assertTrue(str_contains($pgn, "[TimeControl \"180+2\"]\n"));
        $tests->assertTrue(str_ends_with(
            $pgn,
            "1. e4 e5 2. Nf3 Nc6 3. O-O d5 4. exd6 a6 5. e8=Q+ a5 6. Nxf7# 1-0\n",
        ));
    });

    $tests->test('pgn exporter escapes header text and exports draw results', function () use ($tests): void {
        $pgn = (new PgnExporter())->export(
            pgnGameRecord(
                whiteLabel: "Alice \"The First\"\nPlayer",
                blackLabel: 'Bob \\ Builder',
                result: '1/2-1/2',
                timeControlJson: json_encode([
                    'kind' => 'custom',
                    'label' => '7+3',
                    'baseMilliseconds' => 420_000,
                    'incrementMilliseconds' => 3_000,
                ]),
            ),
            [],
        );

        $tests->assertTrue(str_contains($pgn, "[White \"Alice \\\"The First\\\" Player\"]\n"));
        $tests->assertTrue(str_contains($pgn, "[Black \"Bob \\\\ Builder\"]\n"));
        $tests->assertTrue(str_contains($pgn, "[Result \"1/2-1/2\"]\n"));
        $tests->assertTrue(str_contains($pgn, "[TimeControl \"420+3\"]\n"));
        $tests->assertTrue(str_ends_with($pgn, "\n\n1/2-1/2\n"));
    });
};

function pgnGameRecord(
    string $whiteLabel = 'White',
    string $blackLabel = 'Black',
    string $status = 'finished',
    ?string $result = '*',
    ?string $timeControlJson = null,
    string $createdAt = '2026-07-12 00:00:00',
    ?string $completedAt = null,
): GameRecord {
    return new GameRecord(
        id: 1,
        ownerUserId: 1,
        whiteLabel: $whiteLabel,
        blackLabel: $blackLabel,
        whitePlayerType: 'local_human',
        blackPlayerType: 'local_human',
        status: $status,
        result: $result,
        terminationReason: $status === 'finished' ? 'checkmate' : null,
        timeControlJson: $timeControlJson,
        currentStateJson: '{"fen":"canonical"}',
        clockStateJson: null,
        createdAt: $createdAt,
        updatedAt: $createdAt,
        completedAt: $completedAt,
    );
}

function pgnMoveRecord(
    int $plyNumber,
    string $from,
    string $to,
    string $coordinate,
    string $san,
    ?string $promotion = null,
): MoveRecord {
    return new MoveRecord(
        id: $plyNumber,
        gameId: 1,
        plyNumber: $plyNumber,
        fromSquare: $from,
        toSquare: $to,
        promotion: $promotion,
        coordinate: $coordinate,
        san: $san,
        positionAfterFen: "fen-after-{$plyNumber}",
        stateAfterJson: null,
        whiteClockMs: null,
        blackClockMs: null,
        createdAt: '2026-07-12 00:00:00',
    );
}
