<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

final class GameRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $ownerUserId,
        public readonly string $whiteLabel,
        public readonly string $blackLabel,
        public readonly string $whitePlayerType,
        public readonly string $blackPlayerType,
        public readonly string $status,
        public readonly ?string $result,
        public readonly ?string $terminationReason,
        public readonly ?string $timeControlJson,
        public readonly string $currentStateJson,
        public readonly ?string $clockStateJson,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $completedAt,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['owner_user_id'],
            (string) $row['white_label'],
            (string) $row['black_label'],
            (string) $row['white_player_type'],
            (string) $row['black_player_type'],
            (string) $row['status'],
            self::nullableString($row['result']),
            self::nullableString($row['termination_reason']),
            self::nullableString($row['time_control_json']),
            (string) $row['current_state_json'],
            self::nullableString($row['clock_state_json']),
            (string) $row['created_at'],
            (string) $row['updated_at'],
            self::nullableString($row['completed_at']),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
