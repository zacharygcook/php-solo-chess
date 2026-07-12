<?php

declare(strict_types=1);

use SoloChess\Engine\EngineMoveProposal;
use SoloChess\Engine\EngineRequest;
use SoloChess\Engine\FakeEngineAdapter;
use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    $tests->test('engine request exposes canonical fen context and participant types', function () use ($tests): void {
        $_SESSION = [];
        $game = new GameService(new SessionStore());
        $state = $game->createGame([
            'whiteLabel' => 'Human',
            'blackLabel' => 'Future engine',
            'blackParticipantType' => 'engine',
            'timeControl' => ['kind' => 'preset', 'preset' => '3+2'],
        ]);

        $request = EngineRequest::fromState($state);

        $tests->assertSame($state['fen'], $request->fen);
        $tests->assertSame('white', $request->activeColor);
        $tests->assertSame('Human', $request->participants['white']['label']);
        $tests->assertSame('local_human', $request->participants['white']['type']);
        $tests->assertSame('Future engine', $request->participants['black']['label']);
        $tests->assertSame('engine', $request->participants['black']['type']);
        $tests->assertSame($state['legalMoves'], $request->legalMoves);
        $tests->assertSame('active', $request->gameStatus);
        $tests->assertSame(null, $request->result);
        $tests->assertSame(1, $request->context['fullmoveNumber']);
        $tests->assertSame('3+2', $request->context['timeControl']['label']);
    });

    $tests->test('fake engine proposals use the same authoritative move path as human moves', function () use ($tests): void {
        $clock = static fn(): int => 1_783_871_678_000;
        $_SESSION = [];
        $engineStore = new SessionStore();
        $engineGame = new GameService($engineStore, null, $clock);
        $engineGame->createGame(['blackParticipantType' => 'engine']);
        $engineGame->submitMove(['from' => 'e2', 'to' => 'e4']);

        $proposal = (new FakeEngineAdapter([
            ['from' => 'e7', 'to' => 'e5'],
        ]))->proposeMove(EngineRequest::fromState($engineGame->getSessionState()));
        $afterEngineProposal = $engineGame->submitMove($proposal->toMovePayload());

        $_SESSION = [];
        $humanGame = new GameService(new SessionStore(), null, $clock);
        $humanGame->createGame(['blackParticipantType' => 'engine']);
        $humanGame->submitMove(['from' => 'e2', 'to' => 'e4']);
        $afterHumanMove = $humanGame->submitMove(['from' => 'e7', 'to' => 'e5']);

        $tests->assertSame('e7e5', $proposal->coordinate());
        $tests->assertSame($afterHumanMove['board'], $afterEngineProposal['board']);
        $tests->assertSame($afterHumanMove['activeColor'], $afterEngineProposal['activeColor']);
        $tests->assertSame($afterHumanMove['moveHistory'], $afterEngineProposal['moveHistory']);
        $tests->assertSame($afterHumanMove['fen'], $afterEngineProposal['fen']);
    });

    $tests->test('illegal fake engine proposals are rejected without mutation', function () use ($tests): void {
        $_SESSION = [];
        $store = new SessionStore();
        $game = new GameService($store);
        $game->createGame(['blackParticipantType' => 'engine']);
        $before = $game->submitMove(['from' => 'e2', 'to' => 'e4']);

        $proposal = new EngineMoveProposal('e7', 'e4');
        $rejected = $game->submitMove($proposal->toMovePayload());

        $tests->assertSame(false, $rejected['isValidMove']);
        $tests->assertSame('Illegal move.', $rejected['lastMessage']);
        $tests->assertSame($before['board'], $store->getState()['board']);
        $tests->assertSame($before['activeColor'], $store->getState()['activeColor']);
        $tests->assertSame($before['moveHistory'], $store->getState()['moveHistory']);
        $tests->assertSame($before['fen'], $store->getState()['fen']);
    });

    $tests->test('fake engine can choose a deterministic legal fallback without external engine code', function () use ($tests): void {
        $_SESSION = [];
        $game = new GameService(new SessionStore());
        $state = $game->createGame([]);

        $proposal = (new FakeEngineAdapter())->proposeMove(EngineRequest::fromState($state));

        $tests->assertSame('a2a3', $proposal->coordinate());
        $tests->assertSame(['from' => 'a2', 'to' => 'a3'], $proposal->toMovePayload());
    });
};
