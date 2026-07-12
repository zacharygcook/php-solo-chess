<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

use PDO;
use RuntimeException;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(
        string $username,
        string $normalizedUsername,
        string $displayName,
        string $passwordHash,
    ): UserRecord {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (username, normalized_username, display_name, password_hash)
             VALUES (:username, :normalized_username, :display_name, :password_hash)',
        );
        $statement->execute([
            ':username' => $username,
            ':normalized_username' => $normalizedUsername,
            ':display_name' => $displayName,
            ':password_hash' => $passwordHash,
        ]);

        return $this->findRequiredById((int) $this->pdo->lastInsertId());
    }

    public function findById(int $id): ?UserRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, normalized_username, display_name, password_hash, created_at, updated_at
             FROM users
             WHERE id = :id',
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : UserRecord::fromRow($row);
    }

    public function findByNormalizedUsername(string $normalizedUsername): ?UserRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, normalized_username, display_name, password_hash, created_at, updated_at
             FROM users
             WHERE normalized_username = :normalized_username',
        );
        $statement->execute([':normalized_username' => $normalizedUsername]);
        $row = $statement->fetch();

        return $row === false ? null : UserRecord::fromRow($row);
    }

    /** @return list<UserRecord> */
    public function listAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, username, normalized_username, display_name, password_hash, created_at, updated_at
             FROM users
             ORDER BY id',
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to list users.');
        }

        return array_map(
            static fn(array $row): UserRecord => UserRecord::fromRow($row),
            $statement->fetchAll(),
        );
    }

    public function updateDisplayName(int $id, string $displayName): UserRecord
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET display_name = :display_name, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
        );
        $statement->execute([
            ':display_name' => $displayName,
            ':id' => $id,
        ]);

        return $this->findRequiredById($id);
    }

    private function findRequiredById(int $id): UserRecord
    {
        $record = $this->findById($id);
        if ($record === null) {
            throw new RuntimeException("User not found: {$id}");
        }

        return $record;
    }
}
