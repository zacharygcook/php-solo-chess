<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class CastlingResolver
{
    /**
     * @param array<int, array<int, string|null>> $board
     * @return array{rookFrom: array{int, int}, rookTo: array{int, int}}|null
     */
    public function resolve(array $board, Move $move, string $activeColor): ?array
    {
        $homeRow = $activeColor === 'white' ? 7 : 0;
        if (!$this->isHomeKingMove($move, $homeRow)) {
            return null;
        }

        $layout = $this->layoutFor($homeRow, $move->to->col);
        if ($layout === null) {
            return null;
        }

        $rookCode = $activeColor === 'white' ? 'wr' : 'br';
        if (!$this->hasRook($board, $layout['rookFrom'], $rookCode) || !$this->pathIsClear($board, $homeRow, $layout['clear'])) {
            return null;
        }

        return ['rookFrom' => $layout['rookFrom'], 'rookTo' => $layout['rookTo']];
    }

    private function isHomeKingMove(Move $move, int $homeRow): bool
    {
        return $move->piece[1] === 'k'
            && $move->from->row === $homeRow
            && $move->from->col === 4
            && $move->to->row === $homeRow;
    }

    /** @return array{rookFrom: array{int, int}, rookTo: array{int, int}, clear: list<int>}|null */
    private function layoutFor(int $homeRow, int $targetCol): ?array
    {
        return match ($targetCol) {
            6 => ['rookFrom' => [$homeRow, 7], 'rookTo' => [$homeRow, 5], 'clear' => [5, 6]],
            2 => ['rookFrom' => [$homeRow, 0], 'rookTo' => [$homeRow, 3], 'clear' => [1, 2, 3]],
            default => null,
        };
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array{int, int} $rookSquare
     */
    private function hasRook(array $board, array $rookSquare, string $rookCode): bool
    {
        return $board[$rookSquare[0]][$rookSquare[1]] === $rookCode;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param list<int> $clearCols
     */
    private function pathIsClear(array $board, int $homeRow, array $clearCols): bool
    {
        foreach ($clearCols as $col) {
            if ($board[$homeRow][$col] !== null) {
                return false;
            }
        }

        return true;
    }
}
