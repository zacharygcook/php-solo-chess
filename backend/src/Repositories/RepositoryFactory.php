<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

use PDO;
use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;

final class RepositoryFactory
{
    private function __construct(private PDO $pdo) {}

    public static function default(): self
    {
        $pdo = SqliteConnectionFactory::forPath(self::defaultDatabasePath());
        DatabaseSchema::initialize($pdo);

        return new self($pdo);
    }

    public function userRepository(): UserRepository
    {
        return new UserRepository($this->pdo);
    }

    public function gameRepository(): GameRepository
    {
        return new GameRepository($this->pdo, $this->moveRepository());
    }

    public function moveRepository(): MoveRepository
    {
        return new MoveRepository($this->pdo);
    }

    private static function defaultDatabasePath(): string
    {
        $configured = getenv('SOLO_CHESS_DATABASE_PATH');
        if (is_string($configured) && $configured !== '') {
            return str_starts_with($configured, DIRECTORY_SEPARATOR)
                ? $configured
                : getcwd() . DIRECTORY_SEPARATOR . $configured;
        }

        return dirname(__DIR__, 2) . '/storage/solo-chess.sqlite';
    }
}
