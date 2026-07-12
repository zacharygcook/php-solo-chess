<?php

declare(strict_types=1);

namespace SoloChess\Engine;

use InvalidArgumentException;

final class EngineRequest
{
    /**
     * @param array{white: array{label: string, type: string}, black: array{label: string, type: string}} $participants
     * @param array<string, list<string>> $legalMoves
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $fen,
        public readonly string $activeColor,
        public readonly array $participants,
        public readonly array $legalMoves,
        public readonly string $gameStatus,
        public readonly ?string $result,
        public readonly array $context = [],
    ) {
        if (trim($fen) === '') {
            throw new InvalidArgumentException('Engine requests require canonical FEN.');
        }
        if (!in_array($activeColor, ['white', 'black'], true)) {
            throw new InvalidArgumentException('Engine requests require an active color.');
        }
    }

    /** @param array<string, mixed> $state */
    public static function fromState(array $state): self
    {
        return new self(
            self::stringValue($state['fen'] ?? null, 'Engine requests require canonical FEN.'),
            self::stringValue($state['activeColor'] ?? null, 'Engine requests require an active color.'),
            [
                'white' => self::participant($state, 'white', 'White'),
                'black' => self::participant($state, 'black', 'Black'),
            ],
            self::legalMoves($state['legalMoves'] ?? []),
            self::stringValue($state['gameStatus'] ?? 'active', 'Engine requests require game status.'),
            is_string($state['result'] ?? null) ? $state['result'] : null,
            [
                'halfmoveClock' => is_int($state['halfmoveClock'] ?? null) ? $state['halfmoveClock'] : 0,
                'fullmoveNumber' => is_int($state['fullmoveNumber'] ?? null) ? $state['fullmoveNumber'] : 1,
                'timeControl' => is_array($state['timeControl'] ?? null) ? $state['timeControl'] : [],
            ],
        );
    }

    /**
     * @param array<string, mixed> $state
     * @return array{label: string, type: string}
     */
    private static function participant(array $state, string $color, string $defaultLabel): array
    {
        $participants = is_array($state['participants'] ?? null) ? $state['participants'] : [];
        $participant = is_array($participants[$color] ?? null) ? $participants[$color] : [];
        $label = is_string($participant['label'] ?? null) && trim($participant['label']) !== ''
            ? trim($participant['label'])
            : $defaultLabel;
        $type = is_string($participant['type'] ?? null) && trim($participant['type']) !== ''
            ? trim($participant['type'])
            : 'local_human';

        return ['label' => $label, 'type' => $type];
    }

    /**
     * @param mixed $value
     * @return array<string, list<string>>
     */
    private static function legalMoves(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $moves = [];
        foreach ($value as $from => $destinations) {
            if (!is_string($from) || !is_array($destinations)) {
                continue;
            }
            $moves[$from] = array_values(array_filter(
                $destinations,
                static fn(mixed $destination): bool => is_string($destination),
            ));
        }
        return $moves;
    }

    private static function stringValue(mixed $value, string $message): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($message);
        }

        return trim($value);
    }
}
