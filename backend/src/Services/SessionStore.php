<?php

declare(strict_types=1);

namespace SoloChess\Services;

final class SessionStore
{
    private const SESSION_KEY = 'solo_chess_state';
    private const AUTH_USER_ID_KEY = 'solo_chess_auth_user_id';
    private const CURRENT_GAME_ID_KEY = 'solo_chess_current_game_id';

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

    public function saveAuthenticatedUserId(int $userId): void
    {
        $_SESSION[self::AUTH_USER_ID_KEY] = $userId;
    }

    public function getAuthenticatedUserId(): ?int
    {
        $userId = $_SESSION[self::AUTH_USER_ID_KEY] ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public function clearAuthenticatedUser(): void
    {
        unset($_SESSION[self::AUTH_USER_ID_KEY]);
    }

    public function saveCurrentGameId(int $gameId): void
    {
        $_SESSION[self::CURRENT_GAME_ID_KEY] = $gameId;
    }

    public function getCurrentGameId(): ?int
    {
        $gameId = $_SESSION[self::CURRENT_GAME_ID_KEY] ?? null;

        return is_int($gameId) && $gameId > 0 ? $gameId : null;
    }

    public function clearCurrentGame(): void
    {
        unset($_SESSION[self::CURRENT_GAME_ID_KEY]);
    }
}
