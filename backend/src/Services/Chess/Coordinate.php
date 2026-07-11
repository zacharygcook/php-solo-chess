<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class Coordinate
{
    private function __construct(
        public readonly int $row,
        public readonly int $col,
        public readonly string $algebraic,
    ) {}

    public static function fromAlgebraic(mixed $value): ?self
    {
        if (!is_string($value) || preg_match('/^[a-h][1-8]$/', $value) !== 1) {
            return null;
        }

        return new self(
            row: 8 - (int) $value[1],
            col: ord($value[0]) - ord('a'),
            algebraic: $value,
        );
    }
}
