<?php

declare(strict_types=1);

namespace SoloChess\Services;

use SoloChess\Repositories\GameRecord;
use SoloChess\Repositories\MoveRecord;
use Throwable;

final class PgnVerifier
{
    /**
     * @param list<MoveRecord> $moves
     */
    public function verify(GameRecord $game, array $moves): PgnVerificationResult
    {
        $errors = [];
        $savedState = $this->savedState($game, $errors);

        $replay = $this->replay($moves, $errors);
        $replayState = $replay['state'];

        $this->verifyMoveCount($moves, $replayState, $savedState, $errors);
        $this->verifyFinalFen($replayState, $savedState, $errors);
        $this->verifyResult($game, $replayState, $savedState, $errors);

        return new PgnVerificationResult(
            $errors,
            is_string($replayState['fen'] ?? null) ? $replayState['fen'] : null,
            $this->resultToken($game->result),
            is_array($replayState['moveHistory'] ?? null) ? count($replayState['moveHistory']) : 0,
        );
    }

    /**
     * @param list<MoveRecord> $moves
     * @param list<string> $errors
     * @return array{state: array<string, mixed>}
     */
    private function replay(array $moves, array &$errors): array
    {
        $previousSession = $_SESSION ?? [];
        $_SESSION = [];

        try {
            $game = new GameService(new SessionStore());
            $state = $game->getSessionState();
            foreach ($moves as $index => $move) {
                $expectedPly = $index + 1;
                if ($move->plyNumber !== $expectedPly) {
                    $errors[] = "Ply {$move->plyNumber} is out of order; expected {$expectedPly}.";
                }

                $state = $game->submitMove([
                    'from' => $move->fromSquare,
                    'to' => $move->toSquare,
                    'promotion' => $move->promotion,
                ]);

                if (($state['isValidMove'] ?? true) === false) {
                    $errors[] = "Ply {$move->plyNumber} {$move->coordinate} was rejected: "
                        . (string) ($state['lastMessage'] ?? 'Unknown reason.');
                    break;
                }

                $this->verifyReplayedMove($move, $state, $errors);
            }

            return ['state' => $state];
        } finally {
            $_SESSION = $previousSession;
        }
    }

    /**
     * @param list<string> $errors
     * @return array<string, mixed>|null
     */
    private function savedState(GameRecord $game, array &$errors): ?array
    {
        try {
            $state = json_decode($game->currentStateJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $errors[] = 'Saved game state JSON is invalid: ' . $error->getMessage();

            return null;
        }

        if (!is_array($state)) {
            $errors[] = 'Saved game state JSON must decode to an object.';

            return null;
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param list<string> $errors
     */
    private function verifyReplayedMove(MoveRecord $move, array $state, array &$errors): void
    {
        $history = $state['moveHistory'] ?? [];
        if (!is_array($history)) {
            $errors[] = "Replay history is missing after ply {$move->plyNumber}.";

            return;
        }

        $entry = $history[count($history) - 1] ?? null;
        if (!is_array($entry)) {
            $errors[] = "Replay history is missing ply {$move->plyNumber}.";

            return;
        }

        foreach (['coordinate' => $move->coordinate, 'san' => $move->san, 'fen' => $move->positionAfterFen] as $field => $expected) {
            if (($entry[$field] ?? null) !== $expected) {
                $errors[] = "Ply {$move->plyNumber} {$field} mismatch; expected {$expected}, replayed "
                    . (is_scalar($entry[$field] ?? null) ? (string) $entry[$field] : 'missing') . '.';
            }
        }
    }

    /**
     * @param list<MoveRecord> $moves
     * @param array<string, mixed> $replayState
     * @param array<string, mixed>|null $savedState
     * @param list<string> $errors
     */
    private function verifyMoveCount(array $moves, array $replayState, ?array $savedState, array &$errors): void
    {
        $replayCount = is_array($replayState['moveHistory'] ?? null) ? count($replayState['moveHistory']) : 0;
        if ($replayCount !== count($moves)) {
            $errors[] = 'Replay move count mismatch; expected ' . count($moves) . ", replayed {$replayCount}.";
        }

        if ($savedState === null) {
            return;
        }

        $savedCount = is_array($savedState['moveHistory'] ?? null) ? count($savedState['moveHistory']) : null;
        if ($savedCount !== count($moves)) {
            $errors[] = 'Saved move count mismatch; expected ' . count($moves) . ', saved '
                . ($savedCount === null ? 'missing' : (string) $savedCount) . '.';
        }
    }

    /**
     * @param array<string, mixed> $replayState
     * @param array<string, mixed>|null $savedState
     * @param list<string> $errors
     */
    private function verifyFinalFen(array $replayState, ?array $savedState, array &$errors): void
    {
        if ($savedState === null) {
            return;
        }

        $savedFen = $savedState['fen'] ?? null;
        $replayFen = $replayState['fen'] ?? null;
        if (!is_string($savedFen)) {
            $errors[] = 'Saved final FEN is missing.';

            return;
        }
        if ($replayFen !== $savedFen) {
            $errors[] = "Final FEN mismatch; saved {$savedFen}, replayed "
                . (is_string($replayFen) ? $replayFen : 'missing') . '.';
        }
    }

    /**
     * @param array<string, mixed> $replayState
     * @param array<string, mixed>|null $savedState
     * @param list<string> $errors
     */
    private function verifyResult(GameRecord $game, array $replayState, ?array $savedState, array &$errors): void
    {
        $recordResult = $this->resultToken($game->result);
        $savedResult = $this->resultToken($savedState['result'] ?? null);
        $replayResult = $this->resultToken($replayState['result'] ?? null);

        if ($savedState !== null && $savedResult !== $recordResult) {
            $errors[] = "Result mismatch; game record has {$recordResult}, saved state has {$savedResult}.";
        }

        if ($replayResult !== '*' && $replayResult !== $recordResult) {
            $errors[] = "Replay result mismatch; game record has {$recordResult}, replay produced {$replayResult}.";
        }
    }

    private function resultToken(mixed $result): string
    {
        return in_array($result, ['1-0', '0-1', '1/2-1/2'], true) ? (string) $result : '*';
    }
}
