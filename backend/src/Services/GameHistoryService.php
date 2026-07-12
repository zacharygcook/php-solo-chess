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
use SoloChess\Services\Chess\GameStateFactory;
use SoloChess\Services\Chess\NotationFormatter;

final class GameHistoryService
{
    private GameStateFactory $stateFactory;
    private NotationFormatter $notationFormatter;

    public function __construct(
        private GameRepository $games,
        private MoveRepository $moves,
        private SessionStore $sessions,
    ) {
        $this->stateFactory = new GameStateFactory();
        $this->notationFormatter = new NotationFormatter();
    }

    public static function default(SessionStore $sessions): self
    {
        $repositories = RepositoryFactory::default();

        return new self($repositories->gameRepository(), $repositories->moveRepository(), $sessions);
    }

    /** @return list<array<string, mixed>> */
    public function listForAuthenticatedUser(): array
    {
        $ownerUserId = $this->sessions->getAuthenticatedUserId();
        if ($ownerUserId === null) {
            return [];
        }

        return array_map(
            fn(GameRecord $record): array => $this->summary($record),
            $this->games->listForOwner($ownerUserId),
        );
    }

    /** @return array<string, mixed>|null */
    public function openForAuthenticatedUser(int $gameId): ?array
    {
        $ownerUserId = $this->sessions->getAuthenticatedUserId();
        if ($ownerUserId === null) {
            return null;
        }

        $record = $this->games->findByIdForOwner($gameId, $ownerUserId);
        if ($record === null) {
            return null;
        }

        $moves = $this->moves->listForGame($gameId);

        return [
            'game' => $this->summary($record),
            'state' => self::decodeObject($record->currentStateJson),
            'replay' => [
                'positions' => $this->replayPositions($moves),
                'moves' => array_map(
                    static fn(MoveRecord $move): array => self::moveData($move),
                    $moves,
                ),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function summary(GameRecord $record): array
    {
        $date = $record->completedAt ?? $record->updatedAt;

        return [
            'id' => $record->id,
            'date' => $date,
            'createdAt' => $record->createdAt,
            'updatedAt' => $record->updatedAt,
            'completedAt' => $record->completedAt,
            'status' => $record->status,
            'result' => $record->result,
            'completionReason' => $record->terminationReason,
            'terminationReason' => $record->terminationReason,
            'whiteLabel' => $record->whiteLabel,
            'blackLabel' => $record->blackLabel,
            'whitePlayerType' => $record->whitePlayerType,
            'blackPlayerType' => $record->blackPlayerType,
            'timeControl' => self::decodeOptionalObject($record->timeControlJson),
        ];
    }

    /**
     * @param list<MoveRecord> $moves
     * @return list<array<string, mixed>>
     */
    private function replayPositions(array $moves): array
    {
        $initialState = $this->stateFactory->create();
        $positions = [[
            'plyNumber' => 0,
            'coordinate' => 'initial',
            'san' => null,
            'fen' => $this->notationFormatter->fen($initialState),
            'whiteClockMilliseconds' => null,
            'blackClockMilliseconds' => null,
        ]];

        foreach ($moves as $move) {
            $positions[] = [
                'plyNumber' => $move->plyNumber,
                'coordinate' => $move->coordinate,
                'san' => $move->san,
                'fen' => $move->positionAfterFen,
                'whiteClockMilliseconds' => $move->whiteClockMs,
                'blackClockMilliseconds' => $move->blackClockMs,
            ];
        }

        return $positions;
    }

    /** @return array<string, mixed> */
    private static function moveData(MoveRecord $move): array
    {
        return [
            'plyNumber' => $move->plyNumber,
            'from' => $move->fromSquare,
            'to' => $move->toSquare,
            'promotion' => $move->promotion,
            'coordinate' => $move->coordinate,
            'san' => $move->san,
            'fen' => $move->positionAfterFen,
            'whiteClockMilliseconds' => $move->whiteClockMs,
            'blackClockMilliseconds' => $move->blackClockMs,
            'createdAt' => $move->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    private static function decodeObject(string $json): array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Stored game JSON is invalid.', previous: $error);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Stored game JSON must decode to an object.');
        }

        return $decoded;
    }

    /** @return array<string, mixed>|null */
    private static function decodeOptionalObject(?string $json): ?array
    {
        return $json === null ? null : self::decodeObject($json);
    }
}
