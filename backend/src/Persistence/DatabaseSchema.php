<?php

declare(strict_types=1);

namespace SoloChess\Persistence;

use PDO;
use Throwable;

final class DatabaseSchema
{
    public static function initialize(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->beginTransaction();

        try {
            foreach (self::statements() as $statement) {
                $pdo->exec($statement);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }

    /** @return list<string> */
    public static function statements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    normalized_username TEXT NOT NULL,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (length(trim(username)) > 0),
    CHECK (length(trim(normalized_username)) > 0),
    CHECK (length(trim(display_name)) > 0),
    CHECK (length(password_hash) > 0)
)
SQL,
            <<<'SQL'
CREATE UNIQUE INDEX IF NOT EXISTS users_normalized_username_unique
    ON users (normalized_username)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_user_id INTEGER NOT NULL,
    white_label TEXT NOT NULL DEFAULT 'White',
    black_label TEXT NOT NULL DEFAULT 'Black',
    white_player_type TEXT NOT NULL DEFAULT 'local_human',
    black_player_type TEXT NOT NULL DEFAULT 'local_human',
    status TEXT NOT NULL,
    result TEXT,
    termination_reason TEXT,
    time_control_json TEXT,
    current_state_json TEXT NOT NULL,
    clock_state_json TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TEXT,
    FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CHECK (length(trim(white_label)) > 0),
    CHECK (length(trim(black_label)) > 0),
    CHECK (length(trim(white_player_type)) > 0),
    CHECK (length(trim(black_player_type)) > 0),
    CHECK (length(trim(status)) > 0),
    CHECK (length(current_state_json) > 0)
)
SQL,
            <<<'SQL'
CREATE INDEX IF NOT EXISTS games_owner_user_id_updated_at_index
    ON games (owner_user_id, updated_at, id)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS moves (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    ply_number INTEGER NOT NULL,
    from_square TEXT NOT NULL,
    to_square TEXT NOT NULL,
    promotion TEXT,
    coordinate TEXT NOT NULL,
    san TEXT NOT NULL,
    position_after_fen TEXT NOT NULL,
    state_after_json TEXT,
    white_clock_ms INTEGER,
    black_clock_ms INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE,
    UNIQUE (game_id, ply_number),
    CHECK (ply_number > 0),
    CHECK (length(from_square) = 2),
    CHECK (length(to_square) = 2),
    CHECK (length(coordinate) > 0),
    CHECK (length(san) > 0),
    CHECK (length(position_after_fen) > 0)
)
SQL,
            <<<'SQL'
CREATE INDEX IF NOT EXISTS moves_game_id_ply_number_index
    ON moves (game_id, ply_number)
SQL,
        ];
    }
}
