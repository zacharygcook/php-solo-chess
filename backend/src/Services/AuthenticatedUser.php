<?php

declare(strict_types=1);

namespace SoloChess\Services;

use SoloChess\Repositories\UserRecord;

final class AuthenticatedUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $normalizedUsername,
        public readonly string $displayName,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromUserRecord(UserRecord $user): self
    {
        return new self(
            $user->id,
            $user->username,
            $user->normalizedUsername,
            $user->displayName,
            $user->createdAt,
            $user->updatedAt,
        );
    }
}
