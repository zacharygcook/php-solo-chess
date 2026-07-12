<?php

declare(strict_types=1);

namespace SoloChess\Services;

final class GameClock
{
    /** @var callable(): int */
    private $currentTimeMilliseconds;

    public function __construct(?callable $currentTimeMilliseconds = null)
    {
        $this->currentTimeMilliseconds = $currentTimeMilliseconds ?? static fn(): int => (int) floor(microtime(true) * 1_000);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function withCurrentView(array $state): array
    {
        if (($state['gameStatus'] ?? 'active') === 'finished') {
            return $state;
        }

        $clock = $this->timedClock($state);
        if ($clock === null) {
            return $state;
        }

        $now = ($this->currentTimeMilliseconds)();
        $activeColor = $this->activeColor($clock);
        $remainingKey = $this->remainingKey($activeColor);
        $remaining = $clock[$remainingKey];
        $turnStartedAt = $clock['turnStartedAtMilliseconds'];

        if (!is_int($remaining) || !is_int($turnStartedAt)) {
            return $state;
        }

        $clock[$remainingKey] = $this->remainingAfterElapsed($remaining, $turnStartedAt, $now);
        $clock['turnStartedAtMilliseconds'] = $now;
        $state['clockState'] = $clock;

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function recordAcceptedMove(array $state, string $movingColor, string $nextColor): array
    {
        $clock = $this->timedClock($state);
        if ($clock === null) {
            return $state;
        }

        $now = ($this->currentTimeMilliseconds)();
        $remainingKey = $this->remainingKey($movingColor);
        $remaining = $clock[$remainingKey];
        $turnStartedAt = $clock['turnStartedAtMilliseconds'];
        $increment = $clock['incrementMilliseconds'];

        if (!is_int($remaining) || !is_int($turnStartedAt) || !is_int($increment)) {
            return $state;
        }

        $clock[$remainingKey] = $this->remainingAfterElapsed($remaining, $turnStartedAt, $now) + $increment;
        $clock['activeColor'] = $nextColor;
        $clock['turnStartedAtMilliseconds'] = $now;
        $state['clockState'] = $clock;

        return $state;
    }

    /** @param array<string, mixed> $state */
    public function timedOutColor(array $state): ?string
    {
        if (($state['gameStatus'] ?? 'active') === 'finished') {
            return null;
        }

        $clock = $this->timedClock($state);
        if ($clock === null) {
            return null;
        }

        $activeColor = $this->activeColor($clock);
        $remaining = $clock[$this->remainingKey($activeColor)] ?? null;
        $turnStartedAt = $clock['turnStartedAtMilliseconds'] ?? null;
        if (!is_int($remaining) || !is_int($turnStartedAt)) {
            return null;
        }

        return $this->remainingAfterElapsed($remaining, $turnStartedAt, ($this->currentTimeMilliseconds)()) === 0
            ? $activeColor
            : null;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function withTimedOutClock(array $state, string $color): array
    {
        $clock = $this->timedClock($state);
        if ($clock === null) {
            return $state;
        }

        $clock[$this->remainingKey($color)] = 0;
        $clock['turnStartedAtMilliseconds'] = ($this->currentTimeMilliseconds)();
        $state['clockState'] = $clock;

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function withLatestMoveClockSnapshot(array $state): array
    {
        $history = $state['moveHistory'] ?? null;
        if (!is_array($history) || $history === []) {
            return $state;
        }

        $clock = $state['clockState'] ?? null;
        if (!is_array($clock)) {
            return $state;
        }

        $index = count($history) - 1;
        $state['moveHistory'][$index]['whiteClockMilliseconds'] = $this->nullableInt($clock['whiteRemainingMilliseconds'] ?? null);
        $state['moveHistory'][$index]['blackClockMilliseconds'] = $this->nullableInt($clock['blackRemainingMilliseconds'] ?? null);

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function timedClock(array $state): ?array
    {
        $clock = $state['clockState'] ?? null;
        if (!is_array($clock) || ($clock['mode'] ?? null) !== 'timed') {
            return null;
        }

        return $clock;
    }

    /** @param array<string, mixed> $clock */
    private function activeColor(array $clock): string
    {
        return ($clock['activeColor'] ?? null) === 'black' ? 'black' : 'white';
    }

    private function remainingKey(string $color): string
    {
        return $color === 'black' ? 'blackRemainingMilliseconds' : 'whiteRemainingMilliseconds';
    }

    private function remainingAfterElapsed(int $remaining, int $turnStartedAt, int $now): int
    {
        return max(0, $remaining - max(0, $now - $turnStartedAt));
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }
}
