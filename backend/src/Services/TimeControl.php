<?php

declare(strict_types=1);

namespace SoloChess\Services;

use InvalidArgumentException;

final class TimeControl
{
    private const PRESETS = [
        '1+0' => [1, 0],
        '3+2' => [3, 2],
        '5+0' => [5, 0],
        '10+0' => [10, 0],
        '15+10' => [15, 10],
    ];
    private const MAX_BASE_MINUTES = 180;
    private const MAX_INCREMENT_SECONDS = 60;

    private function __construct(
        private string $kind,
        private string $label,
        private ?int $baseMilliseconds,
        private int $incrementMilliseconds,
    ) {}

    public static function fromPayload(mixed $payload): self
    {
        if ($payload === null || $payload === '' || self::payloadKind($payload) === 'untimed') {
            return self::untimed();
        }

        if (is_string($payload)) {
            return self::preset($payload);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Time control must be untimed, a preset, or a custom control.');
        }

        $kind = self::optionalString($payload['kind'] ?? $payload['type'] ?? null);
        if ($kind === 'preset' || isset($payload['preset'])) {
            return self::preset(self::requiredString($payload['preset'] ?? null, 'preset'));
        }
        if ($kind === 'custom') {
            return self::custom(
                self::requiredInt($payload['baseMinutes'] ?? null, 'baseMinutes'),
                self::requiredInt($payload['incrementSeconds'] ?? null, 'incrementSeconds'),
            );
        }

        throw new InvalidArgumentException('Timed games require a supported preset or custom base and increment.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'baseMilliseconds' => $this->baseMilliseconds,
            'incrementMilliseconds' => $this->incrementMilliseconds,
        ];
    }

    /** @return array<string, int|string|null> */
    public function initialClockState(int $turnStartedAtMilliseconds): array
    {
        if ($this->kind === 'untimed') {
            return [
                'mode' => 'untimed',
                'activeColor' => 'white',
                'whiteRemainingMilliseconds' => null,
                'blackRemainingMilliseconds' => null,
                'turnStartedAtMilliseconds' => null,
                'incrementMilliseconds' => 0,
            ];
        }

        return [
            'mode' => 'timed',
            'activeColor' => 'white',
            'whiteRemainingMilliseconds' => $this->baseMilliseconds,
            'blackRemainingMilliseconds' => $this->baseMilliseconds,
            'turnStartedAtMilliseconds' => $turnStartedAtMilliseconds,
            'incrementMilliseconds' => $this->incrementMilliseconds,
        ];
    }

    private static function untimed(): self
    {
        return new self('untimed', 'Untimed', null, 0);
    }

    private static function preset(string $label): self
    {
        if (!isset(self::PRESETS[$label])) {
            throw new InvalidArgumentException('Unsupported time-control preset.');
        }

        [$baseMinutes, $incrementSeconds] = self::PRESETS[$label];

        return new self('preset', $label, $baseMinutes * 60_000, $incrementSeconds * 1_000);
    }

    private static function custom(int $baseMinutes, int $incrementSeconds): self
    {
        if ($baseMinutes < 1 || $baseMinutes > self::MAX_BASE_MINUTES) {
            throw new InvalidArgumentException('Custom base minutes are outside the supported range.');
        }
        if ($incrementSeconds < 0 || $incrementSeconds > self::MAX_INCREMENT_SECONDS) {
            throw new InvalidArgumentException('Custom increment seconds are outside the supported range.');
        }

        return new self('custom', "{$baseMinutes}+{$incrementSeconds}", $baseMinutes * 60_000, $incrementSeconds * 1_000);
    }

    private static function payloadKind(mixed $payload): ?string
    {
        if (is_string($payload)) {
            return $payload;
        }
        if (!is_array($payload)) {
            return null;
        }

        return self::optionalString($payload['kind'] ?? $payload['type'] ?? null);
    }

    private static function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing time-control field: {$field}.");
        }

        return trim($value);
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function requiredInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Missing numeric time-control field: {$field}.");
    }
}
