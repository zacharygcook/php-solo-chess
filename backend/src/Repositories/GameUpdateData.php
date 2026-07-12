<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

final class GameUpdateData
{
    public function __construct(
        public readonly string $status,
        public readonly string $currentStateJson,
        public readonly ?string $result = null,
        public readonly ?string $terminationReason = null,
        public readonly ?string $timeControlJson = null,
        public readonly ?string $clockStateJson = null,
        public readonly ?string $completedAt = null,
    ) {}
}
