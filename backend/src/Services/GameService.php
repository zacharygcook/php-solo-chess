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
        $to = $payload['to'] ?? null;
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
        $toCol = ord($to[0]) - ord('a');
        $toRow = 8 - (int)$to[1];
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
            'from' => $from ?? null,
            'fromCol' => $fromCol ?? null,
            'fromRow' => $fromRow ?? null,
            'to' => $to ?? null,
            'toCol' => $toCol ?? null,
            'toRow' => $toRow ?? null,
            'piece' => $piece ?? null,
            'promotion' => $payload['promotion'] ?? null,
            'timestamp' => time(),
            'note' => 'TODO: Replace with validated move + board mutation.',
        ];

        $state = $this->applyMove($state, $move, $piece);

        return $state;
    }

    /**
     * @return array<mixed>
     */
    private function applyMove(array $state, array $move): array
    {
        // a) Get starting data organized
        $toCol = $move['toCol'];
        $toRow = $move['toRow'];
        $fromCol = $move['fromCol'];
        $fromRow = $move['fromRow'];
        $piece = $move['piece'];
        
        // TODO: Implement castling v2
        if (substr($piece, 1, 1) == 'k') {
            $castle = $this->castling($state, $move); # either returns information about the two squares to be updated, or false that its not castling
            if ($castle !== null) {
                // Perform the castling move
                $rookFrom = $castle['rookFormerPosition'];
                $rookTo = $castle['rookNewPosition'];
                
                // Move the king
                $state['board'][$toRow][$toCol] = $state['board'][$fromRow][$fromCol];
                $state['board'][$fromRow][$fromCol] = null;
                
                // Move the rook
                $state['board'][$rookTo[0]][$rookTo[1]] = $state['board'][$rookFrom[0]][$rookFrom[1]];
                $state['board'][$rookFrom[0]][$rookFrom[1]] = null;
                
                $state['moveHistory'][] = $move;
                $state['activeColor'] = $state['activeColor'] === 'white' ? 'black' : 'white';
                $state['lastMessage'] = 'Castling move successfully made.';
                
                $this->store->saveState($state);
                
                return $state;
            }
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
        // TODO: Implement castling logic v2
        // Version 1: Implement simple legality checks (king and rook are in starting positions and no pieces in between)
        // Version 2: Implement full legality checks (king/rook haven't moved, not in check, squares not attacked, etc)
        $tryCastle = false;
        $castleLegal = false;
        $toCol = $move['toCol'];
        $toRow = $move['toRow'];
        $fromCol = $move['fromCol'];
        $fromRow = $move['fromRow'];
        $piece = $move['piece'];
        
        if ($state['activeColor'] === 'white' && $toRow === 7 && $toCol === 6) {
            // See if valid short castle attempt from white
            // See if rook in starting position
            if ($state['board'][7][7] !== 'wr') {
                $state['lastMessage'] = "No white rook in starting position for castling.";
                $state['isValidMove'] = false;
                return null;
            } elseif ($state['board'][7][5] !== null && $state['board'][7][6] !== null) {
                $state['lastMessage'] = "Pieces in the way for castling.";
                $state['isValidMove'] = false;
                return null;
            } else {
                return [
                    "rookFormerPosition" => [7, 7],
                    "rookNewPosition" => [7, 5]
                ];
            }
        } elseif ($state['activeColor'] === 'white' && $toRow === 7 && $toCol === 2) {
            // See if valid long castle attempt from white
            // See if rook in starting position
            if ($state['board'][7][0] !== 'wr') {
                return null;
            } elseif ($state['board'][7][1] && $state['board'][7][2] !== null && $state['board'][7][3] !== null) {
                return null;
            } else {
                return [
                    "rookFormerPosition" => [7, 0],
                    "rookNewPosition" => [7, 3]
                ];
            }
        }

        if ($state['activeColor'] === 'black' && $toRow === 0 && $toCol === 6) {
            // See if valid short castle attempt from black
            if ($state['board'][0][7] !== 'br') {
                $state['lastMessage'] = "No black rook in starting position for castling.";
                $state['isValidMove'] = false;
                return null;
            } elseif ($state['board'][0][5] !== null && $state['board'][0][6] !== null) {
                $state['lastMessage'] = "Pieces in the way for castling.";  
                $state['isValidMove'] = false;
                return null;
            } else {
                return [
                    "rookFormerPosition" => [0, 7],
                    "rookNewPosition" => [0, 5]
                ];
            }
        } elseif ($state['activeColor'] === 'black' && $toRow === 0 && $toCol === 2) {
            // See if valid long castle attempt from black
            if ($state['board'][0][0] !== 'br') {
                return null;
            } elseif ($state['board'][0][1] && $state['board'][0][2] !== null && $state['board'][0][3] !== null) {
                return null;
            } else {
                return [
                    "rookFormerPosition" => [0, 0],
                    "rookNewPosition" => [0, 3]
                ];
            }
        }
        
        return null;
    }

    private function checkMoveLegality(array $state, array $move): bool
    {
        // TODO: Implement actual chess move legality checking - besides that of separate castling (has one legality checker)
        // TODO: Pawn valid moves
        if ($move['piece'][1] === 'p') {
            // Basic pawn move forward by 1
            $direction = $state['activeColor'] === 'white' ? -1 : 1;
            if ($move['toCol'] === $move['fromCol'] && $move['toRow'] === $move['fromRow'] + $direction && $state['board'][$move['toRow']][$move['toCol']] === null) {
                return true;
            }
            // Initial double move
            if (($state['activeColor'] === 'white' && $move['fromRow'] === 6) || ($state['activeColor'] === 'black' && $move['fromRow'] === 1)) {
                if ($move['toCol'] === $move['fromCol'] && $move['toRow'] === $move['fromRow'] + 2 * $direction && $state['board'][$move['fromRow'] + $direction][$move['toCol']] === null && $state['board'][$move['toRow']][$move['toCol']] === null) {
                    return true;
                }
            }
            // Captures
            if (abs($move['toCol'] - $move['fromCol']) === 1 && $move['toRow'] === $move['fromRow'] + $direction) {
                $targetPiece = $state['board'][$move['toRow']][$move['toCol']];
                if ($targetPiece !== null && substr($targetPiece, 0, 1) !== substr($move['piece'], 0, 1)) {
                    return true;
                }
            }
            return false;
        }

        // TODO: Bishop valid moves
        if ($move['piece'][1] === 'b') {
            $colDiff = abs($move['toCol'] - $move['fromCol']);
            $rowDiff = abs($move['toRow'] - $move['fromRow']);
            if ($colDiff === $rowDiff) {
                // Check for obstructions
                $colStep = ($move['toCol'] - $move['fromCol']) / $colDiff;
                $rowStep = ($move['toRow'] - $move['fromRow']) / $rowDiff;
                for ($i = 1; $i < $colDiff; $i++) {
                    $intermediateCol = $move['fromCol'] + $i * $colStep;
                    $intermediateRow = $move['fromRow'] + $i * $rowStep;
                    if ($state['board'][$intermediateRow][$intermediateCol] !== null) {
                        return false; // Obstruction found
                    }
                }
                // Check destination square
                $targetPiece = $state['board'][$move['toRow']][$move['toCol']];
                if ($targetPiece === null || substr($targetPiece, 0, 1) !== substr($move['piece'], 0, 1)) {
                    return true;
                }
            }
            return false;
        }

        // TODO: Knight valid moves
        if ($move['piece'][1] === 'n') {
            $colDiff = abs($move['toCol'] - $move['fromCol']);
            $rowDiff = abs($move['toRow'] - $move['fromRow']);
            if (($colDiff === 2 && $rowDiff === 1) || ($colDiff === 1 && $rowDiff === 2)) {
                $targetPiece = $state['board'][$move['toRow']][$move['toCol']];
                if ($targetPiece === null || substr($targetPiece, 0, 1) !== substr($move['piece'], 0, 1)) {
                    return true;
                }
            }
            return false;
        }

        // TODO: Rook valid moves
        if ($move['piece'][1] === 'r') {
            $colDiff = abs($move['toCol'] - $move['fromCol']);
            $rowDiff = abs($move['toRow'] - $move['fromRow']);
            if ($colDiff === 0 || $rowDiff === 0) {
                // Check for obstructions
                if ($colDiff !== 0) {
                    $step = ($move['toCol'] - $move['fromCol']) / $colDiff;
                    for ($i = 1; $i < $colDiff; $i++) {
                        if ($state['board'][$move['fromRow']][$move['fromCol'] + $i * $step] !== null) {
                            return false; // Obstruction found
                        }
                    }
                } else {
                    $step = ($move['toRow'] - $move['fromRow']) / $rowDiff;
                    for ($i = 1; $i < $rowDiff; $i++) {
                        if ($state['board'][$move['fromRow'] + $i * $step][$move['fromCol']] !== null) {
                            return false; // Obstruction found
                        }
                    }
                }
                // Check destination square
                $targetPiece = $state['board'][$move['toRow']][$move['toCol']];
                if ($targetPiece === null || substr($targetPiece, 0, 1) !== substr($move['piece'], 0, 1)) {
                    return true;
                }
            }
            return false;
        }

        // TODO: Queen valid moves
        if ($move['piece'][1] === 'q') {
            $colDiff = abs($move['toCol'] - $move['fromCol']);
            $rowDiff = abs($move['toRow'] - $move['fromRow']);
            if ($colDiff === $rowDiff || $colDiff === 0 || $rowDiff === 0) {
                // Check for obstructions
                if ($colDiff === $rowDiff) {
                    $colStep = ($move['toCol'] - $move['fromCol']) / $colDiff;
                    $rowStep = ($move['toRow'] - $move['fromRow']) / $rowDiff;
                    for ($i = 1; $i < $colDiff; $i++) {
                        $intermediateCol = $move['fromCol'] + $i * $colStep;
                        $intermediateRow = $move['fromRow'] + $i * $rowStep;
                        if ($state['board'][$intermediateRow][$intermediateCol] !== null) {
                            return false; // Obstruction found
                        }
                    }
                } else {
                    if ($colDiff !== 0) {
                        $step = ($move['toCol'] - $move['fromCol']) / $colDiff;
                        for ($i = 1; $i < $colDiff; $i++) {
                            if ($state['board'][$move['fromRow']][$move['fromCol'] + $i * $step] !== null) {
                                return false; // Obstruction found
                            }
                        }
                    } else {
                        $step = ($move['toRow'] - $move['fromRow']) / $rowDiff;
                        for ($i = 1; $i < $rowDiff; $i++) {
                            if ($state['board'][$move['fromRow'] + $i * $step][$move['fromCol']] !== null) {
                                return false; // Obstruction found
                            }
                        }
                    }
                }
                // Check destination square
                $targetPiece = $state['board'][$move['toRow']][$move['toCol']];
                if ($targetPiece === null || substr($targetPiece, 0, 1) !== substr($move['piece'], 0, 1)) {
                    return true;
                }
            }
            return false;
        }
        // TODO: King valid moves (besides castling)
        if ($move['piece'][1] === 'k') {
            $colDiff = abs($move['toCol'] - $move['fromCol']);
            $rowDiff = abs($move['toRow'] - $move['fromRow']);
            if ($colDiff <= 1 && $rowDiff <= 1) {
                $targetPiece = $state['board'][$move['toRow']][$move['toCol']];
                if ($targetPiece === null || substr($targetPiece, 0, 1) !== substr($move['piece'], 0, 1)) {
                    return true;
                }
            }
            return false;
        }
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
