<?php

declare(strict_types=1);

namespace SoloChess\Services;

final class PgnVerificationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly array $errors,
        public readonly ?string $finalFen,
        public readonly ?string $result,
        public readonly int $moveCount,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
