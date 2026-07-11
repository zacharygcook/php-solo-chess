<?php

declare(strict_types=1);

namespace SoloChess\Services;

use SoloChess\Services\Chess\CastlingResolver;
use SoloChess\Services\Chess\Coordinate;
use SoloChess\Services\Chess\GameStateFactory;
use SoloChess\Services\Chess\LegalMoveGenerator;
use SoloChess\Services\Chess\Move;
use SoloChess\Services\Chess\NotationFormatter;
use SoloChess\Services\Chess\PieceMovement;
use SoloChess\Services\Chess\PositionAnalyzer;
use SoloChess\Services\Chess\TerminalStateResolver;

final class GameService
{
    private GameStateFactory $stateFactory;
    private PieceMovement $movement;
    private PositionAnalyzer $positionAnalyzer;
    private CastlingResolver $castlingResolver;
    private LegalMoveGenerator $legalMoveGenerator;
    private TerminalStateResolver $terminalStateResolver;
    private NotationFormatter $notationFormatter;

    public function __construct(private SessionStore $store)
    {
        $this->stateFactory = new GameStateFactory();
        $this->movement = new PieceMovement();
        $this->positionAnalyzer = new PositionAnalyzer($this->movement);
        $this->castlingResolver = new CastlingResolver($this->positionAnalyzer);
        $this->legalMoveGenerator = new LegalMoveGenerator($this->movement, $this->positionAnalyzer, $this->castlingResolver);
        $this->terminalStateResolver = new TerminalStateResolver();
        $this->notationFormatter = new NotationFormatter();
    }

