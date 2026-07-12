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
use SoloChess\Services\PgnVerifier;
use SoloChess\Services\SessionStore;

return static function (TestHarness $tests): void {
    if (defined('SOLO_CHESS_COVERAGE')) {
        return;
    }

    $tests->test('saved game pgn exports verify against canonical final positions', function () use ($tests): void {
        $cases = [
            [
                'moves' => [
                    ['e2', 'e4'],
                    ['e7', 'e5'],
                    ['g1', 'f3'],
                ],
                'finish' => static fn(GameService $game): array => $game->resignGame(['actorColor' => 'black']),
                'expectedResult' => '1-0',
                'expectedEnding' => '1. e4 e5 2. Nf3 1-0',
            ],
            [
                'moves' => [
                    ['f2', 'f3'],
                    ['e7', 'e5'],
                    ['g2', 'g4'],
                    ['d8', 'h4'],
                ],
                'finish' => null,
                'expectedResult' => '0-1',
                'expectedEnding' => '1. f3 e5 2. g4 Qh4# 0-1',
            ],
        ];

        foreach ($cases as $index => $case) {
            [$games, $moves, $sessions, $ownerId, $gameId] = pgnIntegrationPersistedGame(
                $case['moves'],
                $case['finish'],
            );
            $game = $games->findByIdForOwner($gameId, $ownerId);
            $moveRecords = $moves->listForGame($gameId);
            if ($game === null) {
                throw new RuntimeException('Saved integration game was not persisted.');
            }

            $verification = (new PgnVerifier())->verify($game, $moveRecords);
            $download = new PgnDownloadService(new PgnExporter(), $sessions, $games, $moves);
            $export = $download->exportResult($gameId);

            $tests->assertSame([], $verification->errors, 'Case ' . ($index + 1) . ' should replay cleanly.');
            $tests->assertTrue($verification->isValid(), 'Case ' . ($index + 1) . ' should verify.');
            $tests->assertSame($case['expectedResult'], $verification->result);
            $tests->assertSame(200, $export['status']);
            $tests->assertSame('application/x-chess-pgn; charset=UTF-8', $export['headers']['Content-Type']);
            $tests->assertTrue(str_contains($export['body'], '[Result "' . $case['expectedResult'] . "\"]\n"));
            $tests->assertTrue(str_ends_with($export['body'], $case['expectedEnding'] . "\n"));
        }
    });
};

/**
 * @param list<array{0: string, 1: string, 2?: string}> $moves
 * @param (Closure(GameService): array<string, mixed>)|null $finish
 * @return array{GameRepository, MoveRepository, SessionStore, int, int}
 */
function pgnIntegrationPersistedGame(array $moves, ?Closure $finish): array
{
    $_SESSION = [];
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);
    $users = new UserRepository($pdo);
    $owner = $users->create('PGN Owner', 'pgn_owner_' . count($moves), 'PGN Owner', 'hash');
    $games = new GameRepository($pdo);
    $moveRepository = new MoveRepository($pdo);
    $sessions = new SessionStore();
    $sessions->saveAuthenticatedUserId($owner->id);
    $game = new GameService($sessions, new GamePersistenceService($games, $sessions));
    $game->createGame(['whiteLabel' => 'Integration White', 'blackLabel' => 'Integration Black']);

    foreach ($moves as $move) {
        $state = $game->submitMove([
            'from' => $move[0],
            'to' => $move[1],
            'promotion' => $move[2] ?? null,
        ]);
        if (($state['isValidMove'] ?? true) === false) {
            throw new RuntimeException('Integration fixture move was rejected: ' . (string) ($state['lastMessage'] ?? 'unknown'));
        }
    }

    if ($finish !== null) {
        $finish($game);
    }

    $gameId = $sessions->getCurrentGameId();
    if ($gameId === null) {
        throw new RuntimeException('Saved integration game did not create a durable game id.');
    }

    return [$games, $moveRepository, $sessions, $owner->id, $gameId];
}
