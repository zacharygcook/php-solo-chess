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
        'timed account journey persists clock snapshots timeout replay and pgn agreement',
        function () use ($tests): void {
            $journey = timedJourneySubject();

            $registered = $journey['auth']->register([
                'username' => 'timed_owner',
                'displayName' => 'Timed Owner',
                'password' => 'correct horse',
            ]);
            $created = $journey['game']->createGameResult([
                'whiteLabel' => 'Ada',
                'blackLabel' => 'Byron',
                'timeControl' => ['kind' => 'custom', 'baseMinutes' => 1, 'incrementSeconds' => 3],
            ]);
            $gameId = $journey['sessions']->getCurrentGameId();
            $ownerId = $registered['payload']['state']['user']['id'] ?? null;

            $tests->assertSame(201, $registered['status']);
            $tests->assertSame(201, $created['status']);
            $tests->assertTrue(is_int($ownerId), 'Registration should authenticate a durable user.');
            $tests->assertTrue($gameId !== null, 'Timed authenticated game should receive a durable id.');
            if (!is_int($ownerId) || $gameId === null) {
                throw new RuntimeException('Missing owner or durable game id.');
            }

            $journey['clock']->advance(5_000);
            $afterWhite = $journey['game']->submitMoveResult(['from' => 'e2', 'to' => 'e4']);
            $storedAfterWhite = $journey['games']->findByIdForOwner($gameId, $ownerId);
            $tests->assertSame(200, $afterWhite['status']);
            $tests->assertSame(true, $afterWhite['payload']['success']);
            $tests->assertSame(58_000, $afterWhite['payload']['state']['clockState']['whiteRemainingMilliseconds']);
            $tests->assertSame(60_000, $afterWhite['payload']['state']['clockState']['blackRemainingMilliseconds']);
            $tests->assertSame('black', $afterWhite['payload']['state']['clockState']['activeColor']);
            $tests->assertSame(58_000, $afterWhite['payload']['state']['moveHistory'][0]['whiteClockMilliseconds']);
            $tests->assertSame(60_000, $afterWhite['payload']['state']['moveHistory'][0]['blackClockMilliseconds']);
            $tests->assertSame(60_000, json_decode((string) $storedAfterWhite?->clockStateJson, true)['blackRemainingMilliseconds']);

            $journey['clock']->advance(2_000);
            $projected = $journey['game']->sessionStateResult();
            $storedAfterProjection = $journey['games']->findByIdForOwner($gameId, $ownerId);
            $tests->assertSame(58_000, $projected['payload']['state']['clockState']['blackRemainingMilliseconds']);
            $tests->assertSame(
                60_000,
                json_decode((string) $storedAfterProjection?->clockStateJson, true)['blackRemainingMilliseconds'],
                'Refresh projection must not persist a debit before an accepted move or timeout.',
            );

            $journey['clock']->advance(4_000);
            $afterBlack = $journey['game']->submitMoveResult(['from' => 'g8', 'to' => 'f6']);
            $tests->assertSame(200, $afterBlack['status']);
            $tests->assertSame(true, $afterBlack['payload']['success']);
            $tests->assertSame(58_000, $afterBlack['payload']['state']['clockState']['whiteRemainingMilliseconds']);
            $tests->assertSame(57_000, $afterBlack['payload']['state']['clockState']['blackRemainingMilliseconds']);
            $tests->assertSame('white', $afterBlack['payload']['state']['clockState']['activeColor']);
            $tests->assertSame(58_000, $afterBlack['payload']['state']['moveHistory'][1]['whiteClockMilliseconds']);
            $tests->assertSame(57_000, $afterBlack['payload']['state']['moveHistory'][1]['blackClockMilliseconds']);

            $beforeTimeoutFen = $afterBlack['payload']['state']['fen'];
            $journey['clock']->advance(70_000);
            $timeout = $journey['game']->sessionStateResult();
            $lateMove = $journey['game']->submitMoveResult(['from' => 'e4', 'to' => 'e5']);
            $storedGame = $journey['games']->findByIdForOwner($gameId, $ownerId);
            $storedMoves = $journey['moves']->listForGame($gameId);
            $tests->assertTrue($storedGame !== null, 'Timed terminal saved game should be stored.');
            if ($storedGame === null) {
                throw new RuntimeException('Missing stored game.');
            }

            $finalState = $timeout['payload']['state'];
            $tests->assertSame('finished', $finalState['gameStatus']);
            $tests->assertSame('0-1', $finalState['result']);
            $tests->assertSame('timeout', $finalState['terminationReason']);
            $tests->assertSame(0, $finalState['clockState']['whiteRemainingMilliseconds']);
            $tests->assertSame(57_000, $finalState['clockState']['blackRemainingMilliseconds']);
            $tests->assertSame($beforeTimeoutFen, $finalState['fen']);
            $tests->assertSame(false, $lateMove['payload']['success']);
            $tests->assertSame('Game is already finished.', $lateMove['payload']['message']);
            $tests->assertSame($finalState['fen'], $lateMove['payload']['state']['fen']);
            $tests->assertSame($finalState['clockState'], $lateMove['payload']['state']['clockState']);
            $tests->assertSame($finalState['moveHistory'], $lateMove['payload']['state']['moveHistory']);

            $tests->assertSame('finished', $storedGame->status);
            $tests->assertSame('0-1', $storedGame->result);
            $tests->assertSame('timeout', $storedGame->terminationReason);
            $tests->assertSame('Ada', $storedGame->whiteLabel);
            $tests->assertSame('Byron', $storedGame->blackLabel);
            $tests->assertSame(
                [
                    'kind' => 'custom',
                    'label' => '1+3',
                    'baseMilliseconds' => 60_000,
                    'incrementMilliseconds' => 3_000,
                ],
                json_decode((string) $storedGame->timeControlJson, true),
            );
            $tests->assertSame($finalState['clockState'], json_decode((string) $storedGame->clockStateJson, true));
            $tests->assertSame(['e2e4', 'g8f6'], array_map(
                static fn($move): string => $move->coordinate,
                $storedMoves,
            ));
            $tests->assertSame(['e4', 'Nf6'], array_map(
                static fn($move): string => $move->san,
                $storedMoves,
            ));
            $tests->assertSame([58_000, 58_000], array_map(
                static fn($move): ?int => $move->whiteClockMs,
                $storedMoves,
            ));
            $tests->assertSame([60_000, 57_000], array_map(
                static fn($move): ?int => $move->blackClockMs,
                $storedMoves,
            ));

            $history = $journey['history']->historyResult();
            $opened = $journey['history']->openResult($gameId);
            $replay = $journey['history']->replayResult($gameId);
            $verification = (new PgnVerifier())->verify($storedGame, $storedMoves);
            $export = $journey['downloads']->exportResult($gameId);

            $tests->assertSame(200, $history['status']);
            $tests->assertSame('1+3', $history['payload']['state']['games'][0]['timeControl']['label']);
            $tests->assertSame('0-1', $history['payload']['state']['games'][0]['result']);
            $tests->assertSame('timeout', $history['payload']['state']['games'][0]['terminationReason']);

            $tests->assertSame(200, $opened['status']);
            $tests->assertSame($finalState['fen'], $opened['payload']['state']['gameState']['fen']);
            $tests->assertSame($finalState['clockState'], $opened['payload']['state']['gameState']['clockState']);
            $tests->assertSame($finalState['fen'], $opened['payload']['state']['replay']['positions'][2]['fen']);
            $tests->assertSame([null, 58_000, 58_000], array_map(
                static fn(array $position): ?int => $position['whiteClockMilliseconds'],
                $opened['payload']['state']['replay']['positions'],
            ));
            $tests->assertSame([null, 60_000, 57_000], array_map(
                static fn(array $position): ?int => $position['blackClockMilliseconds'],
                $opened['payload']['state']['replay']['positions'],
            ));

            $tests->assertSame(200, $replay['status']);
            $tests->assertSame(false, array_key_exists('gameState', $replay['payload']['state']));
            $tests->assertSame($finalState['fen'], $replay['payload']['state']['replay']['positions'][2]['fen']);

            $tests->assertSame([], $verification->errors);
            $tests->assertTrue($verification->isValid(), 'Saved timed journey PGN coordinates should replay cleanly.');
            $tests->assertSame($finalState['fen'], $verification->finalFen);
            $tests->assertSame('0-1', $verification->result);
            $tests->assertSame(200, $export['status']);
            $tests->assertTrue(str_contains($export['body'], "[White \"Ada\"]\n"));
            $tests->assertTrue(str_contains($export['body'], "[Black \"Byron\"]\n"));
            $tests->assertTrue(str_contains($export['body'], "[Result \"0-1\"]\n"));
            $tests->assertTrue(str_contains($export['body'], "[TimeControl \"60+3\"]\n"));
            $tests->assertTrue(str_ends_with($export['body'], "1. e4 Nf6 0-1\n"));
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
 *     sessions: SessionStore,
 *     clock: TimedJourneyClock
 * }
 */
function timedJourneySubject(): array
{
    $_SESSION = [];
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);
    $users = new UserRepository($pdo);
    $games = new GameRepository($pdo);
    $moves = new MoveRepository($pdo);
    $sessions = new SessionStore();
    $clock = new TimedJourneyClock(10_000_000);
    $auth = new AuthController(new AuthSessionService(
        new AuthService($users),
        $users,
        $sessions,
        static function (): void {},
    ));
    $game = new GameController(new GameService($sessions, new GamePersistenceService($games, $sessions), $clock));
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
        'clock' => $clock,
    ];
}

final class TimedJourneyClock
{
    public function __construct(private int $milliseconds) {}

    public function __invoke(): int
    {
        return $this->milliseconds;
    }

    public function advance(int $milliseconds): void
    {
        $this->milliseconds += $milliseconds;
    }
}
