<?php

declare(strict_types=1);

namespace SoloChess\Services;

use InvalidArgumentException;
use SoloChess\Services\Chess\GameStateFactory;

final class GameLifecycleService
{
    /** @var callable(): int */
    private $currentTimeMilliseconds;

    public function __construct(
        private ?GameStateFactory $stateFactory = null,
        ?callable $currentTimeMilliseconds = null,
    ) {
        $this->stateFactory ??= new GameStateFactory();
        $this->currentTimeMilliseconds = $currentTimeMilliseconds ?? static fn(): int => (int) floor(microtime(true) * 1_000);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createGame(array $payload): array
    {
        $timeControl = TimeControl::fromPayload($payload['timeControl'] ?? null);
        $state = $this->stateFactory->create();
        $state['participants'] = [
            'white' => $this->participant($payload, 'white', 'White'),
            'black' => $this->participant($payload, 'black', 'Black'),
        ];
        $state['timeControl'] = $timeControl->toArray();
        $state['clockState'] = $timeControl->initialClockState(($this->currentTimeMilliseconds)());
        $state['lastMessage'] = 'New game ready.';

        return $state;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{label: string, type: string}
     */
    private function participant(array $payload, string $color, string $defaultLabel): array
    {
        $nested = is_array($payload['participants'][$color] ?? null) ? $payload['participants'][$color] : [];
        $label = $this->label($payload["{$color}Label"] ?? $nested['label'] ?? null, $defaultLabel);
        $type = $this->type($payload["{$color}ParticipantType"] ?? $nested['type'] ?? null);

        return ['label' => $label, 'type' => $type];
    }

    private function label(mixed $value, string $default): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Participant labels must be text.');
        }

        $label = trim($value);
        if (strlen($label) > 80) {
            throw new InvalidArgumentException('Participant labels must be 80 characters or fewer.');
        }

        return $label;
    }

    private function type(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'local_human';
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Participant type must be text.');
        }

        $type = trim($value);
        if (!in_array($type, ['local_human', 'engine'], true)) {
            throw new InvalidArgumentException('Unsupported participant type.');
        }

        return $type;
    }
}
