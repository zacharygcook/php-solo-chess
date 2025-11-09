<?php

declare(strict_types=1);

namespace SoloChess\Services;

final class GameService
{
    public function __construct(private SessionStore $store)
    {
    }

    /**
     * @return array<mixed>
     */
    public function getSessionState(): array
    {
        $state = $this->store->getState();

        if (empty($state)) {
            $state = $this->createInitialState();
            $this->store->saveState($state);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function submitMove(array $payload): array
    {
        $state = $this->getSessionState();

        $from = $payload['from'] ?? null;
        if (!$from) {
            $state['lastMessage'] = "Couldn't find a 'from' coordinate";
            $state['isValidMove'] = false;
            // $this->store->saveState($state); # if we decide we want to persist the error state
            return $state;
        } elseif (strlen($from) !== 2 || !preg_match('/^[a-h][1-8]$/', $from)) {
            $state['lastMessage'] = "Not a valid 'from' option";
            $state['isValidMove'] = false;
            return $state;
        }

        // Convert to indices
        /*
        *  Basically making from column letter into an integer 0->7
        *  Then converting row digit from its correct chess number, to the correct index in our nested array
        *  See how the board is setup from buildStartingBoard()
        */
        $fromCol = ord($from[0]) - ord('a');
        $fromRow = 8 - (int)$from[1];
        $piece = $state['board'][$fromRow][$fromCol] ?? null;

        if (!$piece) {
            $state['lastMessage'] = "No piece at 'from' coordinate";
            $state['isValidMove'] = false;
            return $state;
        }

        $move = [
            'from' => $payload['from'] ?? null,
            'to' => $payload['to'] ?? null,
            'promotion' => $payload['promotion'] ?? null,
            'timestamp' => time(),
            'note' => 'TODO: Replace with validated move + board mutation.',
        ];

        $state['moveHistory'][] = $move;
        $state['activeColor'] = $state['activeColor'] === 'white' ? 'black' : 'white';
        $state['lastMessage'] = 'Move stored. Plug your chess logic into GameService::applyMove().';

        $this->store->saveState($state);

        return $state;
    }

    /**
     * @return array<mixed>
     */
    public function resetGame(): array
    {
        $state = $this->createInitialState();
        $this->store->saveState($state);

        return $state;
    }

    /**
     * @return array<mixed>
     */
    public function loadFen(string $fen): array
    {
        $state = $this->getSessionState();
        $state['lastMessage'] = 'FEN loading not implemented. Wire your parser into GameService::loadFen().';
        $this->store->saveState($state);

        return $state;
    }

    /**
     * @return array<mixed>
     */
    private function createInitialState(): array
    {
        return [
            'board' => $this->buildStartingBoard(),
            'moveHistory' => [],
            'activeColor' => 'white',
            'capturedWhite' => [],
            'capturedBlack' => [],
            'lastMessage' => 'Session ready. Implement chess logic inside GameService.'
        ];
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function buildStartingBoard(): array
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
