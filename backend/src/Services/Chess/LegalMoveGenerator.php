<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class LegalMoveGenerator
{
    public function __construct(
        private PieceMovement $movement,
        private PositionAnalyzer $positionAnalyzer,
    ) {}

    /**
     * @param array<int, array<int, string|null>> $board
     * @return array<string, list<string>>
     */
    public function generate(array $board, string $activeColor): array
    {
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

                $destinations = $this->legalDestinations($board, $from, $piece, $activeColor);
                if ($destinations !== []) {
                    $legalMoves[$from->algebraic] = $destinations;
                }
            }
        }

        return $legalMoves;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @return list<string>
     */
    private function legalDestinations(array $board, Coordinate $from, string $piece, string $activeColor): array
    {
        $destinations = [];
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                $to = Coordinate::fromAlgebraic($this->squareName($row, $col));
                if ($to === null) {
                    continue;
                }

                $move = new Move($from, $to, $piece, null, 0);
                if (!$this->movement->isLegal($board, $move)) {
                    continue;
                }

                $candidate = $this->movePiece($board, $move);
                if (!$this->positionAnalyzer->isKingInCheck($candidate, $activeColor)) {
                    $destinations[] = $to->algebraic;
                }
            }
        }

        return $destinations;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @return array<int, array<int, string|null>>
     */
    private function movePiece(array $board, Move $move): array
    {
        $board[$move->to->row][$move->to->col] = $move->piece;
        $board[$move->from->row][$move->from->col] = null;

        return $board;
    }

    private function squareName(int $row, int $col): string
    {
        return chr(ord('a') + $col) . (8 - $row);
    }
}
