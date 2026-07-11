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

/**
 * @param array<int, array<int, string|null>> $board
 * @param array<string, mixed> $overrides
 */
function gameWithState(array $board, array $overrides = []): GameService
{
    $_SESSION = [
        'solo_chess_state' => array_merge([
            'board' => $board,
            'moveHistory' => [],
            'activeColor' => 'white',
            'kingInCheck' => null,
            'capturedWhite' => [],
            'capturedBlack' => [],
            'lastMessage' => 'Fixture ready.',
        ], $overrides),
    ];

    return new GameService(new SessionStore());
}

/** @return list<string> */
function legalMovesFrom(array $state, string $from): array
{
    return $state['legalMoves'][$from] ?? [];
}

return static function (TestHarness $tests): void {
    $tests->test('initial position generates deterministic ordinary pawn and knight moves only', function () use ($tests): void {
        $state = freshGame()->getSessionState();

        $tests->assertSame(['e4', 'e3'], legalMovesFrom($state, 'e2'));
        $tests->assertSame(['a3', 'c3'], legalMovesFrom($state, 'b1'));
        $tests->assertSame(['f3', 'h3'], legalMovesFrom($state, 'g1'));
        $tests->assertSame([], legalMovesFrom($state, 'a1'));
        $tests->assertSame([], legalMovesFrom($state, 'c1'));
        $tests->assertSame([], legalMovesFrom($state, 'e1'));
        $tests->assertSame(10, count($state['legalMoves']));
    });

    $tests->test('legal move generation includes captures and excludes blocked sliding destinations', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][4] = null;
        $board[7][0] = 'wk';
        $board[4][3] = 'wq'; // d4
        $board[4][6] = 'wn'; // g4 blocks east
        $board[2][3] = 'bn'; // d6 is capturable north
        $board[2][5] = 'bp'; // f6 is capturable diagonal
        $state = gameWithBoard($board)->getSessionState();

        $tests->assertSame([
            'a7',
            'b6',
            'd6',
            'f6',
            'c5',
            'd5',
            'e5',
            'a4',
            'b4',
            'c4',
            'e4',
            'f4',
            'c3',
            'd3',
            'e3',
            'b2',
            'd2',
            'f2',
            'd1',
            'g1',
        ], legalMovesFrom($state, 'd4'));
        $tests->assertSame(false, in_array('g4', legalMovesFrom($state, 'd4'), true));
        $tests->assertSame(false, in_array('h4', legalMovesFrom($state, 'd4'), true));
        $tests->assertSame(false, in_array('d7', legalMovesFrom($state, 'd4'), true));
    });

    $tests->test('legal move generation lists only the active side pieces', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[6][4] = 'wp';
        $board[1][4] = 'bp';
        $state = gameWithBoard($board, 'black')->getSessionState();

        $tests->assertSame([], legalMovesFrom($state, 'e2'));
        $tests->assertSame(['e6', 'e5'], legalMovesFrom($state, 'e7'));
    });

    $tests->test('legal move generation filters self-check and keeps check evasions', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[0][4] = 'br'; // e8 attacks the white king down the e-file
        $board[0][0] = 'bk';
        $board[6][4] = 'wr'; // e2 shields the white king
        $pinned = gameWithBoard($board)->getSessionState();

        $tests->assertSame(['e8', 'e7', 'e6', 'e5', 'e4', 'e3'], legalMovesFrom($pinned, 'e2'));
        $tests->assertSame(false, in_array('f2', legalMovesFrom($pinned, 'e2'), true));

        $board[6][4] = null;
        $checked = gameWithBoard($board, 'white', 'white')->getSessionState();

        $tests->assertSame(['d2', 'f2', 'd1', 'f1'], legalMovesFrom($checked, 'e1'));
    });

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

    $tests->test('ordinary moves never capture the opponent king', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][4] = null;
        $board[7][0] = 'wk';
        $board[6][4] = 'wr'; // e2 attacks e8 geometrically
        $state = gameWithBoard($board)->getSessionState();

        $tests->assertSame(false, in_array('e8', legalMovesFrom($state, 'e2'), true));

        $after = gameWithBoard($board)->submitMove(['from' => 'e2', 'to' => 'e8']);
        $tests->assertSame(false, $after['isValidMove']);
        $tests->assertSame('wr', $after['board'][6][4]);
        $tests->assertSame('bk', $after['board'][0][4]);
        $tests->assertSame([], $after['moveHistory']);
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

    $tests->test('queen-side castling moves the rook across the king for black', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[0][0] = 'br';
        $state = gameWithState($board, ['activeColor' => 'black'])->submitMove(['from' => 'e8', 'to' => 'c8']);

        $tests->assertSame('bk', $state['board'][0][2]);
        $tests->assertSame('br', $state['board'][0][3]);
        $tests->assertSame(null, $state['board'][0][4]);
        $tests->assertSame(null, $state['board'][0][0]);
        $tests->assertSame('white', $state['activeColor']);
        $tests->assertSame(['kingSide' => false, 'queenSide' => false], $state['castlingRights']['black']);
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

    $tests->test('castling consumes eligibility rights and rejects moved-piece rights', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][0] = 'wr';
        $board[7][7] = 'wr';

        $allowed = gameWithState($board)->getSessionState();
        $tests->assertSame(true, in_array('c1', legalMovesFrom($allowed, 'e1'), true));
        $tests->assertSame(true, in_array('g1', legalMovesFrom($allowed, 'e1'), true));

        $state = gameWithState($board, [
            'castlingRights' => [
                'white' => ['kingSide' => false, 'queenSide' => true],
                'black' => ['kingSide' => true, 'queenSide' => true],
            ],
        ])->submitMove(['from' => 'e1', 'to' => 'g1']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame('wk', $state['board'][7][4]);
        $tests->assertSame('wr', $state['board'][7][7]);
        $tests->assertSame([], $state['moveHistory']);
    });

    $tests->test('castling is rejected through check or while already checked', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[7][7] = 'wr';
        $board[0][5] = 'br'; // attacks f1, the traversed square
        $throughCheck = gameWithState($board)->submitMove(['from' => 'e1', 'to' => 'g1']);

        $tests->assertSame(false, $throughCheck['isValidMove']);
        $tests->assertSame('wk', $throughCheck['board'][7][4]);
        $tests->assertSame('wr', $throughCheck['board'][7][7]);

        $board = emptyBoardWithKings();
        $board[7][7] = 'wr';
        $board[0][4] = 'br'; // attacks e1 before castling starts
        $whileChecked = gameWithState($board, ['kingInCheck' => 'white'])->submitMove(['from' => 'e1', 'to' => 'g1']);

        $tests->assertSame(false, $whileChecked['isValidMove']);
        $tests->assertSame('wk', $whileChecked['board'][7][4]);
        $tests->assertSame([], $whileChecked['moveHistory']);
    });

    $tests->test('en passant is immediate and removes the captured pawn', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[3][4] = 'wp'; // e5
        $board[3][3] = 'bp'; // d5
        $state = gameWithState($board, [
            'enPassantTarget' => 'd6',
        ])->submitMove(['from' => 'e5', 'to' => 'd6']);

        $tests->assertSame('wp', $state['board'][2][3]);
        $tests->assertSame(null, $state['board'][3][4]);
        $tests->assertSame(null, $state['board'][3][3]);
        $tests->assertSame(null, $state['enPassantTarget']);
        $tests->assertSame(0, $state['halfmoveClock']);
        $tests->assertSame('e5', $state['moveHistory'][0]['from']);
        $tests->assertSame('d6', $state['moveHistory'][0]['to']);
        $tests->assertSame('black', $state['activeColor']);
    });

    $tests->test('en passant is rejected after any intervening move clears the target', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[3][4] = 'wp'; // e5
        $board[3][3] = 'bp'; // d5
        $state = gameWithState($board)->submitMove(['from' => 'e5', 'to' => 'd6']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame('wp', $state['board'][3][4]);
        $tests->assertSame('bp', $state['board'][3][3]);
        $tests->assertSame([], $state['moveHistory']);
    });

    $tests->test('en passant must follow the capturing pawn direction', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[3][4] = 'wp'; // e5
        $board[3][3] = 'bp'; // d5
        $state = gameWithState($board, [
            'enPassantTarget' => 'd4',
        ])->submitMove(['from' => 'e5', 'to' => 'd4']);

        $tests->assertSame(false, $state['isValidMove']);
        $tests->assertSame('wp', $state['board'][3][4]);
        $tests->assertSame('bp', $state['board'][3][3]);
        $tests->assertSame([], $state['moveHistory']);
    });

    $tests->test('promotion requires an explicit orthodox piece choice', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[1][0] = 'wp'; // a7

        $missing = gameWithState($board)->submitMove(['from' => 'a7', 'to' => 'a8']);
        $tests->assertSame(false, $missing['isValidMove']);
        $tests->assertSame('wp', $missing['board'][1][0]);
        $tests->assertSame(null, $missing['board'][0][0]);
        $tests->assertSame([], $missing['moveHistory']);

        $invalid = gameWithState($board)->submitMove(['from' => 'a7', 'to' => 'a8', 'promotion' => 'king']);
        $tests->assertSame(false, $invalid['isValidMove']);
        $tests->assertSame('wp', $invalid['board'][1][0]);
        $tests->assertSame(null, $invalid['board'][0][0]);
        $tests->assertSame([], $invalid['moveHistory']);
    });

    $tests->test('promotion applies queen rook bishop and knight choices', function () use ($tests): void {
        foreach (['queen' => 'wq', 'rook' => 'wr', 'bishop' => 'wb', 'knight' => 'wn'] as $choice => $piece) {
            $board = emptyBoardWithKings();
            $board[1][0] = 'wp'; // a7
            $state = gameWithState($board)->submitMove(['from' => 'a7', 'to' => 'a8', 'promotion' => $choice]);

            $tests->assertSame($piece, $state['board'][0][0], "Promotion to {$choice} should use {$piece}.");
            $tests->assertSame(null, $state['board'][1][0]);
            $tests->assertSame($choice, $state['moveHistory'][0]['promotion']);
            $tests->assertSame('black', $state['activeColor']);
        }
    });

    $tests->test('promotion can capture on the final rank', function () use ($tests): void {
        $board = emptyBoardWithKings();
        $board[1][0] = 'wp'; // a7
        $board[0][1] = 'bn'; // b8
        $state = gameWithState($board)->submitMove(['from' => 'a7', 'to' => 'b8', 'promotion' => 'knight']);

        $tests->assertSame('wn', $state['board'][0][1]);
        $tests->assertSame(null, $state['board'][1][0]);
        $tests->assertSame(0, $state['halfmoveClock']);
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
