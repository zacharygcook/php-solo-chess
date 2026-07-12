<?php

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';

use SoloChess\Http\JsonRequest;
use SoloChess\Http\JsonResponse;

/** @return array<string, mixed> */
function soloChessReadJsonBody(): array
{
    try {
        return JsonRequest::readObject();
    } catch (\InvalidArgumentException $error) {
        JsonResponse::send(['success' => false, 'message' => $error->getMessage()], 400);

        return [];
    }
}

function soloChessGameIdFromQuery(): ?int
{
    $raw = $_GET['id'] ?? null;
    if (!is_string($raw) && !is_int($raw)) {
        return null;
    }

    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return is_int($id) ? $id : null;
}
