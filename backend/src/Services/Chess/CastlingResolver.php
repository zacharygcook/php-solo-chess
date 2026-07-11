<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class CastlingResolver
{
    public function __construct(private PositionAnalyzer $positionAnalyzer) {}

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array<string, array<string, bool>> $castlingRights
     * @return array{rookFrom: array{int, int}, rookTo: array{int, int}}|null
     */
    public function resolve(array $board, Move $move, string $activeColor, array $castlingRights): ?array
    {
        $homeRow = $activeColor === 'white' ? 7 : 0;
        if (!$this->isHomeKingMove($move, $homeRow)) {
            return null;
        }

        $side = $move->to->col === 6 ? 'kingSide' : 'queenSide';
        $layout = $this->layoutFor($homeRow, $move->to->col);
        if ($layout === null) {
            return null;
        }

        $rookCode = $activeColor === 'white' ? 'wr' : 'br';
        if (!$this->canUseLayout($board, $move, $activeColor, $castlingRights, $side, $layout, $rookCode)) {
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

    /** @return array{rookFrom: array{int, int}, rookTo: array{int, int}, clear: list<int>, kingPath: list<int>}|null */
    private function layoutFor(int $homeRow, int $targetCol): ?array
    {
        return match ($targetCol) {
            6 => ['rookFrom' => [$homeRow, 7], 'rookTo' => [$homeRow, 5], 'clear' => [5, 6], 'kingPath' => [4, 5, 6]],
            2 => ['rookFrom' => [$homeRow, 0], 'rookTo' => [$homeRow, 3], 'clear' => [1, 2, 3], 'kingPath' => [4, 3, 2]],
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
     * @param array<string, array<string, bool>> $castlingRights
     * @param array{rookFrom: array{int, int}, rookTo: array{int, int}, clear: list<int>, kingPath: list<int>} $layout
     */
    private function canUseLayout(array $board, Move $move, string $activeColor, array $castlingRights, string $side, array $layout, string $rookCode): bool
    {
        return ($castlingRights[$activeColor][$side] ?? false) === true
            && $this->hasRook($board, $layout['rookFrom'], $rookCode)
            && $this->pathIsClear($board, $move->from->row, $layout['clear'])
            && $this->kingPathIsSafe($board, $move, $activeColor, $layout['kingPath']);
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

    /**
     * @param array<int, array<int, string|null>> $board
     * @param list<int> $kingPath
     */
    private function kingPathIsSafe(array $board, Move $move, string $activeColor, array $kingPath): bool
    {
        foreach ($kingPath as $col) {
            $candidate = $board;
            $candidate[$move->from->row][$move->from->col] = null;
            $candidate[$move->from->row][$col] = $move->piece;
            if ($this->positionAnalyzer->isKingInCheck($candidate, $activeColor)) {
                return false;
            }
        }

        return true;
    }
}
