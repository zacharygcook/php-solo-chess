<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class PieceMovement
{
    /** @param array<int, array<int, string|null>> $board */
    public function isLegal(array $board, Move $move): bool
    {
        if ($move->from->row === $move->to->row && $move->from->col === $move->to->col) {
            return false;
        }

        $target = $board[$move->to->row][$move->to->col];
        if ($target !== null && $target[0] === $move->piece[0]) {
            return false;
        }
        if ($target !== null && $target[1] === 'k') {
            return false;
        }

        return match ($move->piece[1]) {
            'p' => $this->isLegalPawnMove($board, $move),
            'n' => $this->isKnightMove($move),
            'b' => $this->isDiagonalMove($board, $move),
            'r' => $this->isStraightMove($board, $move),
            'q' => $this->isDiagonalMove($board, $move) || $this->isStraightMove($board, $move),
            'k' => $this->isKingMove($move),
            default => false,
        };
    }

    /** @param array<int, array<int, string|null>> $board */
    public function attacksSquare(array $board, Move $move): bool
    {
        return match ($move->piece[1]) {
            'p' => $this->isPawnAttack($move),
            'n' => $this->isKnightMove($move),
            'b' => $this->isDiagonalMove($board, $move),
            'r' => $this->isStraightMove($board, $move),
            'q' => $this->isDiagonalMove($board, $move) || $this->isStraightMove($board, $move),
            'k' => $this->isKingMove($move),
            default => false,
        };
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isLegalPawnMove(array $board, Move $move): bool
    {
        $direction = $move->piece[0] === 'w' ? -1 : 1;
        $rowDelta = $move->to->row - $move->from->row;
        $colDelta = abs($move->to->col - $move->from->col);

        if ($colDelta === 1 && $rowDelta === $direction) {
            return $this->isPawnCapture($board, $move);
        }

        if ($colDelta !== 0) {
            return false;
        }

        return $this->isPawnAdvance($board, $move, $direction, $rowDelta);
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isPawnCapture(array $board, Move $move): bool
    {
        $target = $board[$move->to->row][$move->to->col];

        return $target !== null && $target[0] !== $move->piece[0];
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isPawnAdvance(array $board, Move $move, int $direction, int $rowDelta): bool
    {
        if ($board[$move->to->row][$move->to->col] !== null) {
            return false;
        }

        if ($rowDelta === $direction) {
            return true;
        }

        $startRow = $move->piece[0] === 'w' ? 6 : 1;

        return $move->from->row === $startRow
            && $rowDelta === 2 * $direction
            && $board[$move->from->row + $direction][$move->from->col] === null;
    }

    private function isPawnAttack(Move $move): bool
    {
        $direction = $move->piece[0] === 'w' ? -1 : 1;

        return $move->to->row - $move->from->row === $direction
            && abs($move->to->col - $move->from->col) === 1;
    }

    private function isKnightMove(Move $move): bool
    {
        $rowDelta = abs($move->to->row - $move->from->row);
        $colDelta = abs($move->to->col - $move->from->col);

        return ($rowDelta === 2 && $colDelta === 1) || ($rowDelta === 1 && $colDelta === 2);
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isDiagonalMove(array $board, Move $move): bool
    {
        $rowDelta = abs($move->to->row - $move->from->row);
        $colDelta = abs($move->to->col - $move->from->col);

        return $rowDelta > 0 && $rowDelta === $colDelta && $this->isPathClear($board, $move);
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isStraightMove(array $board, Move $move): bool
    {
        $sameRow = $move->from->row === $move->to->row;
        $sameCol = $move->from->col === $move->to->col;

        return $sameRow !== $sameCol && $this->isPathClear($board, $move);
    }

    private function isKingMove(Move $move): bool
    {
        $rowDelta = abs($move->to->row - $move->from->row);
        $colDelta = abs($move->to->col - $move->from->col);

        return max($rowDelta, $colDelta) === 1;
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isPathClear(array $board, Move $move): bool
    {
        $rowStep = $move->to->row <=> $move->from->row;
        $colStep = $move->to->col <=> $move->from->col;
        $row = $move->from->row + $rowStep;
        $col = $move->from->col + $colStep;

        while ($row !== $move->to->row || $col !== $move->to->col) {
            if ($board[$row][$col] !== null) {
                return false;
            }
            $row += $rowStep;
            $col += $colStep;
        }

        return true;
    }
}
