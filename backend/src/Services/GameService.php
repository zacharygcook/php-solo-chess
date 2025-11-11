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

        // See if in 'check' -> if so need to go down an entire other branch of logic
        if ($state['kingInCheck'] === $state['activeColor']) {
            // Acting color that's submitting a move is in check, see if its a legal move
            $checkingPieces = [];
            
            /*
            *  Because of the amazing possibility of double checks, this process will be a bit complicated
            *  We need to go out along every diagonal/rank/file from the king, until side of board or a piece is hit
            *    - If its an enemy piece it hits and it can "see" that way, log that as a checking piece
            *    - If it hits a friendly piece, block that direction
            *    - Then check all possible knight moves from the king, see if any enemy knights are there
            *
            *  NOTE: We might move all/most of this logic to the end of previous ply 
            *      -> since there might be same/similar calculations done to see if the move is checkmate
            */
            $ourKing = $state['activeColor'] === 'white' ? 'wk' : 'bk';
            $ourKingPosition = null;
            for ($row = 0; $row < 8; $row++) {
                for ($col = 0; $col < 8; $col++) {
                    if ($state['board'][$row][$col] == $ourKing) {
                        $ourKingPosition = ['row' => $row, 'col' => $col]; # where it is in the board array
                        break 2;
                    }
                }
            }

            if ($ourKingPosition === null) {
                // This should never happen, but just in case
                $state['lastMessage'] = "Internal error: couldn't find our king on the board.";
                $state['isValidMove'] = false;
                return $state;
            }

            // Search for a checking knight 
            $knightCode = $state['activeColor'] === 'white' ? 'wn' : 'bn';
            $diffRowColToCheck = [
                [-2, 1],
                [-2, -1],
                [-1, 2],
                [-1, -2],
                [1, 2],
                [1, -2],
                [2, 1],
                [2, -1]
            ];
            $potentialKnightSquares = []; # put intermediate values here

            foreach($diffRowColToCheck as $offset) {
                $row = $ourKingPosition['row'] + $offset[0];
                $col = $ourKingPosition['col'] + $offset[1];
                if (($row < 0 || $row > 7) || ($col < 0 || $col > 7)) {
                    // off the board
                } else {
                    $potentialKnightSquares[] = [$row, $col];
                }
            }

            foreach($potentialKnightSquares as $square) {
                if ($state['board'][$square[0]][$square[1]] === $knightCode) {
                    $checkingPieces[] = [
                        'piece' => 'knight',
                        'pieceCode' => $knightCode,
                        'boardLocation' => [$square[0], $square[1]]
                    ];
                    break;
                }
            }

            // Search for checking piece on a diagonal (bishop, queen or pawn)
            $oponnentDiagonalPieceCodes = [];
            if ($state['activeColor'] === 'white') {
                $enemyDiagonalPieceCodes = ['bb', 'bq', 'bp'];
            } else {
                $enemyDiagonalPieceCodes = ['wb', 'wq', 'wp'];
            }
            $diagonalVectors = [
                [-1, -1],
                [-1, 1],
                [1, -1],
                [1, 1]
            ];

            foreach($diagonalVectors as $vector) {
                $startingSquare = [$ourKingPosition['row'], $ourKingPosition['col']];
                for ($i=1; $i < 9; $i++) {
                    $squareToCheckRow = $startingSquare[0] + $vector[0] * $i;
                    $squareToCheckCol = $startingSquare[1] + $vector[1] * $i;
                    if (($squareToCheckRow < 0 || $squareToCheckRow > 7) || ($squareToCheckCol < 0 || $squareToCheckRow > 7)) {
                        continue; # off the board
                    } elseif ($state['board'][$squareToCheckRow][$squareToCheckCol] !== null) {
                        # checking piece detected, make note if on 1st iteration it could be a pawn
                        $pieceCode = $state['board'][$squareToCheckRow][$squareToCheckCol];
                        if (in_array($pieceCode, $enemyDiagonalPieceCodes)) {
                            if ($pieceCode[1] === 'p' && $i === 1) {
                                if ($state['activeColor'] === 'white') {
                                    # check if down one row, might be enemy pawn putting you in check
                                    if ($vector[1] === -1) {
                                        # enemy pawn is checking!
                                        $checkingPieces[] = [
                                            'piece' => 'pawn',
                                            'pieceCode' => $pieceCode,
                                            'boardLocation' => [$squareToCheckRow, $squareToCheckCol]
                                        ];
                                        break 2;
                                    }
                                } else { # must be black active
                                    if ($vector[1] === 1) {
                                        $checkingPieces[] = [
                                            'piece' => 'pawn',
                                            'pieceCode' => $pieceCode,
                                            'boardLocation' => [$squareToCheckRow, $squareToCheckCol]
                                        ];
                                    }
                                    break 2;
                                }
                            } elseif ($pieceCode[1] === 'b') {
                                $checkingPieces[] = [
                                    'piece' => 'bishop',
                                    'pieceCode' => $pieceCode,
                                    'boardLocation' => [$squareToCheckRow, $squareToCheckCol]
                                ];
                                break 2;
                            } else {
                                // Must be queen
                                $checkingPieces[] = [
                                    'piece' => 'queen',
                                    'pieceCode' => $pieceCode,
                                    'boardLocation' => [$squareToCheckRow, $squareToCheckCol]
                                ];
                                break 2;
                            }
                        } else {
                            # Ran into one of our own pieces, stop checking on this diagonal, go to next diagonal vector if available
                            # NOTE: This or something like it is the step missing in checking for checks from queen - CURRENT BUG as of 11/10/2025
                            break;
                        }
                    }
                }
            }

            // Search for a checking piece on a rank / file
            $oponnentDiagonalPieceCodes = [];
            if ($state['activeColor'] === 'white') {
                $enemyRankFileCodes = ['br', 'bq'];
            } else {
                $enemyRankFileCodes = ['wr', 'wq'];
            }
            $rankFileVectors = [
                [0, 1],
                [0, -1],
                [1, 0],
                [-1, 0]
            ];
            
            foreach($rankFileVectors as $vector) {
                $startingSquare = [$ourKingPosition['row'], $ourKingPosition['col']];
                for ($i=1; $i < 9; $i++) {
                    $squareToCheckRow = $startingSquare[0] + $vector[0] * $i;
                    $squareToCheckCol = $startingSquare[1] + $vector[0] * $i;
                    if (($squareToCheckRow < 0 || $squareToCheckRow > 7) || ($squareToCheckCol < 0 || $squareToCheckRow > 7)) {
                        continue; # off the board
                    } elseif ($state['board'][$squareToCheckRow][$squareToCheckCol] !== null) {
                        $pieceCode = $state['board'][$squareToCheckRow][$squareToCheckCol];
                        if (in_array($pieceCode, $enemyRankFileCodes)) {
                            if ($pieceCode[1] === 'r') {
                                $checkingPieces[] = [
                                    'piece' => 'rook',
                                    'pieceCode' => $pieceCode,
                                    'boardLocation' => [$squareToCheckRow, $squareToCheckCol]
                                ];
                                break 2;
                            } else {
                                $checkingPieces[] = [
                                    'piece' => 'queen',
                                    'pieceCode' => $pieceCode,
                                    'boardLocation' => [$squareToCheckRow, $squareToCheckCol]
                                ];
                                break 2;   
                            }
                        } else {
                            # ran into a friendly piece, stop checking along this rank / file
                            break;
                        }
                    }
                }
            }

            // Should have any checking pieces collected -> can move on to validate if their move successfully deals with the check

            $state['lastMessage'] = "You're in check from a " . $checkingPieces[0]['piece'] . " and your king in our array is at row "
                 . $ourKingPosition['row'] . " and column " . $ourKingPosition['col'];
                
            return $state;
        }

        // Convert to indices
        /*
        *  Basically making from column letter into an integer 0 -> 7
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
            'timestamp' => time()
        ];

        $state = $this->applyMove($state, $move, $piece);

        return $state;
    }

    /**
     * @param array<string, mixed> $state, @param array<string, mixed> $move
     * @return array<mixed>
     */
    private function applyMove(array $state, array $move): array
    {
        // Get starting data organized
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

        $legalMove = $this->checkMoveLegality($state, $move); # placeholder function for now

        if ($legalMove) {
            // b) Put piece in new location
            $state['board'][$toRow][$toCol] = $state['board'][$fromRow][$fromCol];
            // c) Remove piece at square it started at
            $state['board'][$fromRow][$fromCol] = null;
            $state['moveHistory'][] = $move;
        } else {
            $state['lastMessage'] = 'Illegal move.';
            $state['isValidMove'] = false;
            return $state;
        }

        $checkStatus = $this->calculateCheckStatus($state, $move); // 'check', 'checkmate', 'stalemate', or null
        // xdebug_break();
        if ($checkStatus === 'check') {
            $state['lastMessage'] = 'Check!';
            $state['kingInCheck'] = $state['activeColor'] === 'white' ? 'black' : 'white';
        } elseif ($checkStatus === 'checkmate') {
            $state['lastMessage'] = 'Checkmate!';
        } elseif ($checkStatus === 'stalemate') {
            $state['lastMessage'] = 'Stalemate!';
        } else {
            $state['lastMessage'] = 'Move successfully made.';
        }

        $state['activeColor'] = $state['activeColor'] === 'white' ? 'black' : 'white';

        $this->store->saveState($state);
        
        return $state;
    }

    /**
     * @param array<string, mixed> $state
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
        // Legality checking besides that of separate legality checking for castling and if king is in check
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

    private function calculateCheckStatus(array $state, array $move): ?string
    {
        // TODO: Implement logic to check if the move puts the enemy's king in check
        // Return values 'check', 'checkmate', 'stalemate', or null

        // Easiest to check per piece type against king position
        // First get enemy king position
        $enemyKing = $state['activeColor'] === 'white' ? 'bk' : 'wk';
        $enemyKingPosition = null;
        for ($row = 0; $row < 8; $row++) {
            for ($col = 0; $col < 8; $col++) {
                if ($state['board'][$row][$col] === $enemyKing) {
                    $enemyKingPosition = ['row' => $row, 'col' => $col];
                    break 2;
                }
            }
        }

        if ($move['piece'][1] === 'k') {
            // If king moved, no need to check for check
            return null;
        } elseif ($move['piece'][1] === 'q') {
            // Check if queen can attack king position
            $sameDiagonalAsEnemyKing = ($move['toRow'] - $move['toCol']) === ($enemyKingPosition['row'] - $enemyKingPosition['col']);
            $sameRowAsEnemyKing = $move['toRow'] === $enemyKingPosition['row'];
            $sameColAsEnemyKing = $move['toCol'] === $enemyKingPosition['col'];
            if ($sameDiagonalAsEnemyKing || $sameRowAsEnemyKing || $sameColAsEnemyKing) {
                // See if any pieces in between
                $pieceBetween = false;
                // Beginning of logic to check for pieces in between

                $checkMate = $this->determineCheckmate($state, $move);
                return $checkMate ? 'checkmate' : 'check';
            } else {
                return null;
            }
        } elseif ($move['piece'][1] === 'r') {
            // Check if rook can attack king position
            $sameRowAsEnemyKing = $move['toRow'] === $enemyKingPosition['row'];
            $sameColAsEnemyKing = $move['toCol'] === $enemyKingPosition['col'];
            if ($sameRowAsEnemyKing || $sameColAsEnemyKing) {
                // See if any pieces in between
                $pieceBetween = false;
                // Beginning of logic to check for pieces in between

                $checkMate = $this->determineCheckmate($state, $move);
                return $checkMate ? 'checkmate' : 'check';
            } else {
                return null;
            }
        } elseif ($move['piece'][1] === 'b') {
            // Check if bishop can attack king position
            $sameDiagonalAsEnemyKing = ($move['toRow'] - $move['toCol']) === ($enemyKingPosition['row'] - $enemyKingPosition['col']);
            if ($sameDiagonalAsEnemyKing) {
                // See if any pieces in between
                $pieceBetween = false;
                // Beginning of logic to check for pieces in between

                $checkMate = $this->determineCheckmate($state, $move);
                return $checkMate ? 'checkmate' : 'check';
            } else {
                return null;
            }
        } elseif ($move['piece'][1] === 'n') {
            // Check if knight can attack king position
            $knightMoves = [
                [-2, -1], [-2, 1], [-1, -2], [-1, 2],
                [1, -2], [1, 2], [2, -1], [2, 1]
            ];
            foreach ($knightMoves as $moveOffset) {
                $knightRow = $move['toRow'] + $moveOffset[0];
                $knightCol = $move['toCol'] + $moveOffset[1];
                if ($knightRow === $enemyKingPosition['row'] && $knightCol === $enemyKingPosition['col']) {
                    // Knight can attack king
                    $checkMate = $this->determineCheckmate($state, $move);
                    return $checkMate ? 'checkmate' : 'check';
                }
            }
            return null;
        } elseif ($move['piece'][1] === 'p') {
            // Check if pawn can attack king position
            $direction = $state['activeColor'] === 'white' ? -1 : 1;
            $pawnAttackPositions = [
                ['row' => $move['toRow'] + $direction, 'col' => $move['toCol'] - 1],
                ['row' => $move['toRow'] + $direction, 'col' => $move['toCol'] + 1],
            ];
            foreach ($pawnAttackPositions as $pos) {
                if ($pos['row'] === $enemyKingPosition['row'] && $pos['col'] === $enemyKingPosition['col']) {
                    // Pawn can attack king
                    $checkMate = $this->determineCheckmate($state, $move);
                    return $checkMate ? 'checkmate' : 'check';
                }
            }
            return null;
        }
        return null;
    }

    private function determineCheckmate(array $state, array $move): bool
    {
        // TODO: Implement logic to determine if the move results in checkmate
        return false;
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
            'kingInCheck' => null,
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
