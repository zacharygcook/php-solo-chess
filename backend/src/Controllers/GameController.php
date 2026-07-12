<?php

declare(strict_types=1);

namespace SoloChess\Controllers;

use SoloChess\Http\JsonResponse;
use SoloChess\Services\GameService;

final class GameController
{
    private GameService $service;

    public function __construct(?GameService $service = null)
    {
        $this->service = $service ?? GameService::default();
    }

    public static function default(): self
    {
        return new self();
    }

    public function sessionState(): void
    {
        $this->send($this->sessionStateResult());
    }

    public function submitMove(array $input): void
    {
        $this->send($this->submitMoveResult($input));
    }

    public function reset(): void
    {
        $this->send($this->resetResult());
    }

    public function createGame(array $input): void
    {
        $this->send($this->createGameResult($input));
    }

    public function resign(array $input): void
    {
        $this->send($this->resignResult($input));
    }

    public function offerDraw(array $input): void
    {
        $this->send($this->offerDrawResult($input));
    }

    public function acceptDraw(array $input): void
    {
        $this->send($this->acceptDrawResult($input));
    }

    public function claimDraw(array $input): void
    {
        $this->send($this->claimDrawResult($input));
    }

    public function abandon(array $input): void
    {
        $this->send($this->abandonResult($input));
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

    /** @return array{payload: array<string, mixed>, status: int} */
    public function sessionStateResult(): array
    {
        $state = $this->service->getSessionState();

        return self::result(true, $state['lastMessage'] ?? null, $state);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function submitMoveResult(array $input): array
    {
        $state = $this->service->submitMove($input);
        $success = $state['isValidMove'] ?? true;

        return self::result(
            $success === true,
            $state['lastMessage'] ?? 'Move stored.',
            self::withoutInternalFlags($state),
        );
    }

    /** @return array{payload: array<string, mixed>, status: int} */
    public function resetResult(): array
    {
        return self::result(true, 'Session reset.', $this->service->resetGame());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function createGameResult(array $input): array
    {
        try {
            return self::result(true, 'Game created.', $this->service->createGame($input), 201);
        } catch (\InvalidArgumentException $error) {
            return self::result(false, $error->getMessage(), $this->service->getSessionState(), 422);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function resignResult(array $input): array
    {
        return $this->actionResult($this->service->resignGame($input));
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function offerDrawResult(array $input): array
    {
        return $this->actionResult($this->service->offerDraw($input));
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function acceptDrawResult(array $input): array
    {
        return $this->actionResult($this->service->acceptDraw($input));
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function claimDrawResult(array $input): array
    {
        return $this->actionResult($this->service->claimDraw($input));
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function abandonResult(array $input): array
    {
        return $this->actionResult($this->service->abandonGame($input));
    }

    /**
     * @param array<string, mixed> $state
     * @return array{payload: array<string, mixed>, status: int}
     */
    private function actionResult(array $state): array
    {
        $success = $state['isValidAction'] ?? true;

        return self::result(
            $success === true,
            $state['lastMessage'] ?? 'Action stored.',
            self::withoutInternalFlags($state),
        );
    }

    /**
     * @param array<string, mixed> $state
     * @return array{payload: array<string, mixed>, status: int}
     */
    private static function result(bool $success, ?string $message, array $state, int $status = 200): array
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

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function withoutInternalFlags(array $state): array
    {
        return array_diff_key($state, array_flip(['isValidMove', 'isValidAction']));
    }

    /** @param array{payload: array<string, mixed>, status: int} $result */
    private function send(array $result): void
    {
        JsonResponse::send($result['payload'], $result['status']);
    }
}
