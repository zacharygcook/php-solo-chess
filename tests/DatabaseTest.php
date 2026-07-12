<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;

return static function (TestHarness $tests): void {
    $tests->test('database setup command creates the sqlite persistence schema', function () use ($tests): void {
        $databasePath = temporaryDatabasePath();

        try {
            $command = sprintf(
                '%s %s %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(dirname(__DIR__) . '/scripts/setup-database.php'),
                escapeshellarg($databasePath),
            );

            exec($command, $output, $exitCode);
            $tests->assertSame(0, $exitCode, implode("\n", $output));

            $pdo = SqliteConnectionFactory::forPath($databasePath);
            $tests->assertSame(['games', 'moves', 'users'], tableNames($pdo));
        } finally {
            removeDatabaseFiles($databasePath);
        }
    });

    $tests->test('database schema setup is deterministic and idempotent', function () use ($tests): void {
        $pdo = SqliteConnectionFactory::inMemory();

        DatabaseSchema::initialize($pdo);
        $firstDefinition = schemaDefinition($pdo);

        DatabaseSchema::initialize($pdo);
        $tests->assertSame($firstDefinition, schemaDefinition($pdo));

        $tests->assertSame(
            ['id', 'username', 'normalized_username', 'display_name', 'password_hash', 'created_at', 'updated_at'],
            columnNames($pdo, 'users'),
        );
        $tests->assertSame(
            [
                'id',
                'owner_user_id',
                'white_label',
                'black_label',
                'white_player_type',
                'black_player_type',
                'status',
                'result',
                'termination_reason',
                'time_control_json',
                'current_state_json',
                'clock_state_json',
                'created_at',
                'updated_at',
                'completed_at',
            ],
            columnNames($pdo, 'games'),
        );
        $tests->assertSame(
            [
                'id',
                'game_id',
                'ply_number',
                'from_square',
                'to_square',
                'promotion',
                'coordinate',
                'san',
                'position_after_fen',
                'state_after_json',
                'white_clock_ms',
                'black_clock_ms',
                'created_at',
            ],
            columnNames($pdo, 'moves'),
        );
    });

    $tests->test('database schema enforces uniqueness and foreign keys', function () use ($tests): void {
        $pdo = SqliteConnectionFactory::inMemory();
        DatabaseSchema::initialize($pdo);

        $pdo->prepare(
            'INSERT INTO users (username, normalized_username, display_name, password_hash) VALUES (?, ?, ?, ?)',
        )->execute(['PlayerOne', 'playerone', 'Player One', 'hash']);

        $duplicateRejected = false;
        try {
            $pdo->prepare(
                'INSERT INTO users (username, normalized_username, display_name, password_hash) VALUES (?, ?, ?, ?)',
            )->execute(['playerone', 'playerone', 'Duplicate', 'hash']);
        } catch (PDOException) {
            $duplicateRejected = true;
        }
        $tests->assertTrue($duplicateRejected, 'Duplicate normalized usernames must be rejected.');

        $foreignKeyRejected = false;
        try {
            $pdo->prepare(
                'INSERT INTO games (owner_user_id, status, current_state_json) VALUES (?, ?, ?)',
            )->execute([999, 'active', '{}']);
        } catch (PDOException) {
            $foreignKeyRejected = true;
        }
        $tests->assertTrue($foreignKeyRejected, 'Games must reference an existing owner.');

        $pdo->prepare(
            'INSERT INTO games (owner_user_id, status, current_state_json) VALUES (?, ?, ?)',
        )->execute([1, 'active', '{}']);
        $pdo->prepare(
            'INSERT INTO moves (game_id, ply_number, from_square, to_square, coordinate, san, position_after_fen) VALUES (?, ?, ?, ?, ?, ?, ?)',
        )->execute([1, 1, 'e2', 'e4', 'e2e4', 'e4', 'fen']);

        $tests->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM moves')->fetchColumn());
    });
};

function temporaryDatabasePath(): string
{
    $path = tempnam(sys_get_temp_dir(), 'solo_chess_db_');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary database path.');
    }
    unlink($path);

    return $path;
}

/** @return list<string> */
function tableNames(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
    );
    if ($statement === false) {
        throw new RuntimeException('Unable to read database tables.');
    }

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

/** @return list<string> */
function columnNames(PDO $pdo, string $table): array
{
    $statement = $pdo->query("PRAGMA table_info({$table})");
    if ($statement === false) {
        throw new RuntimeException("Unable to read columns for {$table}.");
    }

    return array_map(
        static fn(array $column): string => (string) $column['name'],
        $statement->fetchAll(),
    );
}

/** @return list<string> */
function schemaDefinition(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT type || ':' || name || ':' || sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type, name",
    );
    if ($statement === false) {
        throw new RuntimeException('Unable to read schema definitions.');
    }

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function removeDatabaseFiles(string $databasePath): void
{
    foreach ([$databasePath, $databasePath . '-journal', $databasePath . '-shm', $databasePath . '-wal'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