    /** @return array<string, mixed> */
    public function getSessionState(): array
    {
        $storedState = $this->store->getState();
        $state = $this->stateFactory->normalize($storedState);
        $state = $this->withLegalMoves($state);
        if ($storedState !== $state) {
            $this->store->saveState($state);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitMove(array $payload): array
    {
        $state = $this->getSessionState();
        $from = Coordinate::fromAlgebraic($payload['from'] ?? null);
        if ($from === null) {
            $message = empty($payload['from']) ? "Couldn't find a 'from' coordinate" : "Not a valid 'from' option";

            return $this->reject($state, $message);
        }

        $to = Coordinate::fromAlgebraic($payload['to'] ?? null);
        if ($to === null) {
            return $this->reject($state, "Not a valid 'to' option");
        }

        $piece = $state['board'][$from->row][$from->col] ?? null;
        if (!is_string($piece)) {
            return $this->reject($state, "No piece at 'from' coordinate");
        }

        $movingColor = self::pieceColor($piece);
        if ($movingColor !== $state['activeColor']) {
            return $this->reject($state, "It's not {$movingColor}'s turn.");
        }

        $promotion = isset($payload['promotion']) && is_string($payload['promotion'])
            ? $payload['promotion']
            : null;
        $move = new Move($from, $to, $piece, $promotion, time());

        return $this->applyMove($state, $move);
    }

    /** @return array<string, mixed> */
    public function resetGame(): array
    {
        $state = $this->stateFactory->create();
        $state = $this->withLegalMoves($state);
        $this->store->saveState($state);

        return $state;
    }

    /** @return array<string, mixed> */
    public function loadFen(string $fen): array
    {
        $state = $this->getSessionState();
        $state['lastMessage'] = 'FEN loading not implemented. Wire your parser into GameService::loadFen().';
        $this->store->saveState($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function applyMove(array $state, Move $move): array
    {
        if (($state['gameStatus'] ?? 'active') === 'finished') {
            return $this->reject($state, 'Game is already finished.');
        }

        $board = $state['board'];
        $movingColor = $state['activeColor'];
        $castle = $this->castlingResolver->resolve($board, $move, $movingColor, $state['castlingRights']);
        $enPassantCapturedSquare = $this->enPassantCapturedSquare($state, $move);
        if (!$this->isMoveShapeLegal($board, $move, $castle, $enPassantCapturedSquare)) {
            return $this->reject($state, 'Illegal move.');
        }

        $promotionPiece = $this->promotionPiece($move, $movingColor);
        if ($this->isPromotionMove($move) && $promotionPiece === null) {
            return $this->reject($state, 'Promotion requires queen, rook, bishop, or knight.');
        }

        $capturedPiece = $this->capturedPiece($board, $move, $enPassantCapturedSquare);
        $candidate = $this->movePiece($board, $move, $castle, $enPassantCapturedSquare, $promotionPiece);
        if ($this->positionAnalyzer->isKingInCheck($candidate, $movingColor)) {
            return $this->reject($state, "Move would leave {$movingColor} in check.");
        }

        $state['board'] = $candidate;
        $before = $state;
        $state['moveHistory'][] = $move->toHistoryRecord();
        $state['activeColor'] = self::opponent($movingColor);
        $state = $this->updateRuleState($state, $move, $movingColor, $capturedPiece);
        $inCheck = $this->positionAnalyzer->isKingInCheck($candidate, $state['activeColor']);
        $state['kingInCheck'] = $inCheck ? $state['activeColor'] : null;
        $state['lastMessage'] = $inCheck ? 'Check!' : ($castle === null ? 'Move successfully made.' : 'Castling move successfully made.');
        $state = $this->withLegalMoves($state);
        $state = $this->terminalStateResolver->resolveAfterMove($state);
        $state['fen'] = $this->notationFormatter->fen($state);
        $state = $this->withMoveNotation($state, $before, $move, $capturedPiece !== null, $castle !== null);
        unset($state['isValidMove']);
        $this->store->saveState($state);

        return $state;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array{rookFrom: array{int, int}, rookTo: array{int, int}}|null $castle
     * @param array{int, int}|null $enPassantCapturedSquare
     */
    private function isMoveShapeLegal(array $board, Move $move, ?array $castle, ?array $enPassantCapturedSquare): bool
    {
        return $castle !== null
            || $enPassantCapturedSquare !== null
            || $this->movement->isLegal($board, $move);
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array{rookFrom: array{int, int}, rookTo: array{int, int}}|null $castle
     * @param array{int, int}|null $enPassantCapturedSquare
     * @return array<int, array<int, string|null>>
     */
    private function movePiece(array $board, Move $move, ?array $castle, ?array $enPassantCapturedSquare, ?string $promotionPiece): array
    {
        $board[$move->to->row][$move->to->col] = $promotionPiece ?? $move->piece;
        $board[$move->from->row][$move->from->col] = null;
        if ($enPassantCapturedSquare !== null) {
            $board[$enPassantCapturedSquare[0]][$enPassantCapturedSquare[1]] = null;
        }
        if ($castle !== null) {
            $board[$castle['rookTo'][0]][$castle['rookTo'][1]] = $board[$castle['rookFrom'][0]][$castle['rookFrom'][1]];
            $board[$castle['rookFrom'][0]][$castle['rookFrom'][1]] = null;
        }

        return $board;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function updateRuleState(array $state, Move $move, string $movingColor, ?string $capturedPiece): array
    {
        $state['castlingRights'] = $this->updatedCastlingRights($state['castlingRights'], $move, $movingColor, $capturedPiece);
        $state['enPassantTarget'] = $this->enPassantTargetFor($move);
        $state['halfmoveClock'] = $move->piece[1] === 'p' || $capturedPiece !== null ? 0 : $state['halfmoveClock'] + 1;
        if ($movingColor === 'black') {
            $state['fullmoveNumber']++;
        }
        $state['positionHistory'][] = $this->stateFactory->positionKey(
            $state['board'],
            $state['activeColor'],
            $state['castlingRights'],
            $state['enPassantTarget'],
        );

        return $state;
    }

    /**
     * @param array<string, array<string, bool>> $rights
     * @return array<string, array<string, bool>>
     */
    private function updatedCastlingRights(array $rights, Move $move, string $movingColor, ?string $capturedPiece): array
    {
        if ($move->piece[1] === 'k') {
            $rights[$movingColor]['kingSide'] = false;
            $rights[$movingColor]['queenSide'] = false;
        }
        if ($move->piece[1] === 'r') {
            $this->clearRookRight($rights, $movingColor, $move->from->row, $move->from->col);
        }
        if ($capturedPiece !== null && $capturedPiece[1] === 'r') {
            $this->clearRookRight($rights, self::pieceColor($capturedPiece), $move->to->row, $move->to->col);
        }

        return $rights;
    }

    /** @param array<string, array<string, bool>> $rights */
    private function clearRookRight(array &$rights, string $color, int $row, int $col): void
    {
        $homeRow = $color === 'white' ? 7 : 0;
        if ($row !== $homeRow) {
            return;
        }
        if ($col === 7) {
            $rights[$color]['kingSide'] = false;
        }
        if ($col === 0) {
            $rights[$color]['queenSide'] = false;
        }
    }

    private function enPassantTargetFor(Move $move): ?string
    {
        if ($move->piece[1] !== 'p' || abs($move->to->row - $move->from->row) !== 2) {
            return null;
        }

        return self::squareName(($move->from->row + $move->to->row) / 2, $move->from->col);
    }

    /** @param array<string, mixed> $state */
    private function enPassantCapturedSquare(array $state, Move $move): ?array
    {
        if ($move->piece[1] !== 'p' || $move->to->algebraic !== ($state['enPassantTarget'] ?? null)) {
            return null;
        }
        if (!$this->isEnPassantDiagonal($state, $move)) {
            return null;
        }

        $captured = $state['board'][$move->from->row][$move->to->col] ?? null;
        if (!is_string($captured) || $captured[1] !== 'p' || $captured[0] === $move->piece[0]) {
            return null;
        }

        return [$move->from->row, $move->to->col];
    }

    /** @param array<string, mixed> $state */
    private function isEnPassantDiagonal(array $state, Move $move): bool
    {
        $direction = $move->piece[0] === 'w' ? -1 : 1;

        return $move->to->row - $move->from->row === $direction
            && abs($move->to->col - $move->from->col) === 1
            && $state['board'][$move->to->row][$move->to->col] === null;
    }

    /**
     * @param array<int, array<int, string|null>> $board
     * @param array{int, int}|null $enPassantCapturedSquare
     */
    private function capturedPiece(array $board, Move $move, ?array $enPassantCapturedSquare): ?string
    {
        if ($enPassantCapturedSquare !== null) {
            return $board[$enPassantCapturedSquare[0]][$enPassantCapturedSquare[1]];
        }

        return $board[$move->to->row][$move->to->col];
    }

    private function isPromotionMove(Move $move): bool
    {
        return $move->piece[1] === 'p' && ($move->to->row === 0 || $move->to->row === 7);
    }

    private function promotionPiece(Move $move, string $movingColor): ?string
    {
        if (!$this->isPromotionMove($move)) {
            return null;
        }

        $piece = match ($move->promotion) {
            'queen' => 'q',
            'rook' => 'r',
            'bishop' => 'b',
            'knight' => 'n',
            default => null,
        };

        return $piece === null ? null : ($movingColor === 'white' ? 'w' : 'b') . $piece;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function reject(array $state, string $message): array
    {
        $state['lastMessage'] = $message;
        $state['isValidMove'] = false;

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function withLegalMoves(array $state): array
    {
        $state['legalMoves'] = $this->legalMoveGenerator->generate($state);
        $state['fen'] = $this->notationFormatter->fen($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $before
     * @return array<string, mixed>
     */
    private function withMoveNotation(array $state, array $before, Move $move, bool $isCapture, bool $isCastle): array
    {
        $index = count($state['moveHistory']) - 1;
        $state['moveHistory'][$index]['coordinate'] = $this->notationFormatter->coordinate($move);
        $state['moveHistory'][$index]['san'] = $this->notationFormatter->san($before, $state, $move, $isCapture, $isCastle);
        $state['moveHistory'][$index]['fen'] = $state['fen'];

        return $state;
    }

    private static function pieceColor(string $piece): string
    {
        return $piece[0] === 'w' ? 'white' : 'black';
    }

    private static function opponent(string $color): string
    {
        return $color === 'white' ? 'black' : 'white';
    }

    private static function squareName(int|float $row, int $col): string
    {
        return chr(ord('a') + $col) . (8 - (int) $row);
    }
}
