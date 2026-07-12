<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\MoveRepository;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\GamePersistenceService;
use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('logged in games save and reload canonical state with ordered notation', function () use ($tests): void {
        [$game, $games, $moves, $sessions, $ownerId] = gamePersistenceSubject();

        $state = $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $gameId = $sessions->getCurrentGameId();
        $sessions->clear();
        $reloaded = $game->getSessionState();

        $tests->assertTrue($gameId !== null, 'Authenticated play should create a durable game.');
        if ($gameId === null) {
            throw new RuntimeException('Authenticated play did not create a durable game.');
        }

        $storedFen = gamePersistenceDecodedFen($games, $gameId, $ownerId);
        $storedMoves = $moves->listForGame($gameId);

        $tests->assertSame($state['fen'], $reloaded['fen']);
        $tests->assertSame($state['moveHistory'], $reloaded['moveHistory']);
        $tests->assertSame($state['fen'], $storedFen);
        $tests->assertSame(['e2e4'], array_map(
            static fn($move): string => $move->coordinate,
            $storedMoves,
        ));
        $tests->assertSame('e4', $storedMoves[0]->san);
        $tests->assertSame($state['fen'], $storedMoves[0]->positionAfterFen);
    });

    $tests->test('owner isolation prevents a session from loading or mutating another users game', function () use ($tests): void {
        [$ownerGame, $games, $moves, $ownerSessions, $ownerId, $otherId] = gamePersistenceSubject();
        $ownerState = $ownerGame->submitMove(['from' => 'e2', 'to' => 'e4']);
        $ownerGameId = $ownerSessions->getCurrentGameId();
        if ($ownerGameId === null) {
            throw new RuntimeException('Owner game was not persisted.');
        }

        $_SESSION = [];
        $otherSessions = new SessionStore();
        $otherSessions->saveAuthenticatedUserId($otherId);
        $otherSessions->saveCurrentGameId($ownerGameId);
        $otherGame = new GameService(
            $otherSessions,
            new GamePersistenceService($games, $otherSessions),
        );
        $otherState = $otherGame->submitMove(['from' => 'd2', 'to' => 'd4']);
        $otherGameId = $otherSessions->getCurrentGameId();

        $tests->assertTrue($otherGameId !== null, 'Other user should receive their own durable game.');
        if ($otherGameId === null) {
            throw new RuntimeException('Other user game was not persisted.');
        }

        $tests->assertTrue($ownerGameId !== $otherGameId, 'Users should not share a current game id.');
        $tests->assertSame($ownerState['fen'], gamePersistenceDecodedFen($games, $ownerGameId, $ownerId));
        $tests->assertSame($otherState['fen'], gamePersistenceDecodedFen($games, $otherGameId, $otherId));
        $tests->assertSame(['e2e4'], array_map(
            static fn($move): string => $move->coordinate,
            $moves->listForGame($ownerGameId),
        ));
        $tests->assertSame(['d2d4'], array_map(
            static fn($move): string => $move->coordinate,
            $moves->listForGame($otherGameId),
        ));
    });

    $tests->test('guest games remain session backed without durable records', function () use ($tests): void {
        $_SESSION = [];
        $pdo = SqliteConnectionFactory::inMemory();
        DatabaseSchema::initialize($pdo);
        $sessions = new SessionStore();
        $games = new GameRepository($pdo);
        $moves = new MoveRepository($pdo);
        $game = new GameService($sessions, new GamePersistenceService($games, $sessions));

        $state = $game->submitMove(['from' => 'e2', 'to' => 'e4']);

        $tests->assertSame($state, $sessions->getState());
        $tests->assertSame([], $games->listForOwner(1));
        $tests->assertSame(null, $sessions->getCurrentGameId());
    });
};

/**
 * @return array{0: GameService, 1: GameRepository, 2: MoveRepository, 3: SessionStore, 4: int, 5: int}
 */
function gamePersistenceSubject(): array
{
    $_SESSION = [];
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);
    $users = new UserRepository($pdo);
    $owner = $users->create('Owner', 'owner', 'Owner', 'hash');
    $other = $users->create('Other', 'other', 'Other', 'hash');
    $games = new GameRepository($pdo);
    $moves = new MoveRepository($pdo);
    $sessions = new SessionStore();
    $sessions->saveAuthenticatedUserId($owner->id);
    $game = new GameService($sessions, new GamePersistenceService($games, $sessions));

    return [$game, $games, $moves, $sessions, $owner->id, $other->id];
}

function gamePersistenceDecodedFen(GameRepository $games, int $gameId, int $ownerId): ?string
{
    $record = $games->findByIdForOwner($gameId, $ownerId);
    if ($record === null) {
        return null;
    }

    $state = json_decode($record->currentStateJson, true);

    return is_array($state) && is_string($state['fen'] ?? null) ? $state['fen'] : null;
}
