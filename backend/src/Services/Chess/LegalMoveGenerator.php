<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class LegalMoveGenerator
{
    public function __construct(
        private PieceMovement $movement,
        private PositionAnalyzer $positionAnalyzer,
        private CastlingResolver $castlingResolver,
    ) {}

    /**
     * @param array<string, mixed> $state
     * @return array<string, list<string>>
     */
    public function generate(array $state): array
    {
        if (($state['gameStatus'] ?? 'active') === 'finished') {
            return [];
        }

        $board = $state['board'];
        $activeColor = $state['activeColor'];
        $legalMoves = [];
        $activePrefix = $activeColor === 'white' ? 'w' : 'b';

        foreach ($board as $row => $squares) {
            foreach ($squares as $col => $piece) {
                if ($piece === null || $piece[0] !== $activePrefix) {
                    continue;
                }

                $from = Coordinate::fromAlgebraic($this->squareName($row, $col));
                if ($from === null) {
                    continue;
                }

                $destinations = $this->legalDestinations($state, $from, $piece);
                if ($destinations !== []) {
                    $legalMoves[$from->algebraic] = $destinations;
                }
            }
        }

        return $legalMoves;
    }

    /**
     * @param array<string, mixed> $state
     * @return list<string>
     */
    private function legalDestinations(array $state, Coordinate $from, string $piece): array
    {
        $board = $state['board'];
        $destinations = [];
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                $to = Coordinate::fromAlgebraic($this->squareName($row, $col));
                if ($to === null) {
                    continue;
                }

                $move = new Move($from, $to, $piece, null, 0);
                if (!$this->isLegalCandidate($state, $move)) {
                    continue;
                }

                $candidate = $this->movePiece($board, $move, $this->enPassantCapturedSquare($state, $move));
                if (!$this->positionAnalyzer->isKingInCheck($candidate, $state['activeColor'])) {
                    $destinations[] = $to->algebraic;
                }
            }
        }

        return $destinations;
    }

    /** @param array<string, mixed> $state */
    private function isLegalCandidate(array $state, Move $move): bool
    {
        return $this->movement->isLegal($state['board'], $move)
            || $this->castlingResolver->resolve($state['board'], $move, $state['activeColor'], $state['castlingRights']) !== null
            || $this->isEnPassantMove($state, $move);
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array{int, int}|null $enPassantCapturedSquare
     * @return array<int, array<int, string|null>>
     */
    private function movePiece(array $board, Move $move, ?array $enPassantCapturedSquare): array
    {
        $board[$move->to->row][$move->to->col] = $move->piece;
        $board[$move->from->row][$move->from->col] = null;
        if ($enPassantCapturedSquare !== null) {
            $board[$enPassantCapturedSquare[0]][$enPassantCapturedSquare[1]] = null;
        }

        return $board;
    }

    /** @param array<string, mixed> $state */
    private function isEnPassantMove(array $state, Move $move): bool
    {
        return $this->enPassantCapturedSquare($state, $move) !== null;
    }

    /** @param array<string, mixed> $state */
    private function enPassantCapturedSquare(array $state, Move $move): ?array
    {
        if ($move->piece[1] !== 'p' || $move->to->algebraic !== ($state['enPassantTarget'] ?? null)) {
            return null;
        }
        if (!$this->isEnPassantDiagonal($state, $move)) {
            return null;
        }

        $captureRow = $move->from->row;
        $captured = $state['board'][$captureRow][$move->to->col] ?? null;
        if (!is_string($captured) || $captured[1] !== 'p' || $captured[0] === $move->piece[0]) {
            return null;
        }

        return [$captureRow, $move->to->col];
    }

    /** @param array<string, mixed> $state */
    private function isEnPassantDiagonal(array $state, Move $move): bool
    {
        $direction = $move->piece[0] === 'w' ? -1 : 1;

        return $move->to->row - $move->from->row === $direction
            && abs($move->to->col - $move->from->col) === 1
            && $state['board'][$move->to->row][$move->to->col] === null;
    }

    private function squareName(int $row, int $col): string
    {
        return chr(ord('a') + $col) . (8 - $row);
    }
}
