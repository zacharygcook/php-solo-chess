<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

final class MoveRecord
{
    public function __construct(
        public readonly int $id,
        public readonly int $gameId,
        public readonly int $plyNumber,
        public readonly string $fromSquare,
        public readonly string $toSquare,
        public readonly ?string $promotion,
        public readonly string $coordinate,
        public readonly string $san,
        public readonly string $positionAfterFen,
        public readonly ?string $stateAfterJson,
        public readonly ?int $whiteClockMs,
        public readonly ?int $blackClockMs,
        public readonly string $createdAt,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['game_id'],
            (int) $row['ply_number'],
            (string) $row['from_square'],
            (string) $row['to_square'],
            self::nullableString($row['promotion']),
            (string) $row['coordinate'],
            (string) $row['san'],
            (string) $row['position_after_fen'],
            self::nullableString($row['state_after_json']),
            self::nullableInt($row['white_clock_ms']),
            self::nullableInt($row['black_clock_ms']),
            (string) $row['created_at'],
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
