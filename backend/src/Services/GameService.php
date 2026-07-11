<?php

declare(strict_types=1);

namespace SoloChess\Services;

use SoloChess\Services\Chess\CastlingResolver;
use SoloChess\Services\Chess\Coordinate;
use SoloChess\Services\Chess\GameStateFactory;
use SoloChess\Services\Chess\Move;
use SoloChess\Services\Chess\PieceMovement;
use SoloChess\Services\Chess\PositionAnalyzer;

final class GameService
{
    private GameStateFactory $stateFactory;
    private PieceMovement $movement;
    private PositionAnalyzer $positionAnalyzer;
    private CastlingResolver $castlingResolver;

    public function __construct(private SessionStore $store)
    {
        $this->stateFactory = new GameStateFactory();
        $this->movement = new PieceMovement();
        $this->positionAnalyzer = new PositionAnalyzer($this->movement);
        $this->castlingResolver = new CastlingResolver();
    }

    /** @return array<string, mixed> */
    public function getSessionState(): array
    {
        $state = $this->store->getState();
        if ($state === []) {
            $state = $this->stateFactory->create();
            $this->store->saveState($state);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitMove(array $payload): array
    {
        $state = $this->getSessionState();
        $from = Coordinate::fromAlgebraic($payload['from'] ?? null);
        if ($from === null) {
            $message = empty($payload['from']) ? "Couldn't find a 'from' coordinate" : "Not a valid 'from' option";

            return $this->reject($state, $message);
        }

        $to = Coordinate::fromAlgebraic($payload['to'] ?? null);
        if ($to === null) {
            return $this->reject($state, "Not a valid 'to' option");
        }

        $piece = $state['board'][$from->row][$from->col] ?? null;
        if (!is_string($piece)) {
            return $this->reject($state, "No piece at 'from' coordinate");
        }

        $movingColor = self::pieceColor($piece);
        if ($movingColor !== $state['activeColor']) {
            return $this->reject($state, "It's not {$movingColor}'s turn.");
        }

        $promotion = isset($payload['promotion']) && is_string($payload['promotion'])
            ? $payload['promotion']
            : null;
        $move = new Move($from, $to, $piece, $promotion, time());

        return $this->applyMove($state, $move);
    }

    /** @return array<string, mixed> */
    public function resetGame(): array
    {
        $state = $this->stateFactory->create();
        $this->store->saveState($state);

        return $state;
    }

    /** @return array<string, mixed> */
    public function loadFen(string $fen): array
    {
        $state = $this->getSessionState();
        $state['lastMessage'] = 'FEN loading not implemented. Wire your parser into GameService::loadFen().';
        $this->store->saveState($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function applyMove(array $state, Move $move): array
    {
        $board = $state['board'];
        $castle = $this->castlingResolver->resolve($board, $move, $state['activeColor']);
        if ($castle === null && !$this->movement->isLegal($board, $move)) {
            return $this->reject($state, 'Illegal move.');
        }

        $candidate = $this->movePiece($board, $move, $castle);
        $movingColor = $state['activeColor'];
        if ($this->positionAnalyzer->isKingInCheck($candidate, $movingColor)) {
            return $this->reject($state, "Move would leave {$movingColor} in check.");
        }

        $state['board'] = $candidate;
        $state['moveHistory'][] = $move->toHistoryRecord();
        $state['activeColor'] = self::opponent($movingColor);
        $inCheck = $this->positionAnalyzer->isKingInCheck($candidate, $state['activeColor']);
        $state['kingInCheck'] = $inCheck ? $state['activeColor'] : null;
        $state['lastMessage'] = $inCheck ? 'Check!' : ($castle === null ? 'Move successfully made.' : 'Castling move successfully made.');
        unset($state['isValidMove']);
        $this->store->saveState($state);

        return $state;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array{rookFrom: array{int, int}, rookTo: array{int, int}}|null $castle
     * @return array<int, array<int, string|null>>
     */
    private function movePiece(array $board, Move $move, ?array $castle): array
    {
        $board[$move->to->row][$move->to->col] = $move->piece;
        $board[$move->from->row][$move->from->col] = null;
        if ($castle !== null) {
            $board[$castle['rookTo'][0]][$castle['rookTo'][1]] = $board[$castle['rookFrom'][0]][$castle['rookFrom'][1]];
            $board[$castle['rookFrom'][0]][$castle['rookFrom'][1]] = null;
        }

        return $board;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function reject(array $state, string $message): array
    {
        $state['lastMessage'] = $message;
        $state['isValidMove'] = false;

        return $state;
    }

    private static function pieceColor(string $piece): string
    {
        return $piece[0] === 'w' ? 'white' : 'black';
    }

    private static function opponent(string $color): string
    {
        return $color === 'white' ? 'black' : 'white';
    }
}
