<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class Move
{
    public function __construct(
        public readonly Coordinate $from,
        public readonly Coordinate $to,
        public readonly string $piece,
        public readonly ?string $promotion,
        public readonly int $timestamp,
    ) {}

    /** @return array<string, int|string|null> */
    public function toHistoryRecord(): array
    {
        return [
            'from' => $this->from->algebraic,
            'fromCol' => $this->from->col,
            'fromRow' => $this->from->row,
            'to' => $this->to->algebraic,
            'toCol' => $this->to->col,
            'toRow' => $this->to->row,
            'piece' => $this->piece,
            'promotion' => $this->promotion,
            'timestamp' => $this->timestamp,
        ];
    }
}
