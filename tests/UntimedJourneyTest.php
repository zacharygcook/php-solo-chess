<?php

declare(strict_types=1);

use SoloChess\Controllers\AuthController;
use SoloChess\Controllers\GameController;
use SoloChess\Controllers\GameHistoryController;
use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\MoveRepository;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\AuthService;
use SoloChess\Services\AuthSessionService;
use SoloChess\Services\GameHistoryService;
use SoloChess\Services\GamePersistenceService;
use SoloChess\Services\GameService;
use SoloChess\Services\PgnDownloadService;
use SoloChess\Services\PgnExporter;
use SoloChess\Services\PgnVerifier;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    if (defined('SOLO_CHESS_COVERAGE')) {
        return;
    }

    $tests->test(
        'untimed account journey persists terminal history replay pgn and owner isolation',
        function () use ($tests): void {
            $journey = untimedJourneySubject();

            $registered = $journey['auth']->register([
                'username' => 'mvp_owner',
                'displayName' => 'MVP Owner',
                'password' => 'correct horse',
            ]);
            $created = $journey['game']->createGameResult([
                'whiteLabel' => 'Ada',
                'blackLabel' => 'Byron',
            ]);
            $states = [];
            foreach ([['f2', 'f3'], ['e7', 'e5'], ['g2', 'g4'], ['d8', 'h4']] as $move) {
                $states[] = $journey['game']->submitMoveResult(['from' => $move[0], 'to' => $move[1]]);
            }

            $gameId = $journey['sessions']->getCurrentGameId();
            $ownerId = $registered['payload']['state']['user']['id'] ?? null;
            $tests->assertSame(201, $registered['status']);
            $tests->assertSame(201, $created['status']);
            $tests->assertTrue(is_int($ownerId), 'Registration should authenticate a durable user.');
            $tests->assertTrue($gameId !== null, 'Untimed authenticated game should receive a durable id.');
            if (!is_int($ownerId) || $gameId === null) {
                throw new RuntimeException('Missing owner or durable game id.');
            }

            foreach ($states as $state) {
                $tests->assertSame(200, $state['status']);
                $tests->assertSame(true, $state['payload']['success']);
            }

            $finalState = $states[3]['payload']['state'];
            $storedGame = $journey['games']->findByIdForOwner($gameId, $ownerId);
            $storedMoves = $journey['moves']->listForGame($gameId);
            $tests->assertTrue($storedGame !== null, 'Terminal saved game should be stored for its owner.');
            if ($storedGame === null) {
                throw new RuntimeException('Missing stored game.');
            }

            $tests->assertSame('finished', $finalState['gameStatus']);
            $tests->assertSame('0-1', $finalState['result']);
            $tests->assertSame('checkmate', $finalState['terminationReason']);
            $tests->assertSame('finished', $storedGame->status);
            $tests->assertSame('0-1', $storedGame->result);
            $tests->assertSame('checkmate', $storedGame->terminationReason);
            $tests->assertSame('Ada', $storedGame->whiteLabel);
            $tests->assertSame('Byron', $storedGame->blackLabel);
            $tests->assertSame(
                [
                    'kind' => 'untimed',
                    'label' => 'Untimed',
                    'baseMilliseconds' => null,
                    'incrementMilliseconds' => 0,
                ],
                json_decode((string) $storedGame->timeControlJson, true),
            );
            $tests->assertSame(['f2f3', 'e7e5', 'g2g4', 'd8h4'], array_map(
                static fn($move): string => $move->coordinate,
                $storedMoves,
            ));
            $tests->assertSame(['f3', 'e5', 'g4', 'Qh4#'], array_map(
                static fn($move): string => $move->san,
                $storedMoves,
            ));

            $history = $journey['history']->historyResult();
            $opened = $journey['history']->openResult($gameId);
            $replay = $journey['history']->replayResult($gameId);
            $verification = (new PgnVerifier())->verify($storedGame, $storedMoves);
            $export = $journey['downloads']->exportResult($gameId);

            $tests->assertSame(200, $history['status']);
            $tests->assertSame(1, count($history['payload']['state']['games']));
            $tests->assertSame($gameId, $history['payload']['state']['games'][0]['id']);
            $tests->assertSame('Ada', $history['payload']['state']['games'][0]['whiteLabel']);
            $tests->assertSame('Byron', $history['payload']['state']['games'][0]['blackLabel']);
            $tests->assertSame('0-1', $history['payload']['state']['games'][0]['result']);
            $tests->assertSame('checkmate', $history['payload']['state']['games'][0]['terminationReason']);

            $tests->assertSame(200, $opened['status']);
            $tests->assertSame($finalState['fen'], $opened['payload']['state']['gameState']['fen']);
            $tests->assertSame($finalState['fen'], $opened['payload']['state']['replay']['positions'][4]['fen']);
            $tests->assertSame(['initial', 'f2f3', 'e7e5', 'g2g4', 'd8h4'], array_map(
                static fn(array $position): string => (string) $position['coordinate'],
                $opened['payload']['state']['replay']['positions'],
            ));
            $tests->assertSame(['f2f3', 'e7e5', 'g2g4', 'd8h4'], array_map(
                static fn(array $move): string => (string) $move['coordinate'],
                $opened['payload']['state']['replay']['moves'],
            ));

            $tests->assertSame(200, $replay['status']);
            $tests->assertSame(false, array_key_exists('gameState', $replay['payload']['state']));
            $tests->assertSame($finalState['fen'], $replay['payload']['state']['replay']['positions'][4]['fen']);

            $tests->assertSame([], $verification->errors);
            $tests->assertTrue($verification->isValid(), 'Saved untimed journey PGN should replay cleanly.');
            $tests->assertSame($finalState['fen'], $verification->finalFen);
            $tests->assertSame('0-1', $verification->result);
            $tests->assertSame(200, $export['status']);
            $tests->assertSame('application/x-chess-pgn; charset=UTF-8', $export['headers']['Content-Type']);
            $tests->assertTrue(str_contains($export['body'], "[White \"Ada\"]\n"));
            $tests->assertTrue(str_contains($export['body'], "[Black \"Byron\"]\n"));
            $tests->assertTrue(str_contains($export['body'], "[Result \"0-1\"]\n"));
            $tests->assertTrue(str_ends_with($export['body'], "1. f3 e5 2. g4 Qh4# 0-1\n"));

            $journey['auth']->logout();
            $other = $journey['auth']->register([
                'username' => 'mvp_other',
                'displayName' => 'MVP Other',
                'password' => 'correct horse',
            ]);
            $tests->assertSame(201, $other['status']);
            $tests->assertSame([], $journey['history']->historyResult()['payload']['state']['games']);
            $tests->assertSame(404, $journey['history']->openResult($gameId)['status']);
            $tests->assertSame(404, $journey['history']->replayResult($gameId)['status']);
            $tests->assertSame(404, $journey['downloads']->exportResult($gameId)['status']);
            $tests->assertSame(404, $journey['downloads']->exportResult(null)['status']);

            $journey['auth']->logout();
            $loggedIn = $journey['auth']->login([
                'username' => 'mvp_owner',
                'password' => 'correct horse',
            ]);
            $tests->assertSame(200, $loggedIn['status']);
            $tests->assertSame($gameId, $journey['history']->historyResult()['payload']['state']['games'][0]['id']);
            $tests->assertSame(200, $journey['downloads']->exportResult($gameId)['status']);
        },
    );
};

/**
 * @return array{
 *     auth: AuthController,
 *     game: GameController,
 *     history: GameHistoryController,
 *     downloads: PgnDownloadService,
 *     games: GameRepository,
 *     moves: MoveRepository,
 *     sessions: SessionStore
 * }
 */
function untimedJourneySubject(): array
{
    $_SESSION = [];
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);
    $users = new UserRepository($pdo);
    $games = new GameRepository($pdo);
    $moves = new MoveRepository($pdo);
    $sessions = new SessionStore();
    $auth = new AuthController(new AuthSessionService(
        new AuthService($users),
        $users,
        $sessions,
        static function (): void {},
    ));
    $game = new GameController(new GameService($sessions, new GamePersistenceService($games, $sessions)));
    $history = new GameHistoryController(new GameHistoryService($games, $moves, $sessions), $sessions);
    $downloads = new PgnDownloadService(new PgnExporter(), $sessions, $games, $moves);

    return [
        'auth' => $auth,
        'game' => $game,
        'history' => $history,
        'downloads' => $downloads,
        'games' => $games,
        'moves' => $moves,
        'sessions' => $sessions,
    ];
}
