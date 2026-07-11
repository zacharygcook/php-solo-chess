<?php

declare(strict_types=1);

use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

/** @return GameService */
function freshGame(): GameService
{
    $_SESSION = [];

    return new GameService(new SessionStore());
}

return static function (TestHarness $tests): void {
    $tests->test('new game has a complete standard position', function () use ($tests): void {
        $state = freshGame()->getSessionState();

        $tests->assertSame('white', $state['activeColor']);
        $tests->assertSame('br', $state['board'][0][0]);
        $tests->assertSame('bk', $state['board'][0][4]);
        $tests->assertSame('wk', $state['board'][7][4]);
        $tests->assertSame('wr', $state['board'][7][7]);
        $tests->assertSame(32, count(array_filter(array_merge(...$state['board']))));
        $tests->assertSame([], $state['moveHistory']);
        foreach ($state['board'] as $row) {
            $tests->assertSame(8, count($row));
        }
        $tests->assertSame(8, count($state['board']));
    });

    $tests->test('new game exposes serializable explicit rules state', function () use ($tests): void {
        $state = freshGame()->getSessionState();

        $tests->assertSame([
            'white' => ['kingSide' => true, 'queenSide' => true],
            'black' => ['kingSide' => true, 'queenSide' => true],
        ], $state['castlingRights']);
        $tests->assertSame(null, $state['enPassantTarget']);
        $tests->assertSame(0, $state['halfmoveClock']);
        $tests->assertSame(1, $state['fullmoveNumber']);
        $tests->assertSame(1, count($state['positionHistory']));
        $tests->assertSame($state, unserialize(serialize($state)));
    });

    $tests->test('legal pawn double-step updates board, turn, and history', function () use ($tests): void {
        $state = freshGame()->submitMove(['from' => 'e2', 'to' => 'e4']);

        $tests->assertSame(null, $state['board'][6][4]);
        $tests->assertSame('wp', $state['board'][4][4]);
        $tests->assertSame('black', $state['activeColor']);
        $tests->assertSame('e2', $state['moveHistory'][0]['from']);
        $tests->assertSame('e4', $state['moveHistory'][0]['to']);
        $tests->assertSame('e3', $state['enPassantTarget']);
        $tests->assertSame(0, $state['halfmoveClock']);
        $tests->assertSame(1, $state['fullmoveNumber']);
        $tests->assertSame(2, count($state['positionHistory']));
    });

    $tests->test('ordinary black move clears en passant target and advances fullmove state', function () use ($tests): void {
        $game = freshGame();
        $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $state = $game->submitMove(['from' => 'g8', 'to' => 'f6']);

        $tests->assertSame(null, $state['enPassantTarget']);
        $tests->assertSame(1, $state['halfmoveClock']);
        $tests->assertSame(2, $state['fullmoveNumber']);
        $tests->assertSame(3, count($state['positionHistory']));
    });

    $tests->test('invalid coordinate is rejected without mutating the game', function () use ($tests): void {
        $game = freshGame();
        $before = $game->getSessionState();
        $after = $game->submitMove(['from' => 'z9', 'to' => 'e4']);

        $tests->assertSame(false, $after['isValidMove']);
        $tests->assertSame("Not a valid 'from' option", $after['lastMessage']);
        $tests->assertSame($before['board'], $after['board']);
        $tests->assertSame([], $after['moveHistory']);
        $tests->assertSame($before['activeColor'], $after['activeColor']);
        $tests->assertSame($before['castlingRights'], $after['castlingRights']);
        $tests->assertSame($before['enPassantTarget'], $after['enPassantTarget']);
        $tests->assertSame($before['halfmoveClock'], $after['halfmoveClock']);
        $tests->assertSame($before['fullmoveNumber'], $after['fullmoveNumber']);
        $tests->assertSame($before['positionHistory'], $after['positionHistory']);
    });

    $tests->test('player cannot move twice in succession', function () use ($tests): void {
        $game = freshGame();
        $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $state = $game->submitMove(['from' => 'd2', 'to' => 'd4']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame("It's not white's turn.", $state['lastMessage']);
        $tests->assertSame('wp', $state['board'][6][3]);
        $tests->assertSame(1, count($state['moveHistory']));
    });

    $tests->test('reset restores the initial position after a move', function () use ($tests): void {
        $game = freshGame();
        $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $state = $game->resetGame();

        $tests->assertSame('white', $state['activeColor']);
        $tests->assertSame('wp', $state['board'][6][4]);
        $tests->assertSame(null, $state['board'][4][4]);
        $tests->assertSame([], $state['moveHistory']);
    });
};
