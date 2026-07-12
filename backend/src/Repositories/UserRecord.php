<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

final class UserRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $normalizedUsername,
        public readonly string $displayName,
        public readonly string $passwordHash,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['normalized_username'],
            (string) $row['display_name'],
            (string) $row['password_hash'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
