<?php

declare(strict_types=1);

use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

/** @return array<int, array<int, string|null>> */
function emptyBoardWithKings(): array
{
    $board = array_fill(0, 8, array_fill(0, 8, null));
    $board[0][4] = 'bk';
    $board[7][4] = 'wk';

    return $board;
}

/** @param array<int, array<int, string|null>> $board */
function gameWithBoard(array $board, string $activeColor = 'white', ?string $kingInCheck = null): GameService
{
    $_SESSION = [
        'solo_chess_state' => [
            'board' => $board,
            'moveHistory' => [],
            'activeColor' => $activeColor,
            'kingInCheck' => $kingInCheck,
            'capturedWhite' => [],
            'capturedBlack' => [],
            'lastMessage' => 'Fixture ready.',
        ],
    ];

    return new GameService(new SessionStore());
}

return static function (TestHarness $tests): void {
    $tests->test('invalid destination coordinate is rejected without mutation', function () use ($tests): void {
        $game = freshGame();
        $before = $game->getSessionState();
        $after = $game->submitMove(['from' => 'e2', 'to' => 'z9']);

        $tests->assertSame(false, $after['isValidMove']);
        $tests->assertSame("Not a valid 'to' option", $after['lastMessage']);
        $tests->assertSame($before['board'], $after['board']);
        $tests->assertSame('white', $after['activeColor']);
        $tests->assertSame([], $after['moveHistory']);
    });

    $tests->test('sliding pieces move only on clear geometric paths', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[4][2] = 'wb'; // c4
        $board[3][3] = 'wp'; // d5 blocks f7
        $blocked = gameWithBoard($board)->submitMove(['from' => 'c4', 'to' => 'f7']);
        $tests->assertSame(false, $blocked['isValidMove']);
        $tests->assertSame('wb', $blocked['board'][4][2]);

        $board[3][3] = null;
        $legal = gameWithBoard($board)->submitMove(['from' => 'c4', 'to' => 'f7']);
        $tests->assertSame('wb', $legal['board'][1][5]);
        $tests->assertSame('black', $legal['activeColor']);
    });

    $tests->test('knights jump while rooks cannot move diagonally', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[4][3] = 'wn'; // d4
        $board[3][3] = 'wp';
        $knight = gameWithBoard($board)->submitMove(['from' => 'd4', 'to' => 'f5']);
        $tests->assertSame('wn', $knight['board'][3][5]);

        $board = emptyBoardWithKings();
        $board[4][3] = 'wr';
        $rook = gameWithBoard($board)->submitMove(['from' => 'd4', 'to' => 'e5']);
        $tests->assertSame(false, $rook['isValidMove']);
        $tests->assertSame('wr', $rook['board'][4][3]);
    });

    $tests->test('pieces capture enemies but never friendly pieces', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[4][2] = 'wb'; // c4
        $board[2][4] = 'bn'; // e6
        $capture = gameWithBoard($board)->submitMove(['from' => 'c4', 'to' => 'e6']);
        $tests->assertSame('wb', $capture['board'][2][4]);
        $tests->assertSame(null, $capture['board'][4][2]);

        $board[2][4] = 'wn';
        $friendly = gameWithBoard($board)->submitMove(['from' => 'c4', 'to' => 'e6']);
        $tests->assertSame(false, $friendly['isValidMove']);
        $tests->assertSame('wb', $friendly['board'][4][2]);
        $tests->assertSame('wn', $friendly['board'][2][4]);
    });

    $tests->test('pawns capture diagonally but cannot capture straight ahead', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[4][3] = 'wp'; // d4
        $board[3][4] = 'bn'; // e5
        $capture = gameWithBoard($board)->submitMove(['from' => 'd4', 'to' => 'e5']);
        $tests->assertSame('wp', $capture['board'][3][4]);

        $board[3][3] = 'bn'; // d5 blocks a forward move
        $blocked = gameWithBoard($board)->submitMove(['from' => 'd4', 'to' => 'd5']);
        $tests->assertSame(false, $blocked['isValidMove']);
        $tests->assertSame('wp', $blocked['board'][4][3]);
        $tests->assertSame('bn', $blocked['board'][3][3]);
    });

    $tests->test('queens share clear diagonal and straight movement', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[4][3] = 'wq'; // d4
        $diagonal = gameWithBoard($board)->submitMove(['from' => 'd4', 'to' => 'g7']);
        $tests->assertSame('wq', $diagonal['board'][1][6]);

        $straight = gameWithBoard($board)->submitMove(['from' => 'd4', 'to' => 'd7']);
        $tests->assertSame('wq', $straight['board'][1][3]);
    });

    $tests->test('a king cannot move onto an attacked square', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[0][4] = null;
        $board[0][0] = 'bk';
        $board[6][2] = 'br'; // c2 attacks d2
        $state = gameWithBoard($board)->submitMove(['from' => 'e1', 'to' => 'd2']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame('wk', $state['board'][7][4]);
        $tests->assertSame(null, $state['board'][6][3]);
    });

    $tests->test('clear-path castling moves the king and rook together', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][7] = 'wr';
        $state = gameWithBoard($board)->submitMove(['from' => 'e1', 'to' => 'g1']);

        $tests->assertSame('wk', $state['board'][7][6]);
        $tests->assertSame('wr', $state['board'][7][5]);
        $tests->assertSame(null, $state['board'][7][4]);
        $tests->assertSame(null, $state['board'][7][7]);
        $tests->assertSame('black', $state['activeColor']);
        $tests->assertSame(['kingSide' => false, 'queenSide' => false], $state['castlingRights']['white']);
    });

    $tests->test('rook movement and captures update castling eligibility state', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][0] = 'wr';
        $state = gameWithBoard($board)->submitMove(['from' => 'a1', 'to' => 'a2']);

        $tests->assertSame(false, $state['castlingRights']['white']['queenSide']);
        $tests->assertSame(true, $state['castlingRights']['white']['kingSide']);

        $board = emptyBoardWithKings();
        $board[7][0] = 'wr';
        $board[0][0] = 'br';
        $capture = gameWithBoard($board)->submitMove(['from' => 'a1', 'to' => 'a8']);

        $tests->assertSame(false, $capture['castlingRights']['black']['queenSide']);
        $tests->assertSame(true, $capture['castlingRights']['black']['kingSide']);
    });

    $tests->test('castling is rejected when its path is occupied', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][7] = 'wr';
        $board[7][5] = 'wb';
        $state = gameWithBoard($board)->submitMove(['from' => 'e1', 'to' => 'g1']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame('wk', $state['board'][7][4]);
        $tests->assertSame('wr', $state['board'][7][7]);
    });

    $tests->test('a move exposing the active king is rejected', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[0][4] = 'br'; // e8 attacks the white king down the e-file
        $board[0][0] = 'bk';
        $board[6][4] = 'wr'; // e2 currently shields the king
        $state = gameWithBoard($board)->submitMove(['from' => 'e2', 'to' => 'f2']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame('Move would leave white in check.', $state['lastMessage']);
        $tests->assertSame('wr', $state['board'][6][4]);
        $tests->assertSame(null, $state['board'][6][5]);
        $tests->assertSame('white', $state['activeColor']);
    });

    $tests->test('a king in check can make a legal escape move', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[0][4] = 'br';
        $board[0][0] = 'bk';
        $state = gameWithBoard($board, 'white', 'white')->submitMove(['from' => 'e1', 'to' => 'd1']);

        $tests->assertSame('wk', $state['board'][7][3]);
        $tests->assertSame(null, $state['board'][7][4]);
        $tests->assertSame('black', $state['activeColor']);
        $tests->assertSame(null, $state['kingInCheck']);
    });

    $tests->test('a line attack records check against the next player', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][4] = null;
        $board[7][0] = 'wk';
        $board[6][4] = 'wr'; // e2
        $state = gameWithBoard($board)->submitMove(['from' => 'e2', 'to' => 'e7']);

        $tests->assertSame('black', $state['activeColor']);
        $tests->assertSame('black', $state['kingInCheck']);
        $tests->assertSame('Check!', $state['lastMessage']);
    });

    $tests->test('a knight attack records check against the next player', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][4] = null;
        $board[7][0] = 'wk';
        $board[3][3] = 'wn'; // d5
        $state = gameWithBoard($board)->submitMove(['from' => 'd5', 'to' => 'f6']);

        $tests->assertSame('black', $state['kingInCheck']);
        $tests->assertSame('Check!', $state['lastMessage']);
    });
};
