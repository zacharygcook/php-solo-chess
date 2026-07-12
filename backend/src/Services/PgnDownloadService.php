<?php

declare(strict_types=1);

namespace SoloChess\Services;

use JsonException;
use RuntimeException;
use SoloChess\Repositories\GameRecord;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\MoveRecord;
use SoloChess\Repositories\MoveRepository;
use SoloChess\Repositories\RepositoryFactory;

final class PgnDownloadService
{
    /** @var callable(): string */
    private $currentTimestamp;

    public function __construct(
        private PgnExporter $exporter,
        private SessionStore $sessions,
        private ?GameRepository $games = null,
        private ?MoveRepository $moves = null,
        ?callable $currentTimestamp = null,
    ) {
        $this->currentTimestamp = $currentTimestamp ?? static fn(): string => gmdate('c');
    }

    public static function default(): self
    {
        $sessions = new SessionStore();
        $repositories = RepositoryFactory::default();

        return new self(
            new PgnExporter(),
            $sessions,
            $repositories->gameRepository(),
            $repositories->moveRepository(),
        );
    }

    /** @return array{body: string, headers: array<string, string>, status: int} */
    public function exportResult(?int $gameId): array
    {
        if ($gameId !== null) {
            return $this->savedGameResult($gameId);
        }

        return $this->sessionResult();
    }

    /** @return array{body: string, headers: array<string, string>, status: int} */
    private function savedGameResult(int $gameId): array
    {
        $ownerUserId = $this->sessions->getAuthenticatedUserId();
        if ($ownerUserId === null) {
            return self::jsonError('Log in to export saved games.', 401);
        }

        $game = $this->requiredGames()->findByIdForOwner($gameId, $ownerUserId);
        if ($game === null) {
            return self::jsonError('Saved game not found.', 404);
        }

        return $this->pgnResult($game, $this->requiredMoves()->listForGame($gameId), "solo-chess-game-{$gameId}.pgn");
    }

    /** @return array{body: string, headers: array<string, string>, status: int} */
    private function sessionResult(): array
    {
        if ($this->sessions->getAuthenticatedUserId() !== null) {
            $currentGameId = $this->sessions->getCurrentGameId();

            return $currentGameId === null
                ? self::jsonError('Choose a saved game to export PGN.', 422)
                : $this->savedGameResult($currentGameId);
        }

        $state = $this->sessions->getState();
        if ($state === []) {
            return self::jsonError('No guest game is available to export.', 404);
        }

        $timestamp = ($this->currentTimestamp)();
        $game = $this->guestGameRecord($state, $timestamp);

        return $this->pgnResult($game, $this->guestMoveRecords($state), 'solo-chess-guest.pgn');
    }

    /**
     * @param list<MoveRecord> $moves
     * @return array{body: string, headers: array<string, string>, status: int}
     */
    private function pgnResult(GameRecord $game, array $moves, string $filename): array
    {
        return [
            'body' => $this->exporter->export($game, $moves),
            'headers' => [
                'Content-Type' => 'application/x-chess-pgn; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $this->safeFilename($filename) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store',
            ],
            'status' => 200,
        ];
    }

    /**
     * @param array<mixed> $state
     */
    private function guestGameRecord(array $state, string $createdAt): GameRecord
    {
        return new GameRecord(
            id: 0,
            ownerUserId: 0,
            whiteLabel: $this->participantLabel($state, 'white', 'White'),
            blackLabel: $this->participantLabel($state, 'black', 'Black'),
            whitePlayerType: $this->participantType($state, 'white'),
            blackPlayerType: $this->participantType($state, 'black'),
            status: $this->stringValue($state['gameStatus'] ?? null) ?? 'active',
            result: $this->stringValue($state['result'] ?? null),
            terminationReason: $this->stringValue($state['terminationReason'] ?? null),
            timeControlJson: $this->optionalJson($state['timeControl'] ?? null),
            currentStateJson: $this->encodeState($state),
            clockStateJson: $this->optionalJson($state['clockState'] ?? null),
            createdAt: $createdAt,
            updatedAt: $createdAt,
            completedAt: $this->stringValue($state['completedAt'] ?? null),
        );
    }

    /**
     * @param array<mixed> $state
     * @return list<MoveRecord>
     */
    private function guestMoveRecords(array $state): array
    {
        $history = $state['moveHistory'] ?? [];
        if (!is_array($history)) {
            return [];
        }

        $records = [];
        foreach (array_values($history) as $index => $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Move history entries must be arrays.');
            }
            $plyNumber = $index + 1;
            $from = $this->requiredString($entry, 'from');
            $to = $this->requiredString($entry, 'to');
            $coordinate = $this->stringValue($entry['coordinate'] ?? null) ?? $from . $to;

            $records[] = new MoveRecord(
                id: $plyNumber,
                gameId: 0,
                plyNumber: $plyNumber,
                fromSquare: $from,
                toSquare: $to,
                promotion: $this->stringValue($entry['promotion'] ?? null),
                coordinate: $coordinate,
                san: $this->stringValue($entry['san'] ?? null) ?? $coordinate,
                positionAfterFen: $this->stringValue($entry['fen'] ?? null) ?? $this->requiredString($state, 'fen'),
                stateAfterJson: null,
                whiteClockMs: $this->intValue($entry['whiteClockMilliseconds'] ?? null),
                blackClockMs: $this->intValue($entry['blackClockMilliseconds'] ?? null),
                createdAt: ($this->currentTimestamp)(),
            );
        }

        return $records;
    }

    /**
     * @return array{body: string, headers: array<string, string>, status: int}
     */
    private static function jsonError(string $message, int $status): array
    {
        return [
            'body' => json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store',
            ],
            'status' => $status,
        ];
    }

    private function requiredGames(): GameRepository
    {
        if ($this->games === null) {
            throw new RuntimeException('Game repository is required for saved PGN export.');
        }

        return $this->games;
    }

    private function requiredMoves(): MoveRepository
    {
        if ($this->moves === null) {
            throw new RuntimeException('Move repository is required for saved PGN export.');
        }

        return $this->moves;
    }

    /**
     * @param array<mixed> $state
     */
    private function participantLabel(array $state, string $color, string $fallback): string
    {
        $participant = $this->participant($state, $color);
        $label = $this->stringValue($participant['label'] ?? null);

        return $label !== null && trim($label) !== '' ? trim($label) : $fallback;
    }

    /**
     * @param array<mixed> $state
     */
    private function participantType(array $state, string $color): string
    {
        $participant = $this->participant($state, $color);
        $type = $this->stringValue($participant['type'] ?? null);

        return $type !== null && trim($type) !== '' ? trim($type) : 'local_human';
    }

    /**
     * @param array<mixed> $state
     * @return array<mixed>
     */
    private function participant(array $state, string $color): array
    {
        $participants = $state['participants'] ?? null;

        return is_array($participants) && is_array($participants[$color] ?? null) ? $participants[$color] : [];
    }

    private function optionalJson(mixed $value): ?string
    {
        return is_array($value) ? $this->encodeState($value) : null;
    }

    /**
     * @param array<mixed> $state
     */
    private function encodeState(array $state): string
    {
        try {
            return json_encode($state, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Game state could not be encoded for PGN export.', previous: $error);
        }
    }

    /**
     * @param array<mixed> $values
     */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Missing PGN export field: {$key}");
        }

        return $value;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function intValue(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function safeFilename(string $filename): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);

        return is_string($safe) && $safe !== '' ? $safe : 'solo-chess.pgn';
    }
}
