<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\UserRepository;
use SoloChess\Services\GameLifecycleService;
use SoloChess\Services\GamePersistenceService;
use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('new untimed game uses default participants and serializable clock state', function () use ($tests): void {
        $lifecycle = new GameLifecycleService(currentTimeMilliseconds: static fn(): int => 1_700_000_000_000);

        $state = $lifecycle->createGame([]);

        $tests->assertSame('white', $state['activeColor']);
        $tests->assertSame([], $state['moveHistory']);
        $tests->assertSame('White', $state['participants']['white']['label']);
        $tests->assertSame('Black', $state['participants']['black']['label']);
        $tests->assertSame('local_human', $state['participants']['white']['type']);
        $tests->assertSame('local_human', $state['participants']['black']['type']);
        $tests->assertSame('untimed', $state['timeControl']['kind']);
        $tests->assertSame('Untimed', $state['timeControl']['label']);
        $tests->assertSame('untimed', $state['clockState']['mode']);
        $tests->assertSame(null, $state['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(null, $state['clockState']['blackRemainingMilliseconds']);
        $tests->assertSame(null, $state['clockState']['turnStartedAtMilliseconds']);
        $tests->assertSame($state, unserialize(serialize($state)));
    });

    $tests->test('new preset timed game stores labels participant types and initial clocks', function () use ($tests): void {
        $lifecycle = new GameLifecycleService(currentTimeMilliseconds: static fn(): int => 1_700_000_123_456);

        $state = $lifecycle->createGame([
            'whiteLabel' => 'Alice',
            'blackLabel' => 'Practice engine',
            'blackParticipantType' => 'engine',
            'timeControl' => ['kind' => 'preset', 'preset' => '5+0'],
        ]);

        $tests->assertSame('Alice', $state['participants']['white']['label']);
        $tests->assertSame('Practice engine', $state['participants']['black']['label']);
        $tests->assertSame('local_human', $state['participants']['white']['type']);
        $tests->assertSame('engine', $state['participants']['black']['type']);
        $tests->assertSame('preset', $state['timeControl']['kind']);
        $tests->assertSame('5+0', $state['timeControl']['label']);
        $tests->assertSame(300_000, $state['timeControl']['baseMilliseconds']);
        $tests->assertSame(0, $state['timeControl']['incrementMilliseconds']);
        $tests->assertSame('timed', $state['clockState']['mode']);
        $tests->assertSame(300_000, $state['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(300_000, $state['clockState']['blackRemainingMilliseconds']);
        $tests->assertSame(1_700_000_123_456, $state['clockState']['turnStartedAtMilliseconds']);
        $tests->assertSame('white', $state['clockState']['activeColor']);
    });

    $tests->test('new custom timed game validates base and increment bounds', function () use ($tests): void {
        $lifecycle = new GameLifecycleService(currentTimeMilliseconds: static fn(): int => 1_700_000_123_456);

        $state = $lifecycle->createGame([
            'timeControl' => ['kind' => 'custom', 'baseMinutes' => 7, 'incrementSeconds' => 3],
        ]);

        $tests->assertSame('custom', $state['timeControl']['kind']);
        $tests->assertSame('7+3', $state['timeControl']['label']);
        $tests->assertSame(420_000, $state['timeControl']['baseMilliseconds']);
        $tests->assertSame(3_000, $state['timeControl']['incrementMilliseconds']);
        $tests->assertSame(420_000, $state['clockState']['whiteRemainingMilliseconds']);
        $tests->assertSame(420_000, $state['clockState']['blackRemainingMilliseconds']);

        gameLifecycleExpectInvalid($tests, ['timeControl' => ['kind' => 'custom', 'baseMinutes' => 0, 'incrementSeconds' => 3]]);
        gameLifecycleExpectInvalid($tests, ['timeControl' => ['kind' => 'custom', 'baseMinutes' => 7, 'incrementSeconds' => -1]]);
        gameLifecycleExpectInvalid($tests, ['timeControl' => ['kind' => 'preset', 'preset' => '2+1']]);
        gameLifecycleExpectInvalid($tests, ['whiteParticipantType' => 'remote']);
    });

    $tests->test('authenticated game creation persists ownership labels and time control metadata', function () use ($tests): void {
        $_SESSION = [];
        $pdo = SqliteConnectionFactory::inMemory();
        DatabaseSchema::initialize($pdo);
        $users = new UserRepository($pdo);
        $owner = $users->create('Owner', 'owner', 'Owner', 'hash');
        $games = new GameRepository($pdo);
        $sessions = new SessionStore();
        $sessions->saveAuthenticatedUserId($owner->id);
        $game = new GameService($sessions, new GamePersistenceService($games, $sessions));

        $state = $game->createGame([
            'whiteLabel' => 'Owner',
            'blackLabel' => 'Training side',
            'timeControl' => ['kind' => 'preset', 'preset' => '3+2'],
        ]);
        $gameId = $sessions->getCurrentGameId();

        $tests->assertTrue($gameId !== null, 'Authenticated creation should store a current durable game id.');
        if ($gameId === null) {
            throw new RuntimeException('Missing durable game id.');
        }

        $record = $games->findByIdForOwner($gameId, $owner->id);
        $tests->assertTrue($record !== null, 'Created game should be readable by its owner.');
        if ($record === null) {
            throw new RuntimeException('Missing created game record.');
        }

        $tests->assertSame('Owner', $record->whiteLabel);
        $tests->assertSame('Training side', $record->blackLabel);
        $tests->assertSame('local_human', $record->whitePlayerType);
        $tests->assertSame('local_human', $record->blackPlayerType);
        $tests->assertSame($state, json_decode($record->currentStateJson, true));
        $tests->assertSame($state['timeControl'], json_decode((string) $record->timeControlJson, true));
        $tests->assertSame($state['clockState'], json_decode((string) $record->clockStateJson, true));
    });
};

/** @param array<string, mixed> $payload */
function gameLifecycleExpectInvalid(TestHarness $tests, array $payload): void
{
    $lifecycle = new GameLifecycleService(currentTimeMilliseconds: static fn(): int => 1_700_000_000_000);

    try {
        $lifecycle->createGame($payload);
    } catch (InvalidArgumentException) {
        $tests->assertTrue(true);

        return;
    }

    throw new RuntimeException('Expected invalid lifecycle payload to be rejected.');
}
