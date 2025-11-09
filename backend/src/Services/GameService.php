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

        $attemptingColor = substr($piece, 0, 1) === 'w' ? 'white' : 'black';
        if ($attemptingColor !== $state['activeColor']) {
            $state['lastMessage'] = "It's not {$attemptingColor}'s turn.";
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

        $state = $this->applyMove($state, $move);

        return $state;
    }

    /**
     * @return array<mixed>
     */
    private function applyMove(array $state, array $move): array
    {
        $from = $move['from'] ?? null;

        // a) Get to and from column indices
        $to = $move['to'] ?? null;
        $toCol = ord($to[0]) - ord('a');
        $toRow = 8 - (int)$to[1];
        $fromCol = ord($from[0]) - ord('a');
        $fromRow = 8 - (int)$from[1];
        $piece = $state['board'][$fromRow][$fromCol];
        
        // TODO: Implement castling
        if (substr($piece, 1, 1) == 'k') {
            $castle = $this->castling($state, $move); # either returns information about the two squares to be updated, or false that its not castling
        }

        // TODO: Check legality of the move - right now always returns true
        $legalMove = $this->checkMoveLegality($state, $move); # placeholder function for now

        if ($legalMove) {
            // b) Put piece in new location
            $state['board'][$toRow][$toCol] = $state['board'][$fromRow][$fromCol];
            // c) Remove piece at square it started at
            $state['board'][$fromRow][$fromCol] = null;
            $state['moveHistory'][] = $move;
            $state['activeColor'] = $state['activeColor'] === 'white' ? 'black' : 'white';
            $state['lastMessage'] = 'Move successfully made, should have moved piece to new square';
        } else {
            $state['lastMessage'] = 'Illegal move.';
            $state['isValidMove'] = false;
            return $state;
        }

        $this->store->saveState($state);
        
        return $state;
    }

    /**
     * @return array<mixed>
     */
    private function castling(array $state, array $move): ?array
    {
        // TODO: Implement castling logic - right now returns null which means no castling detected
        return null;
    }

    private function checkMoveLegality(array $state, array $move): bool
    {
        // TODO: Implement actual chess move legality checking
        return true;
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
