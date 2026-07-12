<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;
use SoloChess\Repositories\GameCreateData;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\GameUpdateData;
use SoloChess\Repositories\MoveCreateData;
use SoloChess\Repositories\MoveRepository;
use SoloChess\Repositories\UserRepository;

return static function (TestHarness $tests): void {
    $tests->test('user repository creates reads lists and updates users', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        $users = new UserRepository($pdo);

        $created = $users->create('PlayerOne', 'playerone', 'Player One', 'hash-one');
        $users->create('Second', 'second', 'Second Player', 'hash-two');
        $updated = $users->updateDisplayName($created->id, 'Player 1');

        $tests->assertSame('Player 1', $updated->displayName);
        $tests->assertSame('PlayerOne', $users->findById($created->id)?->username);
        $tests->assertSame('hash-one', $users->findByNormalizedUsername('playerone')?->passwordHash);
        $tests->assertSame(['Player 1', 'Second Player'], array_map(
            static fn($user): string => $user->displayName,
            $users->listAll(),
        ));
    });

    $tests->test('duplicate normalized usernames fail without adding a second user', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        $users = new UserRepository($pdo);
        $users->create('PlayerOne', 'playerone', 'Player One', 'hash-one');

        $duplicateRejected = false;
        try {
            $users->create('playerone', 'playerone', 'Duplicate', 'hash-two');
        } catch (PDOException) {
            $duplicateRejected = true;
        }

        $tests->assertTrue($duplicateRejected, 'Duplicate normalized usernames should be rejected.');
        $tests->assertSame(1, count($users->listAll()));
    });

    $tests->test('game repository creates reads lists and updates canonical state for one owner', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        $users = new UserRepository($pdo);
        $games = new GameRepository($pdo);
        $owner = $users->create('Owner', 'owner', 'Owner', 'hash');
        $other = $users->create('Other', 'other', 'Other', 'hash');

        $game = $games->create(new GameCreateData(
            ownerUserId: $owner->id,
            status: 'active',
            currentStateJson: '{"fen":"initial"}',
            whiteLabel: 'Alice',
            blackLabel: 'Bob',
        ));
        $updated = $games->updateState($game->id, $owner->id, new GameUpdateData(
            status: 'checkmate',
            currentStateJson: '{"fen":"final"}',
            result: '1-0',
            terminationReason: 'checkmate',
        ));

        $tests->assertSame('{"fen":"final"}', $updated->currentStateJson);
        $tests->assertSame('checkmate', $games->findByIdForOwner($game->id, $owner->id)?->status);
        $tests->assertSame(null, $games->findByIdForOwner($game->id, $other->id));
        $tests->assertSame([$game->id], array_map(
            static fn($listedGame): int => $listedGame->id,
            $games->listForOwner($owner->id),
        ));
    });

    $tests->test('game repository rejects invalid owners without creating a game', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        $games = new GameRepository($pdo);

        $foreignKeyRejected = false;
        try {
            $games->create(new GameCreateData(
                ownerUserId: 999,
                status: 'active',
                currentStateJson: '{}',
            ));
        } catch (PDOException) {
            $foreignKeyRejected = true;
        }

        $tests->assertTrue($foreignKeyRejected, 'Games should require an existing owner.');
        $tests->assertSame([], $games->listForOwner(999));
    });

    $tests->test('move repository writes and reloads ordered moves', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        [$gameId] = repositoryTestGame($pdo);
        $moves = new MoveRepository($pdo);

        $moves->create($gameId, repositoryMove(2, 'e7', 'e5', 'e7e5', 'e5'));
        $moves->create($gameId, repositoryMove(1, 'e2', 'e4', 'e2e4', 'e4'));

        $tests->assertSame(['e2e4', 'e7e5'], array_map(
            static fn($move): string => $move->coordinate,
            $moves->listForGame($gameId),
        ));
        $tests->assertSame('state-after-2', $moves->listForGame($gameId)[1]->stateAfterJson);
    });

    $tests->test('canonical game snapshots update game state and moves atomically', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        [$gameId, $ownerId] = repositoryTestGame($pdo);
        $games = new GameRepository($pdo);
        $moves = new MoveRepository($pdo);

        $saved = $games->replaceCanonicalStateWithMoves(
            $gameId,
            $ownerId,
            new GameUpdateData(status: 'active', currentStateJson: '{"fen":"after-two"}'),
            [
                repositoryMove(1, 'e2', 'e4', 'e2e4', 'e4'),
                repositoryMove(2, 'e7', 'e5', 'e7e5', 'e5'),
            ],
        );

        $tests->assertSame('{"fen":"after-two"}', $saved->currentStateJson);
        $tests->assertSame(['e2e4', 'e7e5'], array_map(
            static fn($move): string => $move->coordinate,
            $moves->listForGame($gameId),
        ));
    });

    $tests->test('snapshot failures roll back game state and existing ordered moves', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        [$gameId, $ownerId] = repositoryTestGame($pdo);
        $games = new GameRepository($pdo);
        $moves = new MoveRepository($pdo);

        $games->replaceCanonicalStateWithMoves(
            $gameId,
            $ownerId,
            new GameUpdateData(status: 'active', currentStateJson: '{"fen":"after-one"}'),
            [repositoryMove(1, 'e2', 'e4', 'e2e4', 'e4')],
        );

        $duplicatePlyRejected = false;
        try {
            $games->replaceCanonicalStateWithMoves(
                $gameId,
                $ownerId,
                new GameUpdateData(status: 'active', currentStateJson: '{"fen":"should-rollback"}'),
                [
                    repositoryMove(1, 'd2', 'd4', 'd2d4', 'd4'),
                    repositoryMove(1, 'd7', 'd5', 'd7d5', 'd5'),
                ],
            );
        } catch (PDOException) {
            $duplicatePlyRejected = true;
        }

        $tests->assertTrue($duplicatePlyRejected, 'Duplicate ply numbers should reject the snapshot.');
        $tests->assertSame(
            '{"fen":"after-one"}',
            $games->findByIdForOwner($gameId, $ownerId)?->currentStateJson,
        );
        $tests->assertSame(['e2e4'], array_map(
            static fn($move): string => $move->coordinate,
            $moves->listForGame($gameId),
        ));
    });

    $tests->test('move repository rejects invalid game references without partial writes', function () use ($tests): void {
        $pdo = repositoryTestDatabase();
        [$gameId] = repositoryTestGame($pdo);
        $moves = new MoveRepository($pdo);
        $moves->create($gameId, repositoryMove(1, 'e2', 'e4', 'e2e4', 'e4'));

        $foreignKeyRejected = false;
        try {
            $moves->create(999, repositoryMove(1, 'e7', 'e5', 'e7e5', 'e5'));
        } catch (PDOException) {
            $foreignKeyRejected = true;
        }

        $tests->assertTrue($foreignKeyRejected, 'Moves should require an existing game.');
        $tests->assertSame(['e2e4'], array_map(
            static fn($move): string => $move->coordinate,
            $moves->listForGame($gameId),
        ));
    });
};

function repositoryTestDatabase(): PDO
{
    $pdo = SqliteConnectionFactory::inMemory();
    DatabaseSchema::initialize($pdo);

    return $pdo;
}

/** @return array{0: int, 1: int} */
function repositoryTestGame(PDO $pdo): array
{
    $user = (new UserRepository($pdo))->create('Owner', 'owner', 'Owner', 'hash');
    $game = (new GameRepository($pdo))->create(new GameCreateData(
        ownerUserId: $user->id,
        status: 'active',
        currentStateJson: '{"fen":"initial"}',
    ));

    return [$game->id, $user->id];
}

function repositoryMove(
    int $plyNumber,
    string $fromSquare,
    string $toSquare,
    string $coordinate,
    string $san,
): MoveCreateData {
    return new MoveCreateData(
        plyNumber: $plyNumber,
        fromSquare: $fromSquare,
        toSquare: $toSquare,
        promotion: null,
        coordinate: $coordinate,
        san: $san,
        positionAfterFen: "fen-after-{$plyNumber}",
        stateAfterJson: "state-after-{$plyNumber}",
    );
}
