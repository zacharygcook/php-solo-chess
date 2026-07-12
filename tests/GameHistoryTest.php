<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\MoveRepository;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\GameHistoryService;
use SoloChess\Services\GamePersistenceService;
use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('personal history lists only the active owner with lifecycle metadata', function () use ($tests): void {
        [$game, $history, $games, $moves, $sessions, $ownerId, $otherId] = gameHistorySubject([1_000, 4_000, 7_000]);

        $game->createGame([
            'whiteLabel' => 'Ada',
            'blackLabel' => 'Byron',
            'timeControl' => ['kind' => 'preset', 'preset' => '1+0'],
        ]);
        $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $finished = $game->resignGame(['actorColor' => 'black']);
        $gameId = $sessions->getCurrentGameId();

        $tests->assertTrue($gameId !== null, 'Authenticated game should have a durable id.');
        if ($gameId === null) {
            throw new RuntimeException('Missing durable game id.');
        }

        $record = $games->findByIdForOwner($gameId, $ownerId);
        $storedMove = $moves->listForGame($gameId)[0] ?? null;
        $items = $history->listForAuthenticatedUser();

        $tests->assertTrue($record !== null, 'Finished game should be stored for the owner.');
        $tests->assertTrue($storedMove !== null, 'Accepted move should be stored.');
        if ($record === null || $storedMove === null) {
            throw new RuntimeException('Missing saved game evidence.');
        }

        $tests->assertSame('finished', $record->status);
        $tests->assertSame('1-0', $record->result);
        $tests->assertSame('resignation', $record->terminationReason);
        $tests->assertSame('1970-01-01T00:00:07+00:00', $record->completedAt);
        $tests->assertSame($finished['clockState'], json_decode((string) $record->clockStateJson, true));
        $tests->assertSame(54_000, $storedMove->whiteClockMs);
        $tests->assertSame(60_000, $storedMove->blackClockMs);

        $tests->assertSame(1, count($items));
        $tests->assertSame($gameId, $items[0]['id']);
        $tests->assertSame('Ada', $items[0]['whiteLabel']);
        $tests->assertSame('Byron', $items[0]['blackLabel']);
        $tests->assertSame('1-0', $items[0]['result']);
        $tests->assertSame('resignation', $items[0]['completionReason']);
        $tests->assertSame('1970-01-01T00:00:07+00:00', $items[0]['completedAt']);
        $tests->assertSame('1970-01-01T00:00:07+00:00', $items[0]['date']);
        $tests->assertSame('1+0', $items[0]['timeControl']['label']);

        $_SESSION = [];
        $otherSessions = new SessionStore();
        $otherSessions->saveAuthenticatedUserId($otherId);
        $otherHistory = new GameHistoryService($games, $moves, $otherSessions);

        $tests->assertSame([], $otherHistory->listForAuthenticatedUser());
        $tests->assertSame(null, $otherHistory->openForAuthenticatedUser($gameId));
    });

    $tests->test('opening a saved game returns canonical state and ordered replay positions', function () use ($tests): void {
        [$game, $history, , , $sessions] = gameHistorySubject([10_000, 11_000, 12_000, 13_000, 14_000, 15_000, 16_000]);

        $game->createGame(['timeControl' => ['kind' => 'preset', 'preset' => '3+2']]);
        $stateAfterWhite = $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $stateAfterBlack = $game->submitMove(['from' => 'e7', 'to' => 'e5']);
        $gameId = $sessions->getCurrentGameId();

        $tests->assertTrue($gameId !== null, 'Authenticated game should have a durable id.');
        if ($gameId === null) {
            throw new RuntimeException('Missing durable game id.');
        }

        $opened = $history->openForAuthenticatedUser($gameId);

        $tests->assertTrue($opened !== null, 'Owner should be able to open their saved game.');
        if ($opened === null) {
            throw new RuntimeException('Missing opened game.');
        }

        $tests->assertSame($stateAfterBlack['fen'], $opened['state']['fen']);
        $tests->assertSame($stateAfterBlack['moveHistory'], $opened['state']['moveHistory']);
        $tests->assertSame(['initial', 'e2e4', 'e7e5'], array_map(
            static fn(array $position): string => (string) $position['coordinate'],
            $opened['replay']['positions'],
        ));
        $tests->assertSame($stateAfterWhite['fen'], $opened['replay']['positions'][1]['fen']);
        $tests->assertSame($stateAfterBlack['fen'], $opened['replay']['positions'][2]['fen']);
        $tests->assertSame(179_000, $opened['replay']['positions'][1]['whiteClockMilliseconds']);
        $tests->assertSame(180_000, $opened['replay']['positions'][1]['blackClockMilliseconds']);
        $tests->assertSame(179_000, $opened['replay']['positions'][2]['whiteClockMilliseconds']);
        $tests->assertSame(179_000, $opened['replay']['positions'][2]['blackClockMilliseconds']);
    });

    $tests->test('replay reads are immutable and do not mutate the active session game', function () use ($tests): void {
        [$game, $history, $games, , $sessions, $ownerId] = gameHistorySubject([20_000, 21_000, 22_000]);

        $game->createGame([]);
        $savedState = $game->submitMove(['from' => 'd2', 'to' => 'd4']);
        $activeStateBeforeReplay = $sessions->getState();
        $gameId = $sessions->getCurrentGameId();

        $tests->assertTrue($gameId !== null, 'Authenticated game should have a durable id.');
        if ($gameId === null) {
            throw new RuntimeException('Missing durable game id.');
        }

        $opened = $history->openForAuthenticatedUser($gameId);
        $tests->assertTrue($opened !== null, 'Owner should be able to open replay data.');
        if ($opened === null) {
            throw new RuntimeException('Missing opened game.');
        }

        $opened['state']['fen'] = 'mutated';
        $opened['replay']['positions'][1]['fen'] = 'mutated';
        $openedAgain = $history->openForAuthenticatedUser($gameId);

        $tests->assertTrue($openedAgain !== null, 'Fresh replay read should still be available.');
        if ($openedAgain === null) {
            throw new RuntimeException('Missing reopened game.');
        }

        $tests->assertSame($savedState['fen'], $openedAgain['state']['fen']);
        $tests->assertSame($savedState['fen'], $openedAgain['replay']['positions'][1]['fen']);
        $tests->assertSame($activeStateBeforeReplay, $sessions->getState());
        $tests->assertSame($savedState['fen'], gameHistoryDecodedFen($games, $gameId, $ownerId));
    });
};

/**
 * @param list<int> $times
 * @return array{0: GameService, 1: GameHistoryService, 2: GameRepository, 3: MoveRepository, 4: SessionStore, 5: int, 6: int}
 */
function gameHistorySubject(array $times): array
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
    $clock = gameHistoryClock($times);
    $game = new GameService($sessions, new GamePersistenceService($games, $sessions), $clock);
    $history = new GameHistoryService($games, $moves, $sessions);

    return [$game, $history, $games, $moves, $sessions, $owner->id, $other->id];
}

/** @param list<int> $times */
function gameHistoryClock(array $times): Closure
{
    $index = 0;

    return static function () use ($times, &$index): int {
        $time = $times[$index] ?? $times[count($times) - 1];
        $index++;

        return $time;
    };
}

function gameHistoryDecodedFen(GameRepository $games, int $gameId, int $ownerId): ?string
{
    $record = $games->findByIdForOwner($gameId, $ownerId);
    if ($record === null) {
        return null;
    }

    $state = json_decode($record->currentStateJson, true);

    return is_array($state) && is_string($state['fen'] ?? null) ? $state['fen'] : null;
}
