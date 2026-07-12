<?php

declare(strict_types=1);

use SoloChess\Repositories\GameRecord;
use SoloChess\Repositories\MoveRecord;
use SoloChess\Services\PgnExporter;
use SoloChess\Services\PgnVerifier;

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

    if (defined('SOLO_CHESS_COVERAGE')) {
        return;
    }

    $tests->test('pgn verifier replays ordinary canonical records through the move service', function () use ($tests): void {
        [$game, $moves] = pgnStaticFixture(null, 'rnbqkbnr/pppp1ppp/8/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R b KQkq - 1 2', [
            ['e2', 'e4', null, 'e2e4', 'e4', 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1'],
            ['e7', 'e5', null, 'e7e5', 'e5', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2'],
            ['g1', 'f3', null, 'g1f3', 'Nf3', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R b KQkq - 1 2'],
        ]);

        $result = (new PgnVerifier())->verify($game, $moves);

        $tests->assertSame([], $result->errors);
        $tests->assertTrue($result->isValid());
        $tests->assertSame(3, $result->moveCount);
        $tests->assertSame('rnbqkbnr/pppp1ppp/8/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R b KQkq - 1 2', $result->finalFen);
        $tests->assertSame('*', $result->result);
    });

    $tests->test('pgn verifier accepts castling en passant promotion checkmate and drawn canonical records', function () use ($tests): void {
        foreach (pgnSpecialVerificationFixtures() as [$game, $moves]) {
            $result = (new PgnVerifier())->verify($game, $moves);
            $tests->assertSame([], $result->errors);
            $tests->assertTrue($result->isValid());
        }
    });

    $tests->test('pgn verifier reports corrupt canonical records with actionable errors', function () use ($tests): void {
        [$game, $moves] = pgnStaticFixture(null, 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2', [
            ['e2', 'e4', null, 'e2e4', 'e4', 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1'],
            ['e7', 'e5', null, 'e7e5', 'e5', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2'],
        ]);

        $corruptSan = pgnMoveRecordFrom($moves[1], san: 'Nc6');
        $badSan = (new PgnVerifier())->verify($game, [$moves[0], $corruptSan]);
        $tests->assertSame(false, $badSan->isValid());
        $tests->assertTrue(str_contains(implode("\n", $badSan->errors), 'Ply 2 san mismatch'));

        $badFenGame = pgnGameRecordFrom($game, currentStateJson: '{"fen":"corrupt","moveHistory":[{},{}],"result":null}');
        $badFen = (new PgnVerifier())->verify($badFenGame, $moves);
        $tests->assertSame(false, $badFen->isValid());
        $tests->assertTrue(str_contains(implode("\n", $badFen->errors), 'Final FEN mismatch'));

        $illegalMove = pgnMoveRecordFrom($moves[1], toSquare: 'e4', coordinate: 'e7e4');
        $illegal = (new PgnVerifier())->verify($game, [$moves[0], $illegalMove]);
        $tests->assertSame(false, $illegal->isValid());
        $tests->assertTrue(str_contains(implode("\n", $illegal->errors), 'Ply 2 e7e4 was rejected'));

        $badResultGame = pgnGameRecordFrom($game, result: '1-0');
        $badResult = (new PgnVerifier())->verify($badResultGame, $moves);
        $tests->assertSame(false, $badResult->isValid());
        $tests->assertTrue(str_contains(implode("\n", $badResult->errors), 'Result mismatch'));
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

/**
 * @param list<array{0: string, 1: string, 2: ?string, 3: string, 4: string, 5: string}> $moves
 * @return array{GameRecord, list<MoveRecord>}
 */
function pgnStaticFixture(?string $result, string $finalFen, array $moves): array
{
    $records = [];
    foreach ($moves as $index => $move) {
        $records[] = pgnMoveRecord(
            $index + 1,
            $move[0],
            $move[1],
            $move[3],
            $move[4],
            $move[2],
        );
        $records[$index] = pgnMoveRecordFrom($records[$index], positionAfterFen: $move[5]);
    }

    $game = pgnGameRecord(
        status: $result === null ? 'active' : 'finished',
        result: $result,
        createdAt: '2026-07-12 00:00:00',
    );
    $state = [
        'fen' => $finalFen,
        'moveHistory' => array_fill(0, count($moves), []),
        'result' => $result,
    ];

    return [pgnGameRecordFrom($game, currentStateJson: json_encode($state, JSON_THROW_ON_ERROR)), $records];
}

/**
 * @return list<array{GameRecord, list<MoveRecord>}>
 */
function pgnSpecialVerificationFixtures(): array
{
    return [
        pgnStaticFixture(null, 'r1bqkb1r/pppp1ppp/2n2n2/4p3/2B1P3/5N2/PPPP1PPP/RNBQ1RK1 b kq - 5 4', [
            ['e2', 'e4', null, 'e2e4', 'e4', 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1'],
            ['e7', 'e5', null, 'e7e5', 'e5', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2'],
            ['g1', 'f3', null, 'g1f3', 'Nf3', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R b KQkq - 1 2'],
            ['b8', 'c6', null, 'b8c6', 'Nc6', 'r1bqkbnr/pppp1ppp/2n5/4p3/4P3/5N2/PPPP1PPP/RNBQKB1R w KQkq - 2 3'],
            ['f1', 'c4', null, 'f1c4', 'Bc4', 'r1bqkbnr/pppp1ppp/2n5/4p3/2B1P3/5N2/PPPP1PPP/RNBQK2R b KQkq - 3 3'],
            ['g8', 'f6', null, 'g8f6', 'Nf6', 'r1bqkb1r/pppp1ppp/2n2n2/4p3/2B1P3/5N2/PPPP1PPP/RNBQK2R w KQkq - 4 4'],
            ['e1', 'g1', null, 'e1g1', 'O-O', 'r1bqkb1r/pppp1ppp/2n2n2/4p3/2B1P3/5N2/PPPP1PPP/RNBQ1RK1 b kq - 5 4'],
        ]),
        pgnStaticFixture(null, 'rnbqkbnr/1pp1pppp/p2P4/8/8/8/PPPP1PPP/RNBQKBNR b KQkq - 0 3', [
            ['e2', 'e4', null, 'e2e4', 'e4', 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1'],
            ['a7', 'a6', null, 'a7a6', 'a6', 'rnbqkbnr/1ppppppp/p7/8/4P3/8/PPPP1PPP/RNBQKBNR w KQkq - 0 2'],
            ['e4', 'e5', null, 'e4e5', 'e5', 'rnbqkbnr/1ppppppp/p7/4P3/8/8/PPPP1PPP/RNBQKBNR b KQkq - 0 2'],
            ['d7', 'd5', null, 'd7d5', 'd5', 'rnbqkbnr/1pp1pppp/p7/3pP3/8/8/PPPP1PPP/RNBQKBNR w KQkq d6 0 3'],
            ['e5', 'd6', null, 'e5d6', 'exd6', 'rnbqkbnr/1pp1pppp/p2P4/8/8/8/PPPP1PPP/RNBQKBNR b KQkq - 0 3'],
        ]),
        pgnStaticFixture(null, 'Qnbqkbnr/p1ppppp1/8/8/8/8/1PPPPPpP/RNBQKBNR b KQk - 0 5', [
            ['a2', 'a4', null, 'a2a4', 'a4', 'rnbqkbnr/pppppppp/8/8/P7/8/1PPPPPPP/RNBQKBNR b KQkq a3 0 1'],
            ['h7', 'h5', null, 'h7h5', 'h5', 'rnbqkbnr/ppppppp1/8/7p/P7/8/1PPPPPPP/RNBQKBNR w KQkq h6 0 2'],
            ['a4', 'a5', null, 'a4a5', 'a5', 'rnbqkbnr/ppppppp1/8/P6p/8/8/1PPPPPPP/RNBQKBNR b KQkq - 0 2'],
            ['h5', 'h4', null, 'h5h4', 'h4', 'rnbqkbnr/ppppppp1/8/P7/7p/8/1PPPPPPP/RNBQKBNR w KQkq - 0 3'],
            ['a5', 'a6', null, 'a5a6', 'a6', 'rnbqkbnr/ppppppp1/P7/8/7p/8/1PPPPPPP/RNBQKBNR b KQkq - 0 3'],
            ['h4', 'h3', null, 'h4h3', 'h3', 'rnbqkbnr/ppppppp1/P7/8/8/7p/1PPPPPPP/RNBQKBNR w KQkq - 0 4'],
            ['a6', 'b7', null, 'a6b7', 'axb7', 'rnbqkbnr/pPppppp1/8/8/8/7p/1PPPPPPP/RNBQKBNR b KQkq - 0 4'],
            ['h3', 'g2', null, 'h3g2', 'hxg2', 'rnbqkbnr/pPppppp1/8/8/8/8/1PPPPPpP/RNBQKBNR w KQkq - 0 5'],
            ['b7', 'a8', 'queen', 'b7a8q', 'bxa8=Q', 'Qnbqkbnr/p1ppppp1/8/8/8/8/1PPPPPpP/RNBQKBNR b KQk - 0 5'],
        ]),
        pgnStaticFixture('0-1', 'rnb1kbnr/pppp1ppp/8/4p3/6Pq/5P2/PPPPP2P/RNBQKBNR w KQkq - 1 3', [
            ['f2', 'f3', null, 'f2f3', 'f3', 'rnbqkbnr/pppppppp/8/8/8/5P2/PPPPP1PP/RNBQKBNR b KQkq - 0 1'],
            ['e7', 'e5', null, 'e7e5', 'e5', 'rnbqkbnr/pppp1ppp/8/4p3/8/5P2/PPPPP1PP/RNBQKBNR w KQkq e6 0 2'],
            ['g2', 'g4', null, 'g2g4', 'g4', 'rnbqkbnr/pppp1ppp/8/4p3/6P1/5P2/PPPPP2P/RNBQKBNR b KQkq g3 0 2'],
            ['d8', 'h4', null, 'd8h4', 'Qh4#', 'rnb1kbnr/pppp1ppp/8/4p3/6Pq/5P2/PPPPP2P/RNBQKBNR w KQkq - 1 3'],
        ]),
        pgnStaticFixture('1/2-1/2', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2', [
            ['e2', 'e4', null, 'e2e4', 'e4', 'rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1'],
            ['e7', 'e5', null, 'e7e5', 'e5', 'rnbqkbnr/pppp1ppp/8/4p3/4P3/8/PPPP1PPP/RNBQKBNR w KQkq e6 0 2'],
        ]),
    ];
}

function pgnGameRecordFrom(
    GameRecord $game,
    ?string $result = null,
    ?string $currentStateJson = null,
): GameRecord {
    return new GameRecord(
        id: $game->id,
        ownerUserId: $game->ownerUserId,
        whiteLabel: $game->whiteLabel,
        blackLabel: $game->blackLabel,
        whitePlayerType: $game->whitePlayerType,
        blackPlayerType: $game->blackPlayerType,
        status: $game->status,
        result: $result ?? $game->result,
        terminationReason: $game->terminationReason,
        timeControlJson: $game->timeControlJson,
        currentStateJson: $currentStateJson ?? $game->currentStateJson,
        clockStateJson: $game->clockStateJson,
        createdAt: $game->createdAt,
        updatedAt: $game->updatedAt,
        completedAt: $game->completedAt,
    );
}

function pgnMoveRecordFrom(
    MoveRecord $move,
    ?string $toSquare = null,
    ?string $coordinate = null,
    ?string $san = null,
    ?string $positionAfterFen = null,
): MoveRecord {
    return new MoveRecord(
        id: $move->id,
        gameId: $move->gameId,
        plyNumber: $move->plyNumber,
        fromSquare: $move->fromSquare,
        toSquare: $toSquare ?? $move->toSquare,
        promotion: $move->promotion,
        coordinate: $coordinate ?? $move->coordinate,
        san: $san ?? $move->san,
        positionAfterFen: $positionAfterFen ?? $move->positionAfterFen,
        stateAfterJson: $move->stateAfterJson,
        whiteClockMs: $move->whiteClockMs,
        blackClockMs: $move->blackClockMs,
        createdAt: $move->createdAt,
    );
}
