<?php

declare(strict_types=1);

namespace SoloChess\Controllers;

use SoloChess\Http\JsonResponse;
use SoloChess\Services\GameService;
use SoloChess\Services\SessionStore;

final class GameController
{
    private GameService $service;

    public function __construct(?GameService $service = null)
    {
        $this->service = $service ?? new GameService(new SessionStore());
    }

    public function sessionState(): void
    {
        $state = $this->service->getSessionState();

        JsonResponse::send([
            'success' => true,
            'message' => $state['lastMessage'] ?? null,
            'state' => $state,
        ]);
    }

    public function submitMove(array $input): void
    {
        $state = $this->service->submitMove($input);

        JsonResponse::send([
            'success' => $state['isValidMove'] ?? true,
            'message' => $state['lastMessage'] ?? 'Move stored.',
            'state' => array_diff_key($state, array_flip(['isValidMove'])),
        ]);
    }

    public function reset(): void
    {
        $state = $this->service->resetGame();

        JsonResponse::send([
            'success' => true,
            'message' => 'Session reset.',
            'state' => $state,
        ]);
    }

    public function loadFen(string $fen): void
    {
        $state = $this->service->loadFen($fen);

        JsonResponse::send([
            'success' => false,
            'message' => $state['lastMessage'] ?? 'FEN loader placeholder.',
            'state' => $state,
        ], 202);
    }
}
