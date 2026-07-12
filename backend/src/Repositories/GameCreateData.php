<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

final class GameCreateData
{
    public function __construct(
        public readonly int $ownerUserId,
        public readonly string $status,
        public readonly string $currentStateJson,
        public readonly string $whiteLabel = 'White',
        public readonly string $blackLabel = 'Black',
        public readonly string $whitePlayerType = 'local_human',
        public readonly string $blackPlayerType = 'local_human',
        public readonly ?string $result = null,
        public readonly ?string $terminationReason = null,
        public readonly ?string $timeControlJson = null,
        public readonly ?string $clockStateJson = null,
        public readonly ?string $completedAt = null,
    ) {}
}
