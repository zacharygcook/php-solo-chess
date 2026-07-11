<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class GameStateFactory
{
    /** @return array<string, mixed> */
    public function create(): array
    {
        $board = $this->startingBoard();

        return [
            'board' => $board,
            'moveHistory' => [],
            'activeColor' => 'white',
            'kingInCheck' => null,
            'capturedWhite' => [],
            'capturedBlack' => [],
            'castlingRights' => $this->startingCastlingRights(),
            'enPassantTarget' => null,
            'halfmoveClock' => 0,
            'fullmoveNumber' => 1,
            'positionHistory' => [$this->positionKey($board, 'white', $this->startingCastlingRights(), null)],
            'gameStatus' => 'active',
            'result' => null,
            'terminationReason' => null,
            'drawClaims' => [],
            'availableActions' => [],
            'lastMessage' => 'Session ready. Implement chess logic inside GameService.',
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function normalize(array $state): array
    {
        if ($state === []) {
            return $this->create();
        }

        $state['castlingRights'] = is_array($state['castlingRights'] ?? null)
            ? $state['castlingRights']
            : $this->startingCastlingRights();
        $state['enPassantTarget'] = is_string($state['enPassantTarget'] ?? null) ? $state['enPassantTarget'] : null;
        $state['halfmoveClock'] = is_int($state['halfmoveClock'] ?? null) ? $state['halfmoveClock'] : 0;
        $state['fullmoveNumber'] = is_int($state['fullmoveNumber'] ?? null) ? $state['fullmoveNumber'] : 1;
        $state['positionHistory'] = is_array($state['positionHistory'] ?? null) && $state['positionHistory'] !== []
            ? $state['positionHistory']
            : [$this->positionKey($state['board'], $state['activeColor'], $state['castlingRights'], $state['enPassantTarget'])];

        return $this->withTerminalDefaults($state);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function withTerminalDefaults(array $state): array
    {
        $state['gameStatus'] = is_string($state['gameStatus'] ?? null) ? $state['gameStatus'] : 'active';
        $state['result'] = is_string($state['result'] ?? null) ? $state['result'] : null;
        $state['terminationReason'] = is_string($state['terminationReason'] ?? null) ? $state['terminationReason'] : null;
        $state['drawClaims'] = is_array($state['drawClaims'] ?? null) ? $state['drawClaims'] : [];
        $state['availableActions'] = is_array($state['availableActions'] ?? null) ? $state['availableActions'] : [];

        return $state;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array<string, mixed> $castlingRights
     */
    public function positionKey(array $board, string $activeColor, array $castlingRights, ?string $enPassantTarget): string
    {
        return implode('/', array_map(
            static fn(array $row): string => implode('', array_map(static fn(?string $piece): string => $piece ?? '--', $row)),
            $board,
        )) . ' ' . $activeColor . ' ' . json_encode($castlingRights) . ' ' . ($enPassantTarget ?? '-');
    }

    /** @return array<int, array<int, string|null>> */
    private function startingBoard(): array
    {
        return [
            ['br', 'bn', 'bb', 'bq', 'bk', 'bb', 'bn', 'br'],
            array_fill(0, 8, 'bp'),
            array_fill(0, 8, null),
            array_fill(0, 8, null),
            array_fill(0, 8, null),
            array_fill(0, 8, null),
            array_fill(0, 8, 'wp'),
            ['wr', 'wn', 'wb', 'wq', 'wk', 'wb', 'wn', 'wr'],
        ];
    }

    /** @return array<string, array<string, bool>> */
    private function startingCastlingRights(): array
    {
        return [
            'white' => ['kingSide' => true, 'queenSide' => true],
            'black' => ['kingSide' => true, 'queenSide' => true],
        ];
    }
}
