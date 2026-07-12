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

    $tests->test('resignation records a winner and finished games reject later actions without mutation', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();
        $game = new GameService($store);
        $game->createGame([]);

        $finished = $game->resignGame(['actorColor' => 'white']);

        $tests->assertSame('finished', $finished['gameStatus']);
        $tests->assertSame('0-1', $finished['result']);
        $tests->assertSame('resignation', $finished['terminationReason']);
        $tests->assertSame([], $finished['legalMoves']);
        $tests->assertSame('Resignation. Black wins.', $finished['lastMessage']);
        $tests->assertSame($finished, unserialize(serialize($finished)));

        $afterMove = $game->submitMove(['from' => 'e2', 'to' => 'e4']);
        $afterAction = $game->resignGame(['actorColor' => 'black']);

        $tests->assertSame(false, $afterMove['isValidMove']);
        $tests->assertSame('Game is already finished.', $afterMove['lastMessage']);
        $tests->assertSame(false, $afterAction['isValidAction']);
        $tests->assertSame('Game is already finished.', $afterAction['lastMessage']);
        $tests->assertSame($finished, $store->getState());
    });

    $tests->test('draw offers can only be accepted by the opponent in order', function () use ($tests): void {
        $_SESSION = [];
        $game = new GameService(new SessionStore());
        $game->createGame([]);

        $missingOffer = $game->acceptDraw(['actorColor' => 'black']);
        $offered = $game->offerDraw(['actorColor' => 'white']);
        $selfAccepted = $game->acceptDraw(['actorColor' => 'white']);
        $accepted = $game->acceptDraw(['actorColor' => 'black']);

        $tests->assertSame(false, $missingOffer['isValidAction']);
        $tests->assertSame('No draw offer is available to accept.', $missingOffer['lastMessage']);
        $tests->assertSame('active', $offered['gameStatus']);
        $tests->assertSame(['offeredBy' => 'white'], $offered['drawOffer']);
        $tests->assertSame('Draw offered by White.', $offered['lastMessage']);
        $tests->assertSame(false, $selfAccepted['isValidAction']);
        $tests->assertSame('Only the opponent may accept a draw offer.', $selfAccepted['lastMessage']);
        $tests->assertSame('finished', $accepted['gameStatus']);
        $tests->assertSame('1/2-1/2', $accepted['result']);
        $tests->assertSame('agreedDraw', $accepted['terminationReason']);
        $tests->assertSame(null, $accepted['drawOffer']);
        $tests->assertSame('Draw agreed.', $accepted['lastMessage']);
    });

    $tests->test('valid draw claims finish the game while invalid claim attempts do not mutate state', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();
        $game = new GameService($store);
        $state = $game->createGame([]);
        $state['drawClaims'] = ['fiftyMoveRule'];
        $state['availableActions'] = ['claimDraw'];
        $store->saveState($state);

        $wrongSide = $game->claimDraw(['actorColor' => 'black', 'claim' => 'fiftyMoveRule']);
        $tests->assertSame(false, $wrongSide['isValidAction']);
        $tests->assertSame('Only the side to move may claim a draw.', $wrongSide['lastMessage']);
        $tests->assertSame($state, $store->getState());

        $finished = $game->claimDraw(['actorColor' => 'white', 'claim' => 'fiftyMoveRule']);
        $tests->assertSame('finished', $finished['gameStatus']);
        $tests->assertSame('1/2-1/2', $finished['result']);
        $tests->assertSame('fiftyMoveRule', $finished['terminationReason']);
        $tests->assertSame([], $finished['drawClaims']);
        $tests->assertSame([], $finished['availableActions']);
        $tests->assertSame('Draw claimed by fifty-move rule.', $finished['lastMessage']);
    });

    $tests->test('abandonment finishes the game without changing board turn or history', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();
        $game = new GameService($store);
        $before = $game->createGame([]);

        $finished = $game->abandonGame(['actorColor' => 'white']);

        $tests->assertSame('finished', $finished['gameStatus']);
        $tests->assertSame('*', $finished['result']);
        $tests->assertSame('abandoned', $finished['terminationReason']);
        $tests->assertSame('Game abandoned.', $finished['lastMessage']);
        $tests->assertSame($before['board'], $finished['board']);
        $tests->assertSame($before['activeColor'], $finished['activeColor']);
        $tests->assertSame($before['moveHistory'], $finished['moveHistory']);
        $tests->assertSame([], $finished['legalMoves']);
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
