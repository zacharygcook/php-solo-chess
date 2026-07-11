<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class PositionAnalyzer
{
    public function __construct(private PieceMovement $movement) {}

    /** @param array<int, array<int, string|null>> $board */
    public function isKingInCheck(array $board, string $color): bool
    {
        $king = $this->findKing($board, $color);

        return $king === null || $this->isSquareAttacked($board, $king, self::opponent($color));
    }

    /** @param array<int, array<int, string|null>> $board */
    private function findKing(array $board, string $color): ?Coordinate
    {
        $kingCode = $color === 'white' ? 'wk' : 'bk';
        foreach ($board as $row => $squares) {
            foreach ($squares as $col => $piece) {
                if ($piece === $kingCode) {
                    return Coordinate::fromAlgebraic(chr(ord('a') + $col) . (8 - $row));
                }
            }
        }

        return null;
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isSquareAttacked(array $board, Coordinate $target, string $attackingColor): bool
    {
        $attackerPrefix = $attackingColor === 'white' ? 'w' : 'b';
        foreach ($board as $row => $squares) {
            foreach ($squares as $col => $piece) {
                if ($piece === null || $piece[0] !== $attackerPrefix) {
                    continue;
                }
                $from = Coordinate::fromAlgebraic(chr(ord('a') + $col) . (8 - $row));
                if ($from !== null && $this->movement->attacksSquare(
                    $board,
                    new Move($from, $target, $piece, null, 0),
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function opponent(string $color): string
    {
        return $color === 'white' ? 'black' : 'white';
    }
}
