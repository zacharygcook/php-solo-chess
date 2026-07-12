<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\MoveRepository;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\GamePersistenceService;
use SoloChess\Services\GameService;
use SoloChess\Services\PgnDownloadService;
use SoloChess\Services\PgnExporter;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('pgn controller exports owned saved games with download headers', function () use ($tests): void {
        [$games, $moves, $sessions, $gameId] = pgnControllerSavedGame();
        $downloads = new PgnDownloadService(new PgnExporter(), $sessions, $games, $moves);

        $result = $downloads->exportResult($gameId);
        $current = $downloads->exportResult(null);

        $tests->assertSame(200, $result['status']);
        $tests->assertSame(200, $current['status']);
        $tests->assertSame($result['body'], $current['body']);
        $tests->assertSame('application/x-chess-pgn; charset=UTF-8', $result['headers']['Content-Type']);
        $tests->assertSame(
            'attachment; filename="solo-chess-game-' . $gameId . '.pgn"',
            $result['headers']['Content-Disposition'],
        );
        $tests->assertTrue(str_contains($result['body'], "[Event \"PHP Solo Chess Local Game\"]\n"));
        $tests->assertTrue(str_contains($result['body'], "[White \"Owner White\"]\n"));
        $tests->assertTrue(str_contains($result['body'], "[Result \"1-0\"]\n"));
        $tests->assertTrue(str_ends_with($result['body'], "1. e4 1-0\n"));
    });

    $tests->test('pgn controller rejects unowned missing and unauthenticated saved exports', function () use ($tests): void {
        [$games, $moves, , $gameId, $otherId, $ownerId] = pgnControllerSavedGame();

        $_SESSION = [];
        $otherSessions = new SessionStore();
        $otherSessions->saveAuthenticatedUserId($otherId);
        $other = new PgnDownloadService(new PgnExporter(), $otherSessions, $games, $moves);
        $unowned = $other->exportResult($gameId);

        $_SESSION = [];
        $ownerSessions = new SessionStore();
        $ownerSessions->saveAuthenticatedUserId($ownerId);
        $owner = new PgnDownloadService(new PgnExporter(), $ownerSessions, $games, $moves);
        $missing = $owner->exportResult($gameId + 100);

        $_SESSION = [];
        $guest = new PgnDownloadService(new PgnExporter(), new SessionStore(), $games, $moves);

        $tests->assertSame(404, $unowned['status']);
        $tests->assertSame(404, $missing['status']);
        $tests->assertSame(401, $guest->exportResult($gameId)['status']);
    });

    $tests->test('pgn controller exports the active guest session without durable records', function () use ($tests): void {
        $_SESSION = [];
        $sessions = new SessionStore();
        $game = new GameService($sessions);
        $game->createGame(['whiteLabel' => 'Guest White', 'blackLabel' => 'Guest Black']);
        $game->submitMove(['from' => 'd2', 'to' => 'd4']);
        $downloads = new PgnDownloadService(
            new PgnExporter(),
            $sessions,
            null,
            null,
            static fn(): string => '2026-07-12T12:00:00+00:00',
        );

        $result = $downloads->exportResult(null);

        $tests->assertSame(200, $result['status']);
        $tests->assertSame('attachment; filename="solo-chess-guest.pgn"', $result['headers']['Content-Disposition']);
        $tests->assertTrue(str_contains($result['body'], "[White \"Guest White\"]\n"));
        $tests->assertTrue(str_contains($result['body'], "[Black \"Guest Black\"]\n"));
        $tests->assertTrue(str_contains($result['body'], "[Date \"2026.07.12\"]\n"));
        $tests->assertTrue(str_ends_with($result['body'], "1. d4 *\n"));
    });

    $tests->test('pgn controller does not export guest session state for authenticated users', function () use ($tests): void {
        $_SESSION = [];
        $sessions = new SessionStore();
        $sessions->saveAuthenticatedUserId(1);
        $sessions->saveState(['moveHistory' => [], 'fen' => 'guest']);
        $downloads = new PgnDownloadService(new PgnExporter(), $sessions);

        $result = $downloads->exportResult(null);

        $tests->assertSame(422, $result['status']);
        $tests->assertSame('application/json', $result['headers']['Content-Type']);
        $tests->assertTrue(str_contains($result['body'], 'Choose a saved game'));
    });
};

/**
 * @return array{0: GameRepository, 1: MoveRepository, 2: SessionStore, 3: int, 4: int, 5: int}
 */
function pgnControllerSavedGame(): array
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
    $game = new GameService(
        $sessions,
        new GamePersistenceService($games, $sessions),
        pgnControllerClock([1_000, 2_000, 3_000]),
    );

    $game->createGame(['whiteLabel' => 'Owner White', 'blackLabel' => 'Owner Black']);
    $game->submitMove(['from' => 'e2', 'to' => 'e4']);
    $game->resignGame(['actorColor' => 'black']);
    $gameId = $sessions->getCurrentGameId();
    if ($gameId === null) {
        throw new RuntimeException('Saved PGN test did not create a durable game.');
    }

    return [$games, $moves, $sessions, $gameId, $other->id, $owner->id];
}

/** @param list<int> $times */
function pgnControllerClock(array $times): Closure
{
    $index = 0;

    return static function () use ($times, &$index): int {
        $time = $times[$index] ?? $times[count($times) - 1];
        $index++;

        return $time;
    };
}
