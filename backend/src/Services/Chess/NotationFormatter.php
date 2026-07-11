<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class NotationFormatter
{
    private const PROMOTION_PIECES = [
        'queen' => 'Q',
        'rook' => 'R',
        'bishop' => 'B',
        'knight' => 'N',
    ];

    /** @param array<string, mixed> $state */
    public function fen(array $state): string
    {
        return implode('/', array_map(
            fn(array $row): string => $this->fenRow($row),
            $state['board'],
        )) . ' '
            . (($state['activeColor'] ?? 'white') === 'white' ? 'w' : 'b') . ' '
            . $this->castlingRights($state['castlingRights'] ?? []) . ' '
            . ($state['enPassantTarget'] ?? '-') . ' '
            . ($state['halfmoveClock'] ?? 0) . ' '
            . ($state['fullmoveNumber'] ?? 1);
    }

    public function coordinate(Move $move): string
    {
        return $move->from->algebraic . $move->to->algebraic . strtolower($this->promotionLetter($move));
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function san(array $before, array $after, Move $move, bool $isCapture, bool $isCastle): string
    {
        $suffix = $this->checkSuffix($after);
        if ($isCastle) {
            return ($move->to->col === 6 ? 'O-O' : 'O-O-O') . $suffix;
        }

        $pieceType = $move->piece[1];
        if ($pieceType === 'p') {
            return $this->pawnSan($move, $isCapture) . $this->promotionSan($move) . $suffix;
        }

        return $this->pieceLetter($pieceType)
            . $this->disambiguation($before, $move)
            . ($isCapture ? 'x' : '')
            . $move->to->algebraic
            . $suffix;
    }

    /** @param array<int, string|null> $row */
    private function fenRow(array $row): string
    {
        $fen = '';
        $empty = 0;
        foreach ($row as $piece) {
            if ($piece === null) {
                $empty++;
                continue;
            }
            if ($empty > 0) {
                $fen .= (string) $empty;
                $empty = 0;
            }
            $fen .= $this->fenPiece($piece);
        }
        if ($empty > 0) {
            $fen .= (string) $empty;
        }

        return $fen;
    }

    private function fenPiece(string $piece): string
    {
        $letter = $this->pieceLetter($piece[1]);

        return $piece[0] === 'w' ? $letter : strtolower($letter);
    }

    /** @param array<string, mixed> $rights */
    private function castlingRights(array $rights): string
    {
        $fen = '';
        $fen .= ($rights['white']['kingSide'] ?? false) ? 'K' : '';
        $fen .= ($rights['white']['queenSide'] ?? false) ? 'Q' : '';
        $fen .= ($rights['black']['kingSide'] ?? false) ? 'k' : '';
        $fen .= ($rights['black']['queenSide'] ?? false) ? 'q' : '';

        return $fen === '' ? '-' : $fen;
    }

    private function pawnSan(Move $move, bool $isCapture): string
    {
        return ($isCapture ? $move->from->algebraic[0] . 'x' : '') . $move->to->algebraic;
    }

    private function promotionSan(Move $move): string
    {
        $letter = $this->promotionLetter($move);

        return $letter === '' ? '' : '=' . $letter;
    }

    private function promotionLetter(Move $move): string
    {
        return self::PROMOTION_PIECES[$move->promotion ?? ''] ?? '';
    }

    private function pieceLetter(string $type): string
    {
        return match ($type) {
            'k' => 'K',
            'q' => 'Q',
            'r' => 'R',
            'b' => 'B',
            'n' => 'N',
            'p' => 'P',
            default => '',
        };
    }

    /** @param array<string, mixed> $state */
    private function disambiguation(array $state, Move $move): string
    {
        $competitors = $this->samePieceCompetitors($state, $move);
        if ($competitors === []) {
            return '';
        }

        $sameFile = false;
        $sameRank = false;
        foreach ($competitors as $from) {
            $sameFile = $sameFile || $from[0] === $move->from->algebraic[0];
            $sameRank = $sameRank || $from[1] === $move->from->algebraic[1];
        }

        if (!$sameFile) {
            return $move->from->algebraic[0];
        }
        if (!$sameRank) {
            return $move->from->algebraic[1];
        }

        return $move->from->algebraic;
    }

    /**
     * @param array<string, mixed> $state
     * @return list<string>
     */
    private function samePieceCompetitors(array $state, Move $move): array
    {
        $competitors = [];
        foreach (($state['legalMoves'] ?? []) as $from => $destinations) {
            if ($from === $move->from->algebraic || !in_array($move->to->algebraic, $destinations, true)) {
                continue;
            }
            $coordinate = Coordinate::fromAlgebraic((string) $from);
            if ($coordinate === null) {
                continue;
            }
            $piece = $state['board'][$coordinate->row][$coordinate->col] ?? null;
            if ($piece === $move->piece) {
                $competitors[] = (string) $from;
            }
        }

        return $competitors;
    }

    /** @param array<string, mixed> $state */
    private function checkSuffix(array $state): string
    {
        if (($state['terminationReason'] ?? null) === 'checkmate') {
            return '#';
        }
        if (($state['kingInCheck'] ?? null) === ($state['activeColor'] ?? null)) {
            return '+';
        }

        return '';
    }
}
