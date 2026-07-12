<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class GameRepository
{
    private MoveRepository $moveRepository;

    public function __construct(private PDO $pdo, ?MoveRepository $moveRepository = null)
    {
        $this->moveRepository = $moveRepository ?? new MoveRepository($pdo);
    }

    public function create(GameCreateData $data): GameRecord
    {
        $this->insertGame($data);

        return $this->findRequiredById((int) $this->pdo->lastInsertId());
    }

    /**
     * @param list<MoveCreateData> $moves
     */
    public function createWithMoves(GameCreateData $data, array $moves): GameRecord
    {
        $this->pdo->beginTransaction();

        try {
            $this->insertGame($data);
            $gameId = (int) $this->pdo->lastInsertId();
            $this->moveRepository->replaceForGame($gameId, $moves);
            $record = $this->findRequiredById($gameId);
            $this->pdo->commit();

            return $record;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    private function insertGame(GameCreateData $data): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO games (
                owner_user_id, white_label, black_label, white_player_type, black_player_type,
                status, result, termination_reason, time_control_json, current_state_json,
                clock_state_json, completed_at
             ) VALUES (
                :owner_user_id, :white_label, :black_label, :white_player_type, :black_player_type,
                :status, :result, :termination_reason, :time_control_json, :current_state_json,
                :clock_state_json, :completed_at
             )',
        );
        $statement->execute([
            ':owner_user_id' => $data->ownerUserId,
            ':white_label' => $data->whiteLabel,
            ':black_label' => $data->blackLabel,
            ':white_player_type' => $data->whitePlayerType,
            ':black_player_type' => $data->blackPlayerType,
            ':status' => $data->status,
            ':result' => $data->result,
            ':termination_reason' => $data->terminationReason,
            ':time_control_json' => $data->timeControlJson,
            ':current_state_json' => $data->currentStateJson,
            ':clock_state_json' => $data->clockStateJson,
            ':completed_at' => $data->completedAt,
        ]);
    }

    public function findByIdForOwner(int $id, int $ownerUserId): ?GameRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT id, owner_user_id, white_label, black_label, white_player_type, black_player_type,
                    status, result, termination_reason, time_control_json, current_state_json,
                    clock_state_json, created_at, updated_at, completed_at
             FROM games
             WHERE id = :id AND owner_user_id = :owner_user_id',
        );
        $statement->execute([
            ':id' => $id,
            ':owner_user_id' => $ownerUserId,
        ]);
        $row = $statement->fetch();

        return $row === false ? null : GameRecord::fromRow($row);
    }

    /** @return list<GameRecord> */
    public function listForOwner(int $ownerUserId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, owner_user_id, white_label, black_label, white_player_type, black_player_type,
                    status, result, termination_reason, time_control_json, current_state_json,
                    clock_state_json, created_at, updated_at, completed_at
             FROM games
             WHERE owner_user_id = :owner_user_id
             ORDER BY updated_at DESC, id DESC',
        );
        $statement->execute([':owner_user_id' => $ownerUserId]);

        return array_map(
            static fn(array $row): GameRecord => GameRecord::fromRow($row),
            $statement->fetchAll(),
        );
    }

    public function updateState(int $id, int $ownerUserId, GameUpdateData $data): GameRecord
    {
        $this->updateStateRow($id, $ownerUserId, $data);

        return $this->findRequiredForOwner($id, $ownerUserId);
    }

    /**
     * @param list<MoveCreateData> $moves
     */
    public function replaceCanonicalStateWithMoves(
        int $id,
        int $ownerUserId,
        GameUpdateData $data,
        array $moves,
    ): GameRecord {
        $this->pdo->beginTransaction();

        try {
            $this->updateStateRow($id, $ownerUserId, $data);
            $this->moveRepository->replaceForGame($id, $moves);
            $record = $this->findRequiredForOwner($id, $ownerUserId);
            $this->pdo->commit();

            return $record;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    private function updateStateRow(int $id, int $ownerUserId, GameUpdateData $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE games
             SET status = :status,
                 result = :result,
                 termination_reason = :termination_reason,
                 time_control_json = :time_control_json,
                 current_state_json = :current_state_json,
                 clock_state_json = :clock_state_json,
                 updated_at = CURRENT_TIMESTAMP,
                 completed_at = :completed_at
             WHERE id = :id AND owner_user_id = :owner_user_id',
        );
        $statement->execute([
            ':status' => $data->status,
            ':result' => $data->result,
            ':termination_reason' => $data->terminationReason,
            ':time_control_json' => $data->timeControlJson,
            ':current_state_json' => $data->currentStateJson,
            ':clock_state_json' => $data->clockStateJson,
            ':completed_at' => $data->completedAt,
            ':id' => $id,
            ':owner_user_id' => $ownerUserId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException("Game not found for owner: {$id}");
        }
    }

    private function findRequiredById(int $id): GameRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT id, owner_user_id, white_label, black_label, white_player_type, black_player_type,
                    status, result, termination_reason, time_control_json, current_state_json,
                    clock_state_json, created_at, updated_at, completed_at
             FROM games
             WHERE id = :id',
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new RuntimeException("Game not found: {$id}");
        }

        return GameRecord::fromRow($row);
    }

    private function findRequiredForOwner(int $id, int $ownerUserId): GameRecord
    {
        $record = $this->findByIdForOwner($id, $ownerUserId);
        if ($record === null) {
            throw new RuntimeException("Game not found for owner: {$id}");
        }

        return $record;
    }
}
