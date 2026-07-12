<?php

declare(strict_types=1);

namespace SoloChess\Http;

use InvalidArgumentException;

final class JsonRequest
{
    /** @return array<string, mixed> */
    public static function readObject(): array
    {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || $rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Malformed JSON body.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
