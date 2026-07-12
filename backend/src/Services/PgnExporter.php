<?php

declare(strict_types=1);

namespace SoloChess\Services;

use DateTimeImmutable;
use DateTimeZone;
use SoloChess\Repositories\GameRecord;
use SoloChess\Repositories\MoveRecord;
use Throwable;

final class PgnExporter
{
    /**
     * @param list<MoveRecord> $moves
     */
    public function export(GameRecord $game, array $moves): string
    {
        $result = $this->result($game);
        $headers = [
            'Event' => 'PHP Solo Chess Local Game',
            'Site' => 'Local PHP Solo Chess',
            'Date' => $this->date($game),
            'Round' => '-',
            'White' => $game->whiteLabel,
            'Black' => $game->blackLabel,
            'Result' => $result,
            'TimeControl' => $this->timeControl($game),
        ];

        return $this->tagPairs($headers) . "\n\n" . $this->movetext($moves, $result) . "\n";
    }

    private function result(GameRecord $game): string
    {
        return in_array($game->result, ['1-0', '0-1', '1/2-1/2'], true) ? (string) $game->result : '*';
    }

    private function date(GameRecord $game): string
    {
        $source = $game->completedAt ?? $game->createdAt;

        try {
            return (new DateTimeImmutable($source))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y.m.d');
        } catch (Throwable) {
            return '????.??.??';
        }
    }

    private function timeControl(GameRecord $game): string
    {
        if ($game->timeControlJson === null) {
            return '-';
        }

        $timeControl = json_decode($game->timeControlJson, true);
        if (!is_array($timeControl) || ($timeControl['kind'] ?? null) === 'untimed') {
            return '-';
        }

        $base = $timeControl['baseMilliseconds'] ?? null;
        $increment = $timeControl['incrementMilliseconds'] ?? null;
        if (!is_int($base) || !is_int($increment)) {
            return '-';
        }

        return intdiv($base, 1_000) . '+' . intdiv($increment, 1_000);
    }

    /**
     * @param array<string, string> $headers
     */
    private function tagPairs(array $headers): string
    {
        $pairs = [];
        foreach ($headers as $name => $value) {
            $pairs[] = '[' . $name . ' "' . $this->escapeTagValue($value) . '"]';
        }

        return implode("\n", $pairs);
    }

    private function escapeTagValue(string $value): string
    {
        $singleLine = preg_replace('/\s+/', ' ', trim($value));
        if (!is_string($singleLine) || $singleLine === '') {
            $singleLine = '-';
        }

        return str_replace(['\\', '"'], ['\\\\', '\\"'], $singleLine);
    }

    /**
     * @param list<MoveRecord> $moves
     */
    private function movetext(array $moves, string $result): string
    {
        $tokens = [];
        foreach ($moves as $index => $move) {
            if ($index % 2 === 0) {
                $tokens[] = ((int) floor($index / 2) + 1) . '.';
            }
            $tokens[] = $move->san;
        }
        $tokens[] = $result;

        return implode(' ', $tokens);
    }
}
