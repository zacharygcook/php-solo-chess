<?php

declare(strict_types=1);

use SoloChess\Controllers\GameController;
use SoloChess\Controllers\GameHistoryController;
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
    $tests->test('game lifecycle API envelopes cover creation moves resignation and abandonment', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();
        $controller = new GameController(new GameService($store, currentTimeMilliseconds: gameApiClock([
            1_000, 4_000, 4_000, 8_000, 8_000,
        ])));

        $created = $controller->createGameResult([
            'whiteLabel' => 'Ada',
            'blackLabel' => 'Byron',
            'timeControl' => ['kind' => 'preset', 'preset' => '1+0'],
        ]);
        $moved = $controller->submitMoveResult(['from' => 'e2', 'to' => 'e4']);
        $resigned = $controller->resignResult(['actorColor' => 'black']);
        $lateMove = $controller->submitMoveResult(['from' => 'e7', 'to' => 'e5']);

        $tests->assertSame(201, $created['status']);
        $tests->assertSame(true, $created['payload']['success']);
        $tests->assertSame('Game created.', $created['payload']['message']);
        $tests->assertSame('Ada', $created['payload']['state']['participants']['white']['label']);
        $tests->assertSame('timed', $created['payload']['state']['clockState']['mode']);

        $tests->assertSame(200, $moved['status']);
        $tests->assertSame(true, $moved['payload']['success']);
        $tests->assertSame('black', $moved['payload']['state']['activeColor']);
        $tests->assertSame(false, array_key_exists('isValidMove', $moved['payload']['state']));

        $tests->assertSame(true, $resigned['payload']['success']);
        $tests->assertSame('finished', $resigned['payload']['state']['gameStatus']);
        $tests->assertSame('1-0', $resigned['payload']['state']['result']);
        $tests->assertSame('resignation', $resigned['payload']['state']['terminationReason']);

        $tests->assertSame(false, $lateMove['payload']['success']);
        $tests->assertSame('Game is already finished.', $lateMove['payload']['message']);
        $tests->assertSame('finished', $lateMove['payload']['state']['gameStatus']);

        $_SESSION = [];
        $abandonController = new GameController(new GameService(new SessionStore()));
        $abandonController->createGameResult([]);
        $abandoned = $abandonController->abandonResult(['actorColor' => 'white']);

        $tests->assertSame(true, $abandoned['payload']['success']);
        $tests->assertSame('*', $abandoned['payload']['state']['result']);
        $tests->assertSame('abandoned', $abandoned['payload']['state']['terminationReason']);
    });

    $tests->test('draw action API envelopes enforce offer accept and claim boundaries', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();
        $controller = new GameController(new GameService($store));
        $controller->createGameResult([]);

        $missingOffer = $controller->acceptDrawResult(['actorColor' => 'black']);
        $offered = $controller->offerDrawResult(['actorColor' => 'white']);
        $accepted = $controller->acceptDrawResult(['actorColor' => 'black']);

        $tests->assertSame(false, $missingOffer['payload']['success']);
        $tests->assertSame('No draw offer is available to accept.', $missingOffer['payload']['message']);
        $tests->assertSame(true, $offered['payload']['success']);
        $tests->assertSame(['offeredBy' => 'white'], $offered['payload']['state']['drawOffer']);
        $tests->assertSame(true, $accepted['payload']['success']);
        $tests->assertSame('agreedDraw', $accepted['payload']['state']['terminationReason']);

        $_SESSION = [];
        $claimStore = new SessionStore();
        $claimController = new GameController(new GameService($claimStore));
        $state = $claimController->createGameResult([])['payload']['state'];
        $state['drawClaims'] = ['fiftyMoveRule'];
        $state['availableActions'] = ['claimDraw'];
        $claimStore->saveState($state);

        $claim = $claimController->claimDrawResult(['actorColor' => 'white', 'claim' => 'fiftyMoveRule']);

        $tests->assertSame(true, $claim['payload']['success']);
        $tests->assertSame('1/2-1/2', $claim['payload']['state']['result']);
        $tests->assertSame('fiftyMoveRule', $claim['payload']['state']['terminationReason']);
        $tests->assertSame(false, array_key_exists('isValidAction', $claim['payload']['state']));
    });

    $tests->test('history replay API envelopes are authenticated owner scoped and session independent', function () use ($tests): void {
        [$gameController, $games, $moves, $ownerId, $otherId, $gameId] = gameApiSavedTimedGame();

        $tests->assertTrue($gameId > 0, 'Saved API journey should create a durable game id.');
        $tests->assertSame('finished', $gameController->sessionStateResult()['payload']['state']['gameStatus']);

        $_SESSION = [];
        $readerSessions = new SessionStore();
        $readerSessions->saveAuthenticatedUserId($ownerId);
        $history = new GameHistoryController(new GameHistoryService($games, $moves, $readerSessions), $readerSessions);

        $listed = $history->historyResult();
        $opened = $history->openResult($gameId);
        $replay = $history->replayResult($gameId);

        $tests->assertSame(200, $listed['status']);
        $tests->assertSame(1, count($listed['payload']['state']['games']));
        $tests->assertSame($gameId, $listed['payload']['state']['games'][0]['id']);
        $tests->assertSame('3+2', $listed['payload']['state']['games'][0]['timeControl']['label']);

        $tests->assertSame(200, $opened['status']);
        $tests->assertSame($gameId, $opened['payload']['state']['game']['id']);
        $tests->assertSame('finished', $opened['payload']['state']['gameState']['gameStatus']);
        $tests->assertSame('resignation', $opened['payload']['state']['gameState']['terminationReason']);

        $tests->assertSame(200, $replay['status']);
        $tests->assertSame(['initial', 'e2e4'], array_map(
            static fn(array $position): string => (string) $position['coordinate'],
            $replay['payload']['state']['replay']['positions'],
        ));
        $tests->assertSame(false, array_key_exists('gameState', $replay['payload']['state']));

        $_SESSION = [];
        $otherSessions = new SessionStore();
        $otherSessions->saveAuthenticatedUserId($otherId);
        $otherHistory = new GameHistoryController(new GameHistoryService($games, $moves, $otherSessions), $otherSessions);

        $tests->assertSame([], $otherHistory->historyResult()['payload']['state']['games']);
        $tests->assertSame(404, $otherHistory->openResult($gameId)['status']);

        $_SESSION = [];
        $guestSessions = new SessionStore();
        $guestHistory = new GameHistoryController(new GameHistoryService($games, $moves, $guestSessions), $guestSessions);

        $tests->assertSame(401, $guestHistory->historyResult()['status']);
        $tests->assertSame(401, $guestHistory->replayResult($gameId)['status']);
        $tests->assertSame(422, $history->openResult(null)['status']);
    });
};

/**
 * @return array{0: GameController, 1: GameRepository, 2: MoveRepository, 3: int, 4: int, 5: int}
 */
function gameApiSavedTimedGame(): array
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
        gameApiClock([10_000, 11_000, 11_000, 12_000, 12_000]),
    );
    $controller = new GameController($game);

    $controller->createGameResult(['timeControl' => ['kind' => 'preset', 'preset' => '3+2']]);
    $controller->submitMoveResult(['from' => 'e2', 'to' => 'e4']);
    $controller->resignResult(['actorColor' => 'black']);

    return [$controller, $games, $moves, $owner->id, $other->id, $sessions->getCurrentGameId() ?? 0];
}

/** @param list<int> $times */
function gameApiClock(array $times): Closure
{
    $index = 0;

    return static function () use ($times, &$index): int {
        $time = $times[$index] ?? $times[count($times) - 1];
        $index++;

        return $time;
    };
}
