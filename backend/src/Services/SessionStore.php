<?php

declare(strict_types=1);

namespace SoloChess\Services;

final class SessionStore
{
    private const SESSION_KEY = 'solo_chess_state';

    /**
     * @return array<mixed>
     */
    public function getState(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    /**
     * @param array<mixed> $state
     */
    public function saveState(array $state): void
    {
        $_SESSION[self::SESSION_KEY] = $state;
    }

    public function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
