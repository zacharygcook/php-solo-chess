<?php

declare(strict_types=1);

namespace SoloChess\Persistence;

use PDO;
use RuntimeException;

final class SqliteConnectionFactory
{
    public static function forPath(string $databasePath): PDO
    {
        if ($databasePath === '') {
            throw new RuntimeException('Database path must not be empty.');
        }

        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return self::configured(new PDO('sqlite:' . $databasePath));
    }

    public static function inMemory(): PDO
    {
        return self::configured(new PDO('sqlite::memory:'));
    }

    private static function configured(PDO $pdo): PDO
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $pdo;
    }
}
