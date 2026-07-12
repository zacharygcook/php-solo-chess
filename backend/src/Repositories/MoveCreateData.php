<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

final class MoveCreateData
{
    public function __construct(
        public readonly int $plyNumber,
        public readonly string $fromSquare,
        public readonly string $toSquare,
        public readonly ?string $promotion,
        public readonly string $coordinate,
        public readonly string $san,
        public readonly string $positionAfterFen,
        public readonly ?string $stateAfterJson = null,
        public readonly ?int $whiteClockMs = null,
        public readonly ?int $blackClockMs = null,
    ) {}
}
