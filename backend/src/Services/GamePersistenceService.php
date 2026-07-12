<?php

declare(strict_types=1);

namespace SoloChess\Services;

use JsonException;
use RuntimeException;
use SoloChess\Repositories\GameCreateData;
use SoloChess\Repositories\GameRepository;
use SoloChess\Repositories\GameUpdateData;
use SoloChess\Repositories\MoveCreateData;
use SoloChess\Repositories\RepositoryFactory;

final class GamePersistenceService
{
    public function __construct(
        private GameRepository $games,
        private SessionStore $sessions,
    ) {}

    public static function default(SessionStore $sessions): self
    {
        $repositories = RepositoryFactory::default();

        return new self($repositories->gameRepository(), $sessions);
    }

    /**
     * @param array<string, mixed> $fallbackState
     * @return array<string, mixed>
     */
    public function loadStateForAuthenticatedUser(array $fallbackState): array
    {
        $ownerUserId = $this->sessions->getAuthenticatedUserId();
        if ($ownerUserId === null) {
            return $fallbackState;
        }

        $currentGameId = $this->sessions->getCurrentGameId();
        if ($currentGameId !== null) {
            $record = $this->games->findByIdForOwner($currentGameId, $ownerUserId);
            if ($record !== null) {
                return self::decodeState($record->currentStateJson);
            }

            $this->sessions->clearCurrentGame();
        }

        $latest = $this->games->listForOwner($ownerUserId)[0] ?? null;
        if ($latest !== null) {
            $this->sessions->saveCurrentGameId($latest->id);

            return self::decodeState($latest->currentStateJson);
        }

        return $fallbackState;
    }

    /** @param array<string, mixed> $state */
    public function saveStateForAuthenticatedUser(array $state): void
    {
        $ownerUserId = $this->sessions->getAuthenticatedUserId();
        if ($ownerUserId === null) {
            return;
        }

        $currentGameId = $this->sessions->getCurrentGameId();
        $update = self::updateData($state);
        $moves = self::moveDataFromState($state);

        if ($currentGameId !== null && $this->games->findByIdForOwner($currentGameId, $ownerUserId) !== null) {
            $this->games->replaceCanonicalStateWithMoves($currentGameId, $ownerUserId, $update, $moves);

            return;
        }

        $created = $this->games->createWithMoves($this->createData($ownerUserId, $state), $moves);
        $this->sessions->saveCurrentGameId($created->id);
    }

    /** @param array<string, mixed> $state */
    public function createStateForAuthenticatedUser(array $state): void
    {
        $ownerUserId = $this->sessions->getAuthenticatedUserId();
        if ($ownerUserId === null) {
            return;
        }

        $created = $this->games->createWithMoves(
            $this->createData($ownerUserId, $state),
            self::moveDataFromState($state),
        );
        $this->sessions->saveCurrentGameId($created->id);
    }

    /** @param array<string, mixed> $state */
    private function createData(int $ownerUserId, array $state): GameCreateData
    {
        return new GameCreateData(
            ownerUserId: $ownerUserId,
            status: self::status($state),
            currentStateJson: self::encodeState($state),
            result: self::nullableString($state['result'] ?? null),
            terminationReason: self::nullableString($state['terminationReason'] ?? null),
            whiteLabel: self::participantLabel($state, 'white', 'White'),
            blackLabel: self::participantLabel($state, 'black', 'Black'),
            whitePlayerType: self::participantType($state, 'white'),
            blackPlayerType: self::participantType($state, 'black'),
            timeControlJson: self::optionalJson($state['timeControl'] ?? null),
            clockStateJson: self::optionalJson($state['clockState'] ?? null),
            completedAt: self::completedAt($state),
        );
    }

    /** @param array<string, mixed> $state */
    private static function updateData(array $state): GameUpdateData
    {
        return new GameUpdateData(
            status: self::status($state),
            currentStateJson: self::encodeState($state),
            result: self::nullableString($state['result'] ?? null),
            terminationReason: self::nullableString($state['terminationReason'] ?? null),
            timeControlJson: self::optionalJson($state['timeControl'] ?? null),
            clockStateJson: self::optionalJson($state['clockState'] ?? null),
            completedAt: self::completedAt($state),
        );
    }

    /**
     * @param array<string, mixed> $state
     * @return list<MoveCreateData>
     */
    private static function moveDataFromState(array $state): array
    {
        $history = $state['moveHistory'] ?? [];
        if (!is_array($history)) {
            return [];
        }

        $moves = [];
        foreach (array_values($history) as $index => $entry) {
            if (!is_array($entry)) {
                throw new RuntimeException('Move history entries must be arrays.');
            }

            $from = self::requiredString($entry, 'from');
            $to = self::requiredString($entry, 'to');
            $coordinate = self::optionalString($entry, 'coordinate') ?? $from . $to;
            $san = self::optionalString($entry, 'san') ?? $coordinate;
            $fen = self::optionalString($entry, 'fen') ?? self::requiredString($state, 'fen');

            $moves[] = new MoveCreateData(
                plyNumber: $index + 1,
                fromSquare: $from,
                toSquare: $to,
                promotion: self::optionalString($entry, 'promotion'),
                coordinate: $coordinate,
                san: $san,
                positionAfterFen: $fen,
                whiteClockMs: self::optionalInt($entry, 'whiteClockMilliseconds'),
                blackClockMs: self::optionalInt($entry, 'blackClockMilliseconds'),
            );
        }

        return $moves;
    }

    /** @return array<string, mixed> */
    private static function decodeState(string $json): array
    {
        try {
            $state = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored game state is not valid JSON.', previous: $error);
        }

        if (!is_array($state)) {
            throw new RuntimeException('Stored game state must decode to an array.');
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private static function encodeState(array $state): string
    {
        try {
            return json_encode($state, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Game state could not be encoded for storage.', previous: $error);
        }
    }

    /** @param array<string, mixed> $state */
    private static function status(array $state): string
    {
        $status = $state['gameStatus'] ?? null;

        return is_string($status) && $status !== '' ? $status : 'active';
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $state */
    private static function participantLabel(array $state, string $color, string $fallback): string
    {
        $participants = $state['participants'] ?? null;
        $participant = is_array($participants) && is_array($participants[$color] ?? null) ? $participants[$color] : [];
        $label = $participant['label'] ?? null;

        return is_string($label) && trim($label) !== '' ? trim($label) : $fallback;
    }

    /** @param array<string, mixed> $state */
    private static function participantType(array $state, string $color): string
    {
        $participants = $state['participants'] ?? null;
        $participant = is_array($participants) && is_array($participants[$color] ?? null) ? $participants[$color] : [];
        $type = $participant['type'] ?? null;

        return is_string($type) && trim($type) !== '' ? trim($type) : 'local_human';
    }

    private static function optionalJson(mixed $value): ?string
    {
        return is_array($value) ? self::encodeState($value) : null;
    }

    /** @param array<string, mixed> $state */
    private static function completedAt(array $state): ?string
    {
        if (self::status($state) !== 'finished') {
            return null;
        }

        return self::nullableString($state['completedAt'] ?? null);
    }

    /** @param array<string, mixed> $values */
    private static function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("Missing stored game field: {$key}");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function optionalString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $values */
    private static function optionalInt(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
