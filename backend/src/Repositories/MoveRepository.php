<?php

declare(strict_types=1);

namespace SoloChess\Repositories;

use PDO;
use RuntimeException;

final class MoveRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(int $gameId, MoveCreateData $data): MoveRecord
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO moves (
                game_id, ply_number, from_square, to_square, promotion, coordinate, san,
                position_after_fen, state_after_json, white_clock_ms, black_clock_ms
             ) VALUES (
                :game_id, :ply_number, :from_square, :to_square, :promotion, :coordinate, :san,
                :position_after_fen, :state_after_json, :white_clock_ms, :black_clock_ms
             )',
        );
        $statement->execute([
            ':game_id' => $gameId,
            ':ply_number' => $data->plyNumber,
            ':from_square' => $data->fromSquare,
            ':to_square' => $data->toSquare,
            ':promotion' => $data->promotion,
            ':coordinate' => $data->coordinate,
            ':san' => $data->san,
            ':position_after_fen' => $data->positionAfterFen,
            ':state_after_json' => $data->stateAfterJson,
            ':white_clock_ms' => $data->whiteClockMs,
            ':black_clock_ms' => $data->blackClockMs,
        ]);

        return $this->findRequiredById((int) $this->pdo->lastInsertId());
    }

    /** @return list<MoveRecord> */
    public function listForGame(int $gameId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, game_id, ply_number, from_square, to_square, promotion, coordinate, san,
                    position_after_fen, state_after_json, white_clock_ms, black_clock_ms, created_at
             FROM moves
             WHERE game_id = :game_id
             ORDER BY ply_number',
        );
        $statement->execute([':game_id' => $gameId]);

        return array_map(
            static fn(array $row): MoveRecord => MoveRecord::fromRow($row),
            $statement->fetchAll(),
        );
    }

    /**
     * @param list<MoveCreateData> $moves
     */
    public function replaceForGame(int $gameId, array $moves): void
    {
        $delete = $this->pdo->prepare('DELETE FROM moves WHERE game_id = :game_id');
        $delete->execute([':game_id' => $gameId]);

        foreach ($moves as $move) {
            $this->create($gameId, $move);
        }
    }

    private function findRequiredById(int $id): MoveRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT id, game_id, ply_number, from_square, to_square, promotion, coordinate, san,
                    position_after_fen, state_after_json, white_clock_ms, black_clock_ms, created_at
             FROM moves
             WHERE id = :id',
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch();
        if ($row === false) {
            throw new RuntimeException("Move not found: {$id}");
        }

        return MoveRecord::fromRow($row);
    }
}
