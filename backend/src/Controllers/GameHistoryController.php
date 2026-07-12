<?php

declare(strict_types=1);

namespace SoloChess\Controllers;

use SoloChess\Http\JsonResponse;
use SoloChess\Services\GameHistoryService;
use SoloChess\Services\SessionStore;

final class GameHistoryController
{
    public function __construct(
        private GameHistoryService $history,
        private SessionStore $sessions,
    ) {}

    public static function default(): self
    {
        $sessions = new SessionStore();

        return new self(GameHistoryService::default($sessions), $sessions);
    }

    public function history(): void
    {
        $this->send($this->historyResult());
    }

    public function open(?int $gameId): void
    {
        $this->send($this->openResult($gameId));
    }

    public function replay(?int $gameId): void
    {
        $this->send($this->replayResult($gameId));
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    public function historyResult(): array
    {
        if (!$this->isAuthenticated()) {
            return self::result(false, 'Log in to view saved games.', ['games' => []], 401);
        }

        return self::result(true, 'Saved games loaded.', [
            'games' => $this->history->listForAuthenticatedUser(),
        ]);
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    public function openResult(?int $gameId): array
    {
        $opened = $this->openedGame($gameId);
        if ($opened['status'] !== 200) {
            return $opened;
        }

        $state = $opened['payload']['state'];

        return self::result(true, 'Saved game loaded.', [
            'game' => $state['game'],
            'gameState' => $state['state'],
            'replay' => $state['replay'],
        ]);
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    public function replayResult(?int $gameId): array
    {
        $opened = $this->openedGame($gameId);
        if ($opened['status'] !== 200) {
            return $opened;
        }

        $state = $opened['payload']['state'];

        return self::result(true, 'Replay loaded.', [
            'game' => $state['game'],
            'replay' => $state['replay'],
        ]);
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    private function openedGame(?int $gameId): array
    {
        if ($gameId === null || $gameId < 1) {
            return self::result(false, 'Provide a valid game id.', [], 422);
        }
        if (!$this->isAuthenticated()) {
            return self::result(false, 'Log in to view saved games.', [], 401);
        }

        $opened = $this->history->openForAuthenticatedUser($gameId);
        if ($opened === null) {
            return self::result(false, 'Saved game not found.', [], 404);
        }

        return self::result(true, 'Saved game loaded.', $opened);
    }

    private function isAuthenticated(): bool
    {
        return $this->sessions->getAuthenticatedUserId() !== null;
    }

    /**
     * @param array<string, mixed> $state
     * @return array{payload: array<string, mixed>, status: int}
     */
    private static function result(bool $success, string $message, array $state, int $status = 200): array
    {
        return [
            'payload' => [
                'success' => $success,
                'message' => $message,
                'state' => $state,
            ],
            'status' => $status,
        ];
    }

    /** @param array{payload: array<string, mixed>, status: int} $result */
    private function send(array $result): void
    {
        JsonResponse::send($result['payload'], $result['status']);
    }
}
