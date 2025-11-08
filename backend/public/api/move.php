<?php

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

use SoloChess\Controllers\GameController;
use SoloChess\Http\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$rawBody = file_get_contents('php://input');
$payload = [];

if (is_string($rawBody) && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = $decoded;
    }
}

$controller = new GameController();
$controller->submitMove($payload);
