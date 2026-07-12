<?php

declare(strict_types=1);

namespace SoloChess\Engine;

use InvalidArgumentException;

final class EngineMoveProposal
{
    private const PROMOTIONS = ['queen', 'rook', 'bishop', 'knight'];

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $promotion = null,
    ) {
        if (!self::isSquare($from) || !self::isSquare($to)) {
            throw new InvalidArgumentException('Engine move proposals must use algebraic coordinates.');
        }
        if ($promotion !== null && !in_array($promotion, self::PROMOTIONS, true)) {
            throw new InvalidArgumentException('Engine promotion must be queen, rook, bishop, or knight.');
        }
    }

    /** @return array{from: string, to: string, promotion?: string} */
    public function toMovePayload(): array
    {
        $payload = ['from' => $this->from, 'to' => $this->to];
        if ($this->promotion !== null) {
            $payload['promotion'] = $this->promotion;
        }

        return $payload;
    }

    public function coordinate(): string
    {
        return $this->from . $this->to . ($this->promotion === null ? '' : $this->promotion[0]);
    }

    private static function isSquare(string $value): bool
    {
        return preg_match('/^[a-h][1-8]$/', $value) === 1;
    }
}
