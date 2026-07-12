<?php

declare(strict_types=1);

use SoloChess\Persistence\DatabaseSchema;
use SoloChess\Persistence\SqliteConnectionFactory;

require_once dirname(__DIR__) . '/backend/src/Persistence/SqliteConnectionFactory.php';
require_once dirname(__DIR__) . '/backend/src/Persistence/DatabaseSchema.php';

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);

if (count($arguments) > 1 || in_array($arguments[0] ?? '', ['-h', '--help'], true)) {
    fwrite(STDERR, "Usage: php scripts/setup-database.php [database-path]\n");
    exit(2);
}

$databasePath = $arguments[0] ?? $root . '/backend/storage/solo-chess.sqlite';
if (!str_starts_with($databasePath, DIRECTORY_SEPARATOR)) {
    $databasePath = getcwd() . DIRECTORY_SEPARATOR . $databasePath;
}

$pdo = SqliteConnectionFactory::forPath($databasePath);
DatabaseSchema::initialize($pdo);

fwrite(STDOUT, "SQLite database ready: {$databasePath}\n");
