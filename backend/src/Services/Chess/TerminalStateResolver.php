<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class TerminalStateResolver
{
    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function resolveAfterMove(array $state): array
    {
        $state = $this->resetActiveOutcome($state);

        if ($this->hasNoLegalMoves($state)) {
            return ($state['kingInCheck'] === $state['activeColor'])
                ? $this->checkmate($state)
                : $this->draw($state, 'stalemate', 'Stalemate.');
        }

        if ($this->isDeadPosition($state['board'])) {
            return $this->draw($state, 'deadPosition', 'Draw by dead position.');
        }

        return $this->withClaimActions($state);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function resetActiveOutcome(array $state): array
    {
        $state['gameStatus'] = 'active';
        $state['result'] = null;
        $state['terminationReason'] = null;
        $state['drawClaims'] = [];
        $state['availableActions'] = [];

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function hasNoLegalMoves(array $state): bool
    {
        return ($state['legalMoves'] ?? []) === [];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function checkmate(array $state): array
    {
        $winner = $state['activeColor'] === 'white' ? 'black' : 'white';
        $state['gameStatus'] = 'finished';
        $state['result'] = $winner === 'white' ? '1-0' : '0-1';
        $state['terminationReason'] = 'checkmate';
        $state['legalMoves'] = [];
        $state['lastMessage'] = 'Checkmate. ' . ucfirst($winner) . ' wins.';

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function draw(array $state, string $reason, string $message): array
    {
        $state['gameStatus'] = 'finished';
        $state['result'] = '1/2-1/2';
        $state['terminationReason'] = $reason;
        $state['legalMoves'] = [];
        $state['lastMessage'] = $message;

        return $state;
    }

    /** @param array<int, array<int, string|null>> $board */
    private function isDeadPosition(array $board): bool
    {
        $pieces = $this->remainingPieces($board);

        if ($pieces === []) {
            return true;
        }

        if (count($pieces) === 1) {
            return in_array($pieces[0]['piece'][1], ['b', 'n'], true);
        }

        return $this->hasOnlySameColorBishops($pieces);
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @return list<array{piece: string, squareColor: int}>
     */
    private function remainingPieces(array $board): array
    {
        $pieces = [];
        foreach ($board as $row => $squares) {
            foreach ($squares as $col => $piece) {
                if ($piece === null || $piece[1] === 'k') {
                    continue;
                }
                $pieces[] = [
                    'piece' => $piece,
                    'squareColor' => ($row + $col) % 2,
                ];
            }
        }

        return $pieces;
    }

    /** @param list<array{piece: string, squareColor: int}> $pieces */
    private function hasOnlySameColorBishops(array $pieces): bool
    {
        if ($pieces === []) {
            return false;
        }

        $squareColor = $pieces[0]['squareColor'];
        foreach ($pieces as $piece) {
            if ($piece['piece'][1] !== 'b' || $piece['squareColor'] !== $squareColor) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function withClaimActions(array $state): array
    {
        $claims = [];
        if ($state['halfmoveClock'] >= 100) {
            $claims[] = 'fiftyMoveRule';
        }
        if ($this->currentPositionCount($state['positionHistory']) >= 3) {
            $claims[] = 'threefoldRepetition';
        }

        $state['drawClaims'] = $claims;
        $state['availableActions'] = $claims === [] ? [] : ['claimDraw'];

        return $state;
    }

    /** @param array<int, string> $positionHistory */
    private function currentPositionCount(array $positionHistory): int
    {
        $current = end($positionHistory);
        if (!is_string($current)) {
            return 0;
        }

        return count(array_filter(
            $positionHistory,
            static fn(string $position): bool => $position === $current,
        ));
    }
}
