<?php

declare(strict_types=1);

namespace SoloChess\Services\Chess;

final class GameStateFactory
{
    /** @return array<string, mixed> */
    public function create(): array
    {
        return [
            'board' => $this->startingBoard(),
            'moveHistory' => [],
            'activeColor' => 'white',
            'kingInCheck' => null,
            'capturedWhite' => [],
            'capturedBlack' => [],
            'lastMessage' => 'Session ready. Implement chess logic inside GameService.',
        ];
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
}
